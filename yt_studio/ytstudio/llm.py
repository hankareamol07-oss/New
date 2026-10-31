"""Chat-completion helper with provider fallback (NVIDIA -> Gemini -> Groq). Returns parsed JSON."""
import json
import re
import time

import requests

from . import vertex

OPENAI_URLS = {
    "nvidia": "https://integrate.api.nvidia.com/v1/chat/completions",
    "groq": "https://api.groq.com/openai/v1/chat/completions",
}


class LLMError(Exception):
    pass


_key_idx = {}


def _keys(cfg, provider):
    """cfg['keys'][provider] may be one key or a list; rotated on quota errors."""
    v = cfg["keys"].get(provider)
    keys = [k for k in (v if isinstance(v, list) else [v]) if k]
    if not keys:
        raise LLMError(f"{provider}: no API key")
    i = _key_idx.get(provider, 0) % len(keys)
    return keys[i:] + keys[:i]


def _rotate(provider):
    _key_idx[provider] = _key_idx.get(provider, 0) + 1


def _quota(status):
    return status in (401, 403, 429)


def _extract_json(text):
    text = text.strip()
    text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text, flags=re.S)
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        m = re.search(r"[\[{].*[\]}]", text, re.S)
        if not m:
            raise
        try:
            return json.loads(m.group(0))
        except json.JSONDecodeError:
            return json.loads(_repair_json(m.group(0)))


def _repair_json(t):
    """Fix the usual model slips: LaTeX-style backslashes (\\frac, \\( ), raw newlines inside strings,
    and output cut off mid-way (drop the incomplete trailing item and close the brackets)."""
    t = re.sub(r"(?<!\\)\\(?![\\/\"u]|[bfnrt](?![A-Za-z]))", r"\\\\", t)
    out, in_str, esc = [], False, False
    for ch in t:
        if in_str:
            if esc:
                esc = False
            elif ch == "\\":
                esc = True
            elif ch == '"':
                in_str = False
            elif ch == "\n":
                ch = "\\n"
            elif ch == "\t":
                ch = " "
            out.append(ch)
        else:
            if ch == '"':
                in_str = True
            out.append(ch)
    t = "".join(out)
    try:
        json.loads(t)
        return t
    except json.JSONDecodeError:
        pass
    i = t.rfind("}")
    while i > 0:
        head = t[:i + 1]
        stack = []
        ok = True
        for ch in re.sub(r'"(?:[^"\\]|\\.)*"', "", head):
            if ch in "[{":
                stack.append(ch)
            elif ch in "]}":
                if not stack or {"]": "[", "}": "{"}[ch] != stack.pop():
                    ok = False
                    break
        if ok:
            cand = head + "".join({"[": "]", "{": "}"}[c] for c in reversed(stack))
            try:
                json.loads(cand)
                return cand
            except json.JSONDecodeError:
                pass
        i = t.rfind("}", 0, i)
    return t


def _openai(provider, cfg, system, user, max_tokens):
    body = {
        "model": cfg[f"{provider}_model"],
        "temperature": 0.4,
        "max_tokens": max_tokens,
        "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}],
    }
    for key in _keys(cfg, provider):
        r = requests.post(OPENAI_URLS[provider], json=body, timeout=180,
                          headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"})
        if r.status_code == 200:
            return r.json()["choices"][0]["message"]["content"]
        if not _quota(r.status_code):
            break
        _rotate(provider)
    raise LLMError(f"{provider} HTTP {r.status_code}: {r.text[:200]}")


def _gemini(cfg, system, user, max_tokens):
    body = {
        "systemInstruction": {"parts": [{"text": system}]},
        "contents": [{"role": "user", "parts": [{"text": user}]}],
        "generationConfig": {"temperature": 0.4, "maxOutputTokens": max_tokens, "responseMimeType": "application/json"},
    }
    keys = _keys(cfg, "gemini") if cfg["keys"].get("gemini") else []
    if not keys and not vertex.enabled(cfg):
        raise LLMError("gemini: no API key and no vertex config")
    r = None
    for label, url, h in vertex.endpoints(cfg, cfg["gemini_model"], keys):
        if url is None:
            continue
        r = requests.post(url, json=body, headers=h, timeout=180)
        if r.status_code == 200:
            return r.json()["candidates"][0]["content"]["parts"][0]["text"]
        if label == "vertex":   # any Vertex failure (model not there, quota): fall back to API keys
            continue
        if not _quota(r.status_code):
            break
        _rotate("gemini")
    if r is None:
        raise LLMError("gemini: vertex credentials failed")
    raise LLMError(f"gemini HTTP {r.status_code}: {r.text[:200]}")


def chat_json(cfg, system, user, max_tokens=8192, log=print, shrink=None):
    """shrink(n) -> the same prompt with its source text cut to n chars; used when a provider says the request is too large."""
    errors = []
    for provider in cfg["llm_order"]:
        text, limit = user, len(user)
        for attempt in range(3):
            try:
                if provider == "gemini":
                    out = _gemini(cfg, system, text, max_tokens)
                else:
                    out = _openai(provider, cfg, system, text, max_tokens)
                return _extract_json(out), f"{provider}/{cfg.get(provider + '_model')}"
            except (LLMError, requests.RequestException, json.JSONDecodeError, KeyError) as e:
                errors.append(f"{provider}: {e}")
                log(f"  [llm] {provider} attempt {attempt + 1} failed: {str(e)[:120]}")
                msg = str(e)
                if "HTTP 413" in msg or "too large" in msg.lower():   # free-tier request size: send half the source text
                    if not shrink:
                        break
                    limit //= 2
                    text = shrink(limit)
                    log(f"  [llm] {provider}: prompt too large, retrying with source text cut to {limit} chars")
                elif "HTTP 503" in msg or "HTTP 429" in msg or "HTTP 500" in msg:   # overloaded: wait
                    time.sleep(15 * (attempt + 1))
                else:
                    time.sleep(2)
    raise LLMError("all providers failed:\n" + "\n".join(errors))
