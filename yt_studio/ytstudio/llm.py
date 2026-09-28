"""Chat-completion helper with provider fallback (NVIDIA -> Gemini -> Groq). Returns parsed JSON."""
import json
import re
import time

import requests

OPENAI_URLS = {
    "nvidia": "https://integrate.api.nvidia.com/v1/chat/completions",
    "groq": "https://api.groq.com/openai/v1/chat/completions",
}


class LLMError(Exception):
    pass


def _extract_json(text):
    text = text.strip()
    text = re.sub(r"^```(?:json)?\s*|\s*```$", "", text, flags=re.S)
    try:
        return json.loads(text)
    except json.JSONDecodeError:
        m = re.search(r"[\[{].*[\]}]", text, re.S)
        if not m:
            raise
        return json.loads(m.group(0))


def _openai(provider, cfg, system, user, max_tokens):
    key = cfg["keys"].get(provider)
    if not key:
        raise LLMError(f"{provider}: no API key")
    body = {
        "model": cfg[f"{provider}_model"],
        "temperature": 0.4,
        "max_tokens": max_tokens,
        "messages": [{"role": "system", "content": system}, {"role": "user", "content": user}],
    }
    r = requests.post(OPENAI_URLS[provider], json=body, timeout=180,
                      headers={"Authorization": f"Bearer {key}", "Content-Type": "application/json"})
    if r.status_code != 200:
        raise LLMError(f"{provider} HTTP {r.status_code}: {r.text[:200]}")
    return r.json()["choices"][0]["message"]["content"]


def _gemini(cfg, system, user, max_tokens):
    key = cfg["keys"].get("gemini")
    if not key:
        raise LLMError("gemini: no API key")
    url = f"https://generativelanguage.googleapis.com/v1beta/models/{cfg['gemini_model']}:generateContent?key={key}"
    body = {
        "systemInstruction": {"parts": [{"text": system}]},
        "contents": [{"role": "user", "parts": [{"text": user}]}],
        "generationConfig": {"temperature": 0.4, "maxOutputTokens": max_tokens, "responseMimeType": "application/json"},
    }
    r = requests.post(url, json=body, timeout=180)
    if r.status_code != 200:
        raise LLMError(f"gemini HTTP {r.status_code}: {r.text[:200]}")
    return r.json()["candidates"][0]["content"]["parts"][0]["text"]


def chat_json(cfg, system, user, max_tokens=8192, log=print):
    errors = []
    for provider in cfg["llm_order"]:
        for attempt in range(2):
            try:
                if provider == "gemini":
                    out = _gemini(cfg, system, user, max_tokens)
                else:
                    out = _openai(provider, cfg, system, user, max_tokens)
                return _extract_json(out), f"{provider}/{cfg.get(provider + '_model')}"
            except (LLMError, requests.RequestException, json.JSONDecodeError, KeyError) as e:
                errors.append(f"{provider}: {e}")
                log(f"  [llm] {provider} attempt {attempt + 1} failed: {str(e)[:120]}")
                time.sleep(2)
    raise LLMError("all providers failed:\n" + "\n".join(errors))
