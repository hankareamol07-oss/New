"""config.json next to the app: language, voices, AI keys. Keys never leave this file (gitignored)."""
import copy
import json
import os

from . import paths

DEFAULTS = {
    "lang": "mr",
    "shorts": False,
    "music": "calm",
    "music_volume": 0.16,
    "captions": True,
    "tts_engine": "edge",
    "llm_order": ["gemini", "nvidia", "groq"],
    "gemini_api_key": "",
    "nvidia_api_key": "",
    "groq_api_key": "",
    "sarvam_api_key": "",
    "gemini_model": "gemini-2.5-flash",
    "nvidia_model": "meta/llama-3.3-70b-instruct",
    "groq_model": "llama-3.3-70b-versatile",
    "vertex": {"project": "", "location": "us-central1", "key_file": ""},
    "cost_cap_inr": 100,
    "voices": {},
}


def load():
    cfg = copy.deepcopy(DEFAULTS)
    if os.path.exists(paths.CONFIG):
        try:
            with open(paths.CONFIG, encoding="utf8") as f:
                user = json.load(f)
            for k, v in user.items():
                if isinstance(v, dict) and isinstance(cfg.get(k), dict):
                    cfg[k].update(v)
                else:
                    cfg[k] = v
        except (OSError, ValueError):
            pass
    return cfg


def save(cfg):
    with open(paths.CONFIG, "w", encoding="utf8") as f:
        json.dump({k: v for k, v in cfg.items() if not k.startswith("_")}, f, ensure_ascii=False, indent=1)


def llm_cfg(cfg):
    """The shape ytstudio.llm.chat_json expects."""
    def kl(name, env):
        v = os.environ.get(env) or cfg.get(name) or ""
        return [k.strip() for k in (v.split(",") if isinstance(v, str) else v) if k and k.strip()]
    v = dict(cfg.get("vertex") or {})
    if v.get("key_file") and not os.path.isabs(v["key_file"]):
        v["key_file"] = os.path.join(paths.HERE, v["key_file"])
    if not (v.get("project") and v.get("key_file") and os.path.exists(v["key_file"])) and not v.get("api_key"):
        v = {}
    out = dict(cfg, vertex=v, keys={"gemini": kl("gemini_api_key", "GEMINI_API_KEY"),
                                    "nvidia": kl("nvidia_api_key", "NVIDIA_API_KEY"),
                                    "groq": kl("groq_api_key", "GROQ_API_KEY")})
    out["llm_order"] = [p for p in cfg.get("llm_order", []) if out["keys"].get(p) or (p == "gemini" and v)]
    return out
