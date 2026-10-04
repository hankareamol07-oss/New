"""One client for every API the user owns: Gemini on Vertex AI (GCP key file / API key), Gemini API keys, Anthropic Claude,
OpenAI, OpenRouter (Kimi/Qwen/DeepSeek/...), Mistral, Together, NVIDIA NIM, Groq, Sarvam and any custom OpenAI-compatible URL.
Text + image input (vision-capable providers), JSON output, retries, running cost estimate with a hard cap."""
import base64, io, json, os, re, threading, time
import requests

SCOPE = "https://www.googleapis.com/auth/cloud-platform"
# provider -> (chat-completions URL, default model, vision capable).  Config keys: <provider>_api_key, <provider>_model
PROVIDERS = {
    "gemini": (None, "gemini-2.5-flash", True),
    "anthropic": ("https://api.anthropic.com/v1/messages", "claude-sonnet-4-5", True),
    "openai": ("https://api.openai.com/v1/chat/completions", "gpt-4o-mini", True),
    "openrouter": ("https://openrouter.ai/api/v1/chat/completions", "moonshotai/kimi-k2", True),
    "mistral": ("https://api.mistral.ai/v1/chat/completions", "mistral-small-latest", True),
    "together": ("https://api.together.xyz/v1/chat/completions", "meta-llama/Llama-3.3-70B-Instruct-Turbo", False),
    "groq": ("https://api.groq.com/openai/v1/chat/completions", "openai/gpt-oss-120b", False),
    "nvidia": ("https://integrate.api.nvidia.com/v1/chat/completions", "meta/llama-3.3-70b-instruct", False),
    "sarvam": ("https://api.sarvam.ai/v1/chat/completions", "sarvam-m", False),
    "custom": (None, "", False),   # custom_url + custom_api_key + custom_model (any OpenAI-compatible server, e.g. Ollama/LM Studio)
}
OPENAI_URLS = {k: v[0] for k, v in PROVIDERS.items() if v[0] and k != "anthropic"}
# USD per 1M tokens (input, output) – rough list prices used only for the cost cap
PRICE = {"gemini-2.5-flash": (0.30, 2.50), "gemini-2.5-pro": (1.25, 10.0), "gemini-2.0-flash": (0.10, 0.40),
         "gemini-3-flash-preview": (0.50, 3.0), "claude-sonnet-4-5": (3.0, 15.0), "claude-haiku-4-5": (1.0, 5.0), "gpt-4o-mini": (0.15, 0.60),
         "gpt-4.1-mini": (0.40, 1.60), "gpt-4o": (2.50, 10.0), "default": (0.50, 3.0)}


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
        tin = usage.get("promptTokenCount") or usage.get("prompt_tokens") or usage.get("input_tokens") or 0
        tout = usage.get("candidatesTokenCount") or usage.get("completion_tokens") or usage.get("output_tokens") or 0
        c = tin * pi / 1e6 + tout * po / 1e6
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


def img_b64(img, max_px=1200):
    from PIL import Image
    im = Image.open(img) if isinstance(img, str) else img
    im = im.convert("RGB")
    im.thumbnail((max_px, max_px))
    buf = io.BytesIO()
    im.save(buf, "JPEG", quality=85)
    return base64.b64encode(buf.getvalue()).decode()


