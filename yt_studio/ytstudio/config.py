"""Configuration: config.json next to the package root, overridden by environment variables."""
import json
import os
import sys

if os.environ.get("YTSTUDIO_ROOT"):          # set by the yt_swadhyay / yt_explain / yt_motivation exes
    ROOT = os.path.abspath(os.environ["YTSTUDIO_ROOT"])
elif getattr(sys, "frozen", False):
    ROOT = os.path.dirname(os.path.abspath(sys.executable))
else:
    ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
REPO = os.path.dirname(ROOT)

DEFAULTS = {
    "channel_name": "स्वाध्याय",
    "channel_tagline": "इयत्ता 1 ली ते 8 वी - स्वाध्याय, सराव व सोप्या भाषेत स्पष्टीकरण",
    "brand_primary": "#1c3f95",
    "brand_accent": "#f7b500",
    "logo": "",
    "data_dir": os.path.join(REPO, "exam_paper", "data"),
    "out_dir": os.path.join(ROOT, "output"),
    "ffmpeg": "ffmpeg",
    "llm_order": ["gemini", "nvidia", "groq"],
    "nvidia_model": "nvidia/nemotron-3-super-120b-a12b",
    "gemini_model": "gemini-3-flash-preview",
    "groq_model": "openai/gpt-oss-120b",
    "tts_backend": "edge",
    "voices": {
        "mr": "mr-IN-AarohiNeural",
        "hi": "hi-IN-SwaraNeural",
        "en": "en-IN-NeerjaNeural",
    },
    "tts_rate": "-5%",
    "gemini_tts_model": "gemini-2.5-flash-preview-tts",
    "gemini_tts_voice": "Kore",
    "elevenlabs": {
        "api_keys": [],
        "voice_id": "",
        "model": "eleven_multilingual_v2",
        "stability": 0.5,
        "similarity_boost": 0.8,
    },
    "questions_per_video": 12,
    "make_short": True,
    "descript": {"enabled": False, "prompt": "", "captions": "auto", "resolution": "1080p", "agent_model": "auto"},
    "youtube": {
        "enabled": False,
        "client_secret": os.path.join(ROOT, "client_secret.json"),
        "privacy": "private",
        "category_id": "27",
        "default_language": "mr",
        "made_for_kids": False,
        "playlist_by": "std_subject",
        "schedule": {"enabled": False, "hour": 18, "minute": 0, "every_days": 1},
    },
    "n8n_webhook": "",
    "auto": {
        "stds": [6, 7, 8, 5, 4, 3, 2, 1],
        "langs": ["mr"],
        "subjects": [],
        "source": "both",
        "min_questions": 4,
        "per_run": 2,
        "max_retries": 2,
        "pause_sec": 10,
    },
}


def _merge(dst, src):
    for k, v in src.items():
        if isinstance(v, dict) and isinstance(dst.get(k), dict):
            _merge(dst[k], v)
        else:
            dst[k] = v


def load(path=None):
    cfg = json.loads(json.dumps(DEFAULTS))
    path = path or os.path.join(ROOT, "config.json")
    if os.path.exists(path):
        with open(path, encoding="utf-8") as f:
            user = json.load(f)
        _merge(cfg, user)
    for k in ("data_dir", "out_dir", "logo"):
        if cfg.get(k) and not os.path.isabs(cfg[k]):
            cfg[k] = os.path.normpath(os.path.join(ROOT, cfg[k]))
    if cfg["youtube"].get("client_secret") and not os.path.isabs(cfg["youtube"]["client_secret"]):
        cfg["youtube"]["client_secret"] = os.path.join(ROOT, cfg["youtube"]["client_secret"])
    cfg["keys"] = {
        "nvidia": os.environ.get("NVIDIA_API_KEY") or cfg.get("nvidia_api_key", ""),
        "gemini": os.environ.get("GEMINI_API_KEY") or cfg.get("gemini_api_key", ""),
        "groq": os.environ.get("GROQ_API_KEY") or cfg.get("groq_api_key", ""),
        "descript": os.environ.get("DESCRIPT_API_TOKEN") or cfg.get("descript_api_token", ""),
    }
    env_el = os.environ.get("ELEVENLABS_API_KEYS", "")
    if env_el:
        cfg["elevenlabs"]["api_keys"] = [k.strip() for k in env_el.split(",") if k.strip()]
    cfg["_path"] = path
    return cfg
