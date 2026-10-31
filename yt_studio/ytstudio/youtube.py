"""YouTube Data API v3: OAuth (desktop app), upload, thumbnail, playlist, schedule. Optional n8n webhook instead."""
import datetime as dt
import json
import os

import requests

from .config import ROOT

SCOPES = ["https://www.googleapis.com/auth/youtube.upload", "https://www.googleapis.com/auth/youtube"]
TOKEN_FILE = os.path.join(ROOT, "youtube_token.json")


def service(cfg):
    from google.auth.transport.requests import Request
    from google.oauth2.credentials import Credentials
    from google_auth_oauthlib.flow import InstalledAppFlow
    from googleapiclient.discovery import build

    token_file = cfg["youtube"].get("token_file") or TOKEN_FILE
    env_token = os.environ.get("YOUTUBE_TOKEN_JSON", "").strip()   # cloud autopost: token json stored as a secret
    creds = None
    if env_token:
        creds = Credentials.from_authorized_user_info(json.loads(env_token), SCOPES)
    elif os.path.exists(token_file):
        creds = Credentials.from_authorized_user_file(token_file, SCOPES)
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        elif env_token or os.environ.get("CI"):
            raise RuntimeError("YouTube login is missing or expired - run GET_YOUTUBE_TOKEN.bat again and update the "
                               "YOUTUBE_TOKEN_JSON secret")
        else:
            cs = cfg["youtube"]["client_secret"]
            if not os.path.exists(cs):
                raise FileNotFoundError(f"client_secret.json not found: {cs} - copy your Google OAuth client file there")
            flow = InstalledAppFlow.from_client_secrets_file(cs, SCOPES)
            creds = flow.run_local_server(port=0, prompt="consent")
        if not env_token:
            with open(token_file, "w") as f:
                f.write(creds.to_json())
    return build("youtube", "v3", credentials=creds)


def _slots(s):
    times = s.get("times") or [f"{s['hour']:02d}:{s['minute']:02d}"]
    return sorted(tuple(int(x) for x in t.split(":")[:2]) for t in times)


def next_publish_time(cfg, state_file):
    """Next free slot: one of schedule.times (or hour:minute) each day, in schedule.tz (default: PC time zone);
    a single daily slot repeats every_days. The last slot used is remembered in state_file."""
    s = cfg["youtube"]["schedule"]
    tz = None
    if s.get("tz"):
        from zoneinfo import ZoneInfo
        tz = ZoneInfo(s["tz"])
    now = dt.datetime.now(tz) if tz else dt.datetime.now().astimezone()
    last = None
    if os.path.exists(state_file):
        with open(state_file) as f:
            v = json.load(f).get("last")
        if v:
            last = dt.datetime.fromisoformat(v).astimezone(now.tzinfo)
    slots = _slots(s)
    step = s.get("every_days", 1) if len(slots) == 1 else 1
    floor = now + dt.timedelta(minutes=20)
    day = floor.date()
    cand = None
    while cand is None:
        for h, m in slots:
            c = dt.datetime.combine(day, dt.time(h, m), now.tzinfo)
            if c > floor and (last is None or (c >= last + dt.timedelta(days=step, minutes=-1) if step > 1 else c > last)):
                cand = c
                break
        day += dt.timedelta(days=1)
    with open(state_file, "w") as f:
        json.dump({"last": cand.isoformat()}, f)
    return cand


def ensure_playlist(yt, title, description=""):
    req = yt.playlists().list(part="snippet", mine=True, maxResults=50)
    while req:
        res = req.execute()
        for it in res.get("items", []):
            if it["snippet"]["title"].strip().lower() == title.strip().lower():
                return it["id"]
        req = yt.playlists().list_next(req, res)
    body = {"snippet": {"title": title, "description": description}, "status": {"privacyStatus": "public"}}
    return yt.playlists().insert(part="snippet,status", body=body).execute()["id"]


def upload(cfg, mp4, meta, thumb=None, is_short=False, log=print):
    from googleapiclient.http import MediaFileUpload

    yt = service(cfg)
    y = cfg["youtube"]
    desc = meta["description"]
    if not is_short and meta.get("chapters_text"):
        desc += "\n\n⏱ Timestamps:\n" + meta["chapters_text"]
    desc += "\n\n" + " ".join(meta.get("hashtags", []))
    body = {
        "snippet": {
            "title": (meta["short_title"] if is_short else meta["title"])[:100],
            "description": desc[:4900],
            "tags": [t[:30] for t in meta.get("tags", [])][:30],
            "categoryId": y["category_id"],
            "defaultLanguage": y["default_language"],
            "defaultAudioLanguage": meta.get("lang", y["default_language"]),
        },
        "status": {"privacyStatus": y["privacy"], "selfDeclaredMadeForKids": y["made_for_kids"]},
    }
    if y["schedule"]["enabled"]:
        if is_short and meta.get("publish_at"):        # the Short goes live together with its video
            when = dt.datetime.fromisoformat(meta["publish_at"])
        else:
            when = next_publish_time(cfg, os.path.join(ROOT, "schedule_state.json"))
            meta["publish_at"] = when.isoformat()
        body["status"]["privacyStatus"] = "private"
        body["status"]["publishAt"] = when.astimezone(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
        log(f"[youtube] scheduled for {when}")
    log(f"[youtube] uploading {os.path.basename(mp4)} ...")
    media = MediaFileUpload(mp4, chunksize=8 * 1024 * 1024, resumable=True, mimetype="video/mp4")
    req = yt.videos().insert(part="snippet,status", body=body, media_body=media)
    res = None
    while res is None:
        status, res = req.next_chunk()
        if status:
            log(f"  {int(status.progress() * 100)}%")
    vid = res["id"]
    if thumb and not is_short and os.path.exists(thumb):
        yt.thumbnails().set(videoId=vid, media_body=MediaFileUpload(thumb, mimetype="image/png")).execute()
    if meta.get("playlist") and not is_short:
        pid = ensure_playlist(yt, meta["playlist"], f"{cfg['channel_name']} — {meta['playlist']}")
        yt.playlistItems().insert(part="snippet", body={"snippet": {"playlistId": pid, "resourceId": {"kind": "youtube#video", "videoId": vid}}}).execute()
    url = f"https://youtu.be/{vid}"
    log(f"[youtube] {url}")
    return url


def notify_n8n(cfg, payload, log=print):
    if not cfg.get("n8n_webhook"):
        return None
    r = requests.post(cfg["n8n_webhook"], json=payload, timeout=60)
    log(f"[n8n] webhook -> {r.status_code}")
    return r.status_code