def img_part(img, max_px=1200):
    """PIL image or path -> Gemini inline_data part (JPEG)."""
    return {"inline_data": {"mime_type": "image/jpeg", "data": img_b64(img, max_px)}}


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
        self.order = [p for p in (cfg.get("llm_order") or ["gemini", "nvidia", "groq"]) if p in PROVIDERS]

    def has(self, provider):
        if provider == "gemini":
            v = self.cfg.get("vertex") or {}
            return bool(self.cfg.get("gemini_api_keys")) or bool(v.get("project") and (v.get("key_file") or v.get("api_key")))
        if provider == "custom":
            return bool(self.cfg.get("custom_url"))
        return bool(self.cfg.get(f"{provider}_api_key"))

    def vision_ok(self, provider):
        return PROVIDERS[provider][2] or bool(self.cfg.get(f"{provider}_vision"))

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

    # ---- OpenAI-compatible providers (text + optional images) ----
    def openai(self, provider, system, user, images=(), max_tokens=8192, json_out=True):
        key = self.cfg.get(f"{provider}_api_key") or ""
        url = self.cfg.get("custom_url") if provider == "custom" else OPENAI_URLS[provider]
        if not url or (not key and provider != "custom"):
            raise LLMError(f"{provider}: no API key")
        model = self.cfg.get(f"{provider}_model") or PROVIDERS[provider][1]
        content = user if not images else [{"type": "text", "text": user}] + [
            {"type": "image_url", "image_url": {"url": "data:image/jpeg;base64," + img_b64(i)}} for i in images]
        body = {"model": model, "temperature": 0.3, "max_tokens": max_tokens,
                "messages": [{"role": "system", "content": system}, {"role": "user", "content": content}]}
        if json_out and provider in ("openai", "openrouter", "mistral", "groq", "together"):
            body["response_format"] = {"type": "json_object"}
        h = {"Authorization": f"Bearer {key}"} if key else {}
        if provider == "openrouter":
            h["HTTP-Referer"], h["X-Title"] = "https://github.com/pdf-agent", "pdf_agent"
        r = None
        for attempt in range(3):
            r = requests.post(url, json=body, timeout=300, headers=h)
            if r.status_code == 200:
                js = r.json()
                self.cost.add(model, js.get("usage"))
                text = js["choices"][0]["message"]["content"]
                return (extract_json(text) if json_out else text), f"{provider}/{model}"
            if r.status_code not in (429, 500, 502, 503):
                break
            time.sleep(10 * (attempt + 1))
        raise LLMError(f"{provider} HTTP {r.status_code}: {r.text[:160]}")

    # ---- Anthropic Claude (text + images) ----
    def anthropic(self, system, user, images=(), max_tokens=8192, json_out=True):
        key = self.cfg.get("anthropic_api_key")
        if not key:
            raise LLMError("anthropic: no API key")
        model = self.cfg.get("anthropic_model") or PROVIDERS["anthropic"][1]
        content = [{"type": "image", "source": {"type": "base64", "media_type": "image/jpeg", "data": img_b64(i)}} for i in images]
        content.append({"type": "text", "text": user + ("\nRespond with JSON only." if json_out else "")})
        body = {"model": model, "max_tokens": max_tokens, "temperature": 0.3, "system": system, "messages": [{"role": "user", "content": content}]}
        r = None
        for attempt in range(3):
            r = requests.post(PROVIDERS["anthropic"][0], json=body, timeout=300,
                              headers={"x-api-key": key, "anthropic-version": "2023-06-01", "content-type": "application/json"})
            if r.status_code == 200:
                js = r.json()
                self.cost.add(model, js.get("usage"))
                text = "".join(b.get("text", "") for b in js.get("content", []))
                return (extract_json(text) if json_out else text), f"anthropic/{model}"
            if r.status_code not in (429, 500, 529, 503):
                break
            time.sleep(10 * (attempt + 1))
        raise LLMError(f"anthropic HTTP {r.status_code}: {r.text[:160]}")

    def call(self, provider, system, user, images=(), max_tokens=8192):
        if provider == "gemini":
            return self.gemini(system, user, images, max_tokens=max_tokens)
        if provider == "anthropic":
            return self.anthropic(system, user, images, max_tokens)
        return self.openai(provider, system, user, images, max_tokens)

    def chat_json(self, system, user, images=(), max_tokens=8192):
        """Try configured providers in cfg.llm_order; requests with images only go to vision-capable ones."""
        errors = []
        order = [p for p in self.order if self.has(p) and (not images or self.vision_ok(p))] or (["gemini"] if images else self.order)
        for p in order:
            try:
                return self.call(p, system, user, images, max_tokens)
            except (LLMError, json.JSONDecodeError, KeyError, ValueError) as e:
                errors.append(f"{p}: {str(e)[:120]}")
                if "cost cap" in str(e):
                    raise
        raise LLMError("all providers failed: " + " | ".join(errors))
