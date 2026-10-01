"""Gemini via Vertex AI (paid Google Cloud project) as an alternative to the free Gemini API keys.

config.json:  "vertex": {"project": "my-project-id", "location": "us-central1", "key_file": "gcp_key.json"}
When present, every Gemini call (text, TTS, transcription) goes to Vertex first; API keys stay as fallback.
Needs: pip install google-auth
"""
import os
import time

_tok = {"val": None, "exp": 0}
SCOPE = "https://www.googleapis.com/auth/cloud-platform"


def enabled(cfg):
    v = cfg.get("vertex") or {}
    return bool(v.get("project") and (v.get("key_file") or os.environ.get("GOOGLE_APPLICATION_CREDENTIALS")))


def _token(cfg):
    if _tok["val"] and time.time() < _tok["exp"] - 120:
        return _tok["val"]
    from google.auth.transport.requests import Request
    from google.oauth2 import service_account
    kf = (cfg.get("vertex") or {}).get("key_file")
    if kf and not os.path.isabs(kf):
        kf = os.path.join(os.getcwd(), kf)
    if kf and os.path.exists(kf):
        creds = service_account.Credentials.from_service_account_file(kf, scopes=[SCOPE])
    else:
        import google.auth
        creds, _ = google.auth.default(scopes=[SCOPE])
    creds.refresh(Request())
    _tok["val"] = creds.token
    _tok["exp"] = creds.expiry.timestamp() if creds.expiry else time.time() + 3000
    return creds.token


def endpoint(cfg, model):
    """(url, headers) for models/<model>:generateContent on Vertex."""
    v = cfg["vertex"]
    loc = v.get("location", "us-central1")
    host = "aiplatform.googleapis.com" if loc == "global" else f"{loc}-aiplatform.googleapis.com"
    url = (f"https://{host}/v1/projects/{v['project']}/locations/{loc}/publishers/google/models/{model}:generateContent")
    return url, {"Authorization": f"Bearer {_token(cfg)}", "Content-Type": "application/json"}


def endpoints(cfg, model, keys):
    """All (label, url, headers) to try for a Gemini call: Vertex first (if configured), then each API key."""
    out = []
    if enabled(cfg):
        try:
            url, h = endpoint(cfg, model)
            out.append(("vertex", url, h))
        except Exception as e:   # bad key file etc.: fall through to API keys
            out.append(("vertex-error:" + str(e)[:80], None, None))
    for k in keys:
        out.append((k, f"https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent",
                    {"x-goog-api-key": k, "Content-Type": "application/json"}))
    return out
