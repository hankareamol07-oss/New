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

    creds = None
    if os.path.exists(TOKEN_FILE):
        creds = Credentials.from_authorized_user_file(TOKEN_FILE, SCOPES)
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            flow = InstalledAppFlow.from_client_secrets_file(cfg["youtube"]["client_secret"], SCOPES)
            creds = flow.run_local_server(port=0)
        with open(TOKEN_FILE, "w") as f:
            f.write(creds.to_json())
    return build("youtube", "v3", credentials=creds)


def next_publish_time(cfg, state_file):
    """Consistent schedule: next free slot at HH:MM local, every N days, remembered in state_file."""
    s = cfg["youtube"]["schedule"]
    last = None
    if os.path.exists(state_file):
        with open(state_file) as f:
            last = dt.datetime.fromisoformat(json.load(f).get("last", ""))
    now = dt.datetime.now().astimezone()
    cand = now.replace(hour=s["hour"], minute=s["minute"], second=0, microsecond=0)
    if cand <= now:
        cand += dt.timedelta(days=1)
    if last and cand <= last:
        cand = last + dt.timedelta(days=s["every_days"])
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
        when = next_publish_time(cfg, os.path.join(ROOT, "schedule_state.json"))
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
