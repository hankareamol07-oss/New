#!/usr/bin/env python3
"""Project A autopilot: next N pending स्वाध्याय / सरावसंच units -> video (+Short) -> YouTube.

Uses the yt_studio engine (script/slides/TTS/video/upload) with this project's own config.json, data and swadhyay.db.
All questions of the exercise set are covered in textbook order (no sampling, no typed/AI questions).
  python auto.py                 # per_run units from config
  python auto.py --unit b35_c01_1.1 --no-upload
  python auto.py --list          # show pending units
"""
import argparse
import json
import os
import sys
import time
import traceback

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from yt_common import State, ocr_text  # noqa: E402  (also puts yt_studio on sys.path)
from ytstudio import config as ytconfig, pipeline, script as scriptmod  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
KIND = ("textbook स्वाध्याय / सरावसंच solutions: solve EVERY given question in the given order, one segment per question, "
        "no extra invented questions; for Maths show the full working step by step")


def load_cfg():
    cfg = ytconfig.load(os.path.join(HERE, "config.json"))
    raw = json.load(open(cfg["_path"], encoding="utf-8")) if os.path.exists(cfg["_path"]) else {}
    for k in ("data_dir", "out_dir"):  # relative paths are relative to THIS project, not yt_studio
        v = raw.get(k) or ("data" if k == "data_dir" else "output")
        cfg[k] = v if os.path.isabs(v) else os.path.join(HERE, v)
    cfg.setdefault("stds", [1, 2, 3, 4, 6])
    cfg["questions_per_video"] = 999  # every question of the set
    return cfg


def _keep_order(qs, n, seed=None):
    return [q for q in qs if q.get("text")][:n]


def run_unit(cfg, st, u, upload):
    qs = st.questions_of(u["unit_id"])
    book = {"book_id": u["book_id"], "std": u["std"], "subject": u["subject"], "lang": u["lang"], "title": "",
            "official_id": (u.get("meta") or {}).get("official_id")}
    chapter = {"no": u["chapter_no"], "title": f"{u['chapter_title']} | {u['block']}", "start_page": 0, "end_page": 0}
    text = ""
    try:
        meta = json.loads(u.get("meta_json") or "{}")
        book["official_id"] = meta.get("official_id")
        if meta.get("pages") and meta["pages"][0]:
            chapter["start_page"], chapter["end_page"] = meta["pages"]
            text = ocr_text(book, chapter)
    except Exception:
        pass
    out_dir = os.path.join(cfg["out_dir"], f"std{u['std']}_{u['subject']}_{u['lang']}_{u['chapter_no']:02d}_{pipeline._slug(u['chapter_title'])}_{pipeline._slug(u['block'])}")
    scriptmod.pick_questions = _keep_order
    return pipeline.run(cfg, book, chapter, qs, text, out_dir=out_dir, upload=upload), out_dir


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--unit")
    ap.add_argument("--n", type=int)
    ap.add_argument("--no-upload", action="store_true")
    ap.add_argument("--list", action="store_true")
    a = ap.parse_args()
    cfg = load_cfg()
    st = State(os.path.join(HERE, "swadhyay.db"))
    if a.list:
        for u in st.pending(500, cfg["stds"], 99):
            print(u["unit_id"], u["std"], u["subject"], u["lang"], u["title"], u["n_questions"], u["status"])
        return
    if a.unit:
        units = [dict(r) for r in st.db.execute("SELECT * FROM units WHERE unit_id=?", (a.unit,))]
    else:
        units = st.pending(a.n or cfg["auto"]["per_run"], cfg["stds"], cfg["auto"]["max_retries"])
    if not units:
        print("nothing pending")
        return
    upload = False if a.no_upload else None
    for u in units:
        print(f"=== {u['unit_id']}  std {u['std']} {u['subject']} {u['lang']}  {u['title']}  ({u['n_questions']} q)")
        try:
            meta, out_dir = run_unit(cfg, st, u, upload)
            meta["out_dir"] = out_dir
            st.mark(u["unit_id"], "done", meta=meta)
        except Exception as e:
            traceback.print_exc()
            st.mark(u["unit_id"], "failed", error=str(e)[:500])
        time.sleep(cfg["auto"].get("pause_sec", 5))


if __name__ == "__main__":
    main()
