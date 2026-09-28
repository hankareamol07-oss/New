#!/usr/bin/env python3
"""Project C autopilot: trending Marathi motivation research -> N original Shorts -> YouTube (Shorts).
  python auto.py --refresh            # fetch trending titles into motivation.db
  python auto.py --n 2 --no-upload    # make 2 Shorts locally
  python auto.py --theme "परीक्षेची भीती" --no-upload
  python auto.py --list               # themes / status
Own state DB: motivation.db (tables trends, units). Themes come from the LLM (from trend research) or --theme / config "themes".
"""
import argparse
import datetime as dt
import json
import os
import sqlite3
import sys
import time
import traceback

HERE = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, "frozen", False) else __file__))
os.environ.setdefault("YTSTUDIO_ROOT", os.path.join(os.path.dirname(HERE), "yt_studio"))
sys.path.insert(0, os.path.dirname(HERE))
import yt_common  # noqa: E402,F401  (puts yt_studio on sys.path)
from ytstudio import config as ytconfig  # noqa: E402
from ytstudio.llm import chat_json  # noqa: E402

import motivate  # noqa: E402
import trends  # noqa: E402

SCHEMA = """
CREATE TABLE IF NOT EXISTS trends(video_id TEXT PRIMARY KEY, title TEXT, channel TEXT, views INTEGER, duration INTEGER,
  published TEXT, query TEXT, fetched_at TEXT);
CREATE TABLE IF NOT EXISTS units(unit_id INTEGER PRIMARY KEY AUTOINCREMENT, theme TEXT, source TEXT, status TEXT DEFAULT 'pending',
  tries INTEGER DEFAULT 0, error TEXT, out_dir TEXT, title TEXT, short_url TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
"""


def load_cfg():
    cfg = ytconfig.load(os.path.join(HERE, "config.json"))
    raw = json.load(open(cfg["_path"], encoding="utf-8")) if os.path.exists(cfg["_path"]) else {}
    v = raw.get("out_dir") or "output"
    cfg["out_dir"] = v if os.path.isabs(v) else os.path.join(HERE, v)
    cfg.setdefault("trends", {})
    return cfg


def open_db():
    db = sqlite3.connect(os.path.join(HERE, "motivation.db"))
    db.row_factory = sqlite3.Row
    db.executescript(SCHEMA)
    return db


def research(cfg, db, log=print):
    last = db.execute("SELECT MAX(fetched_at) FROM trends").fetchone()[0]
    hours = float(cfg["trends"].get("refresh_hours", 24))
    if last and dt.datetime.now() - dt.datetime.fromisoformat(last) < dt.timedelta(hours=hours):
        return
    trends.fetch(cfg, db, log)


def propose_themes(cfg, db, n, log=print):
    """Ask the LLM for n fresh themes from the trending titles; skip ones already used."""
    top = trends.top(db, 40)
    used = [r["theme"] for r in db.execute("SELECT theme FROM units ORDER BY unit_id DESC LIMIT 60")]
    titles = "\n".join(f"- {r['title']} ({r['views']} views)" for r in top) or "- (no trend data; use evergreen Marathi motivation themes)"
    sysm = ("You pick themes for original Marathi motivation Shorts. Output STRICT JSON {\"themes\": [\"...\"]}. "
            "Each theme: a short Marathi phrase (3-8 words) naming ONE concrete idea (e.g. सकाळी लवकर उठण्याची ताकद, "
            "अपयशातून शिकणे, तुलना थांबवा). Themes must be original, not copies of titles, and different from the used list.")
    user = f"Trending Marathi motivation titles now:\n{titles}\n\nAlready used themes: {used}\n\nGive {n} new themes, most viral-worthy first."
    out, _ = chat_json(cfg, sysm, user, max_tokens=800, log=log)
    return [t for t in out.get("themes", []) if isinstance(t, str) and t.strip()][:n]


def add_unit(db, theme, source):
    cur = db.execute("INSERT INTO units(theme, source) VALUES(?,?)", (theme, source))
    db.commit()
    return dict(db.execute("SELECT * FROM units WHERE unit_id=?", (cur.lastrowid,)).fetchone())


def mark(db, uid, status, error=None, meta=None):
    m = meta or {}
    db.execute("UPDATE units SET status=?, tries=tries+1, error=?, out_dir=?, title=?, short_url=?, updated_at=CURRENT_TIMESTAMP WHERE unit_id=?",
               (status, error, m.get("out_dir"), m.get("title"), m.get("short_url"), uid))
    db.commit()


def run_unit(cfg, db, u, upload, log=print):
    top = trends.top(db, 40)
    titles = [r["title"] for r in top]
    out_dir = os.path.join(cfg["out_dir"], f"{u['unit_id']:04d}_{motivate._slug(u['theme'])}")
    return motivate.run(cfg, u, titles, trends.keywords(top), out_dir, upload=upload, log=log)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--n", type=int)
    ap.add_argument("--theme")
    ap.add_argument("--no-upload", action="store_true")
    ap.add_argument("--refresh", action="store_true")
    ap.add_argument("--list", action="store_true")
    a = ap.parse_args()
    cfg = load_cfg()
    db = open_db()
    if a.refresh:
        trends.fetch(cfg, db)
        for r in trends.top(db, 20):
            print(f"{r['views']:>10}  {r['title']}  [{r['channel']}]")
        return
    if a.list:
        for r in db.execute("SELECT * FROM units ORDER BY unit_id"):
            print(r["unit_id"], r["status"], r["theme"], r["short_url"] or "")
        return
    research(cfg, db)
    upload = False if a.no_upload else None
    n = a.n or cfg["auto"]["per_run"]
    units = [dict(r) for r in db.execute("SELECT * FROM units WHERE status!='done' AND tries<? ORDER BY unit_id LIMIT ?",
                                          (cfg["auto"]["max_retries"], n))]
    if a.theme:
        units = [add_unit(db, a.theme, "manual")]
    elif len(units) < n:
        pool = list(cfg.get("themes") or [])
        used = {r["theme"] for r in db.execute("SELECT theme FROM units")}
        fresh = [t for t in pool if t not in used][: n - len(units)]
        for t in fresh:
            units.append(add_unit(db, t, "config"))
        if len(units) < n:
            for t in propose_themes(cfg, db, n - len(units)):
                units.append(add_unit(db, t, "trend"))
    for u in units:
        print(f"=== #{u['unit_id']}  {u['theme']}")
        try:
            meta = run_unit(cfg, db, u, upload)
            mark(db, u["unit_id"], "done", meta=meta)
            print(f"  -> {meta['short']}  {meta.get('short_url', '')}")
        except Exception as e:
            traceback.print_exc()
            mark(db, u["unit_id"], "failed", error=str(e)[:500])
        time.sleep(cfg["auto"].get("pause_sec", 5))


if __name__ == "__main__":
    main()
