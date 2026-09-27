#!/usr/bin/env python3
"""One-click autopilot: walk through every chapter of the configured standards, make the video and upload/schedule it.

    python auto.py                # make + upload the next N chapters (cfg["auto"]["per_run"], default 2)
    python auto.py --dry          # only show the queue
    python auto.py --no-upload    # make videos only

Progress is remembered in auto_state.json (done / failed chapters); re-running continues where it stopped, and a chapter
whose manifest already has youtube_url is never uploaded twice. Run it daily (Task Scheduler / cron) and the channel
gets one scheduled video per day (cfg["youtube"]["schedule"])."""
import argparse
import importlib.util
import json
import os
import shutil
import subprocess
import sys
import time
import traceback

ROOT = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, "frozen", False) else __file__))
STATE = os.path.join(ROOT, "auto_state.json")
MODULES = {"PIL": "Pillow", "edge_tts": "edge-tts", "requests": "requests", "googleapiclient": "google-api-python-client",
           "google_auth_oauthlib": "google-auth-oauthlib"}


def preflight():
    missing = [pkg for mod, pkg in MODULES.items() if importlib.util.find_spec(mod) is None]
    if missing:
        print("installing missing packages:", " ".join(missing))
        subprocess.run([sys.executable, "-m", "pip", "install", "-q", "-r", os.path.join(ROOT, "requirements.txt")], check=True)
    from ytstudio import config
    cfg = config.load()
    ff = cfg["ffmpeg"]
    if not (shutil.which(ff) or os.path.exists(ff)):
        for cand in ("C:/ffmpeg/bin/ffmpeg.exe", "ffmpeg", os.path.join(ROOT, "ffmpeg", "bin", "ffmpeg.exe"), os.path.join(ROOT, "ffmpeg.exe")):
            if os.path.exists(cand) or shutil.which(cand):
                cfg["ffmpeg"] = ff = cand
                break
        else:
            sys.exit(f"ffmpeg not found ({ff}). Unzip ffmpeg to C:\\ffmpeg\\bin or set \"ffmpeg\" in config.json (README §1).")
    d, base = os.path.split(ff)
    probe = os.path.join(d, base.replace("ffmpeg", "ffprobe"))
    if not (shutil.which(probe) or os.path.exists(probe)):
        sys.exit(f"ffprobe not found next to ffmpeg ({probe}).")
    print(f"ffmpeg: {ff}")
    return cfg


def load_state():
    if os.path.exists(STATE):
        with open(STATE, encoding="utf-8") as f:
            return json.load(f)
    return {"done": {}, "failed": {}}


def save_state(s):
    with open(STATE, "w", encoding="utf-8") as f:
        json.dump(s, f, ensure_ascii=False, indent=1)


def queue(cfg, bank):
    a = cfg["auto"]
    out = []
    for std in a["stds"]:
        for b in bank.book_list(std):
            if a["langs"] and b["lang"] not in a["langs"]:
                continue
            if a["subjects"] and b["subject"] not in a["subjects"]:
                continue
            for c in bank.chapters(b["book_id"]):
                n = len(bank.chapter_questions(b["book_id"], c["no"], a["source"]))
                if n >= a["min_questions"]:
                    out.append((b, c, n))
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry", action="store_true")
    ap.add_argument("--no-upload", action="store_true")
    ap.add_argument("--count", type=int)
    args = ap.parse_args()
    cfg = preflight()
    from ytstudio import pipeline
    from ytstudio.data import Bank
    bank = Bank(cfg["data_dir"])
    state = load_state()
    q = queue(cfg, bank)
    upload = False if args.no_upload else True

    def is_done(key):
        d = state["done"].get(key)
        return bool(d) and (not upload or d.get("youtube_url"))

    todo = [(b, c, n) for b, c, n in q if not is_done(f"{b['book_id']}:{c['no']}")]
    print(f"queue: {len(q)} chapters, {len(q) - len(todo)} done, {len(todo)} remaining")
    if args.dry:
        for b, c, n in todo[:40]:
            print(f"  std {b['std']} {b['subject']:<15} {b['lang']}  ch {c['no']:>2}  {c['title']}  ({n} q)")
        return
    if upload and not os.path.exists(cfg["youtube"]["client_secret"]):
        print(f"\nclient_secret.json not found at {cfg['youtube']['client_secret']} — put it there (README §0) or run with --no-upload.")
        return 2
    per_run = args.count or cfg["auto"]["per_run"]
    made = 0
    for b, c, n in todo:
        if made >= per_run:
            break
        key = f"{b['book_id']}:{c['no']}"
        if state["failed"].get(key, 0) >= cfg["auto"]["max_retries"]:
            continue
        print(f"\n=== std {b['std']} {b['subject']} ({b['lang']})  ch {c['no']} {c['title']} ===")
        try:
            meta = pipeline.run_bank(cfg, b["book_id"], c["no"], cfg["auto"]["source"], upload=upload)
            state["done"][key] = {"title": meta["title"], "video": meta["video"], "youtube_url": meta.get("youtube_url"),
                                  "short_url": meta.get("short_url"), "at": time.strftime("%Y-%m-%d %H:%M")}
            made += 1
            state["failed"] = {}
        except Exception as e:
            traceback.print_exc()
            state["failed"][key] = state["failed"].get(key, 0) + 1
            print(f"FAILED ({state['failed'][key]}/{cfg['auto']['max_retries']}): {e}")
            if state["failed"][key] >= cfg["auto"]["max_retries"]:
                print("  -> will be skipped until another chapter succeeds (or clear 'failed' in auto_state.json)")
        save_state(state)
        time.sleep(cfg["auto"]["pause_sec"])
    print(f"\n{made} video(s) this run; {len(todo) - made} remaining. Log: auto_state.json")
    return 0 if made or not todo else 1


if __name__ == "__main__":
    sys.exit(main())
