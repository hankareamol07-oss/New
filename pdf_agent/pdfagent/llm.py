"""One client for every API the user owns: Gemini on Vertex AI (GCP key file / API key), Gemini API keys,
NVIDIA NIM, Groq (OpenAI-compatible). Text + image input, JSON output, retries, running cost estimate with a hard cap."""
import base64, io, json, os, re, threading, time
import requests

SCOPE = "https://www.googleapis.com/auth/cloud-platform"
OPENAI_URLS = {"groq": "https://api.groq.com/openai/v1/chat/completions",
               "nvidia": "https://integrate.api.nvidia.com/v1/chat/completions"}
# USD per 1M tokens (input, output) – rough list prices used only for the cost cap
PRICE = {"gemini-2.5-flash": (0.30, 2.50), "gemini-2.5-pro": (1.25, 10.0), "gemini-2.0-flash": (0.10, 0.40),
         "gemini-3-flash-preview": (0.50, 3.0), "default": (0.50, 3.0)}


class LLMError(Exception):
    pass


class Cost:
    def __init__(self, cap_usd, state_file):
        self.cap, self.file, self.lock = cap_usd, state_file, threading.Lock()
        self.usd = 0.0
        if os.path.exists(state_file):
            try:
                self.usd = float(json.load(open(state_file)).get("usd", 0))
            except Exception:  # noqa: BLE001
                pass

    def add(self, model, usage):
        if not usage:
            return
        pi, po = PRICE.get(model, PRICE["default"])
        c = usage.get("promptTokenCount", 0) * pi / 1e6 + usage.get("candidatesTokenCount", 0) * po / 1e6
        with self.lock:
            self.usd += c
            json.dump({"usd": round(self.usd, 4)}, open(self.file, "w"))
            if self.usd > self.cap:
                raise LLMError(f"cost cap reached: ${self.usd:.2f} > ${self.cap}")


_tok = {"val": None, "exp": 0}


def _vertex_token(v, root):
    if _tok["val"] and time.time() < _tok["exp"] - 120:
        return _tok["val"]
    from google.auth.transport.requests import Request
    from google.oauth2 import service_account
    kf = v.get("key_file")
    if kf and not os.path.isabs(kf):
        kf = os.path.join(root, kf)
    creds = service_account.Credentials.from_service_account_file(kf, scopes=[SCOPE])
    creds.refresh(Request())
    _tok["val"], _tok["exp"] = creds.token, (creds.expiry.timestamp() if creds.expiry else time.time() + 3000)
    return creds.token


def gemini_endpoints(cfg, model, root):
    """[(label, url, headers)] – Vertex first, then each Gemini API key."""
    out = []
    v = cfg.get("vertex") or {}
    if v.get("project") and (v.get("key_file") or v.get("api_key")):
        loc = v.get("location", "global")
        host = "aiplatform.googleapis.com" if loc == "global" else f"{loc}-aiplatform.googleapis.com"
        url = f"https://{host}/v1/projects/{v['project']}/locations/{loc}/publishers/google/models/{model}:generateContent"
        try:
            h = {"x-goog-api-key": v["api_key"]} if v.get("api_key") else {"Authorization": f"Bearer {_vertex_token(v, root)}"}
            out.append(("vertex", url, h))
        except Exception as e:  # noqa: BLE001
            out.append((f"vertex-error:{str(e)[:60]}", None, None))
    for k in cfg.get("gemini_api_keys") or []:
        out.append(("gemini-key", f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent", {"x-goog-api-key": k}))
    return out


def img_part(img, max_px=1200):
    """PIL image or path -> inline_data part (JPEG)."""
    from PIL import Image
    im = Image.open(img) if isinstance(img, str) else img
    im = im.convert("RGB")
    im.thumbnail((max_px, max_px))
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=85)
    return {"inline_data": {"mime_type": "image/jpeg", "data": base64.b64encode(buf.getvalue()).decode()}}


def extract_json(t):
    t = t.strip()
    t = re.sub(r"^```(?:json)?\s*|\s*```$", "", t, flags=re.S)
    try:
        return json.loads(t)
    except json.JSONDecodeError:
        i, j = t.find("{"), t.rfind("}")
        if i >= 0 and j > i:
            return json.loads(t[i:j + 1])
        raise


class Client:
    def __init__(self, cfg, root, log=print):
        self.cfg, self.root, self.log = cfg, root, log
        self.cost = Cost(float(cfg.get("cost_cap_usd", 100)), os.path.join(root, "cost.json"))
        self.order = cfg.get("llm_order") or ["gemini", "nvidia", "groq"]

    # ---- Gemini (text + images) ----
    def gemini(self, system, user, images=(), model=None, max_tokens=8192, json_out=True, temperature=0.3):
        model = model or self.cfg.get("gemini_model", "gemini-2.5-flash")
        parts = [{"text": user}] + [img_part(i) for i in images]
        body = {"systemInstruction": {"parts": [{"text": system}]}, "contents": [{"role": "user", "parts": parts}],
                "generationConfig": {"temperature": temperature, "maxOutputTokens": max_tokens}}
        if json_out:
            body["generationConfig"]["responseMimeType"] = "application/json"
        last = "gemini: not configured"
        for attempt in range(5):
            for label, url, h in gemini_endpoints(self.cfg, model, self.root):
                if not url:
                    last = label
                    continue
                try:
                    r = requests.post(url, json=body, headers={**h, "Content-Type": "application/json"}, timeout=300)
                except requests.RequestException as e:
                    last = f"{label}: {e}"
                    continue
                if r.status_code == 200:
                    js = r.json()
                    self.cost.add(model, js.get("usageMetadata"))
                    text = js["candidates"][0]["content"]["parts"][0]["text"]
                    return (extract_json(text) if json_out else text), f"{label}/{model}"
                last = f"{label} HTTP {r.status_code}: {r.text[:160]}"
                if r.status_code not in (429, 500, 503):
                    break
            time.sleep(10 * (attempt + 1))
        raise LLMError(last)

    # ---- OpenAI-compatible text providers ----
    def openai(self, provider, system, user, max_tokens=8192, json_out=True):
        key = self.cfg.get(f"{provider}_api_key")
        if not key:
            raise LLMError(f"{provider}: no API key")
        body = {"model": self.cfg.get(f"{provider}_model"), "temperature": 0.3, "max_tokens": max_tokens,
                "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}]}
        for attempt in range(3):
            r = requests.post(OPENAI_URLS[provider], json=body, timeout=240, headers={"Authorization": f"Bearer {key}"})
            if r.status_code == 200:
                text = r.json()["choices"][0]["message"]["content"]
                return (extract_json(text) if json_out else text), f"{provider}/{body['model']}"
            if r.status_code not in (429, 500, 503):
                break
            time.sleep(10 * (attempt + 1))
        raise LLMError(f"{provider} HTTP {r.status_code}: {r.text[:160]}")

    def chat_json(self, system, user, images=(), max_tokens=8192):
        """Try providers in cfg.llm_order; anything with images goes to Gemini only."""
        errors = []
        for p in (["gemini"] if images else self.order):
            try:
                return self.gemini(system, user, images, max_tokens=max_tokens) if p == "gemini" else self.openai(p, system, user, max_tokens)
            except (LLMError, json.JSONDecodeError, KeyError) as e:
                errors.append(f"{p}: {str(e)[:120]}")
                if "cost cap" in str(e):
                    raise
        raise LLMError("all providers failed: " + " | ".join(errors))
