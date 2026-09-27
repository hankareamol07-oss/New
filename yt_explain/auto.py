#!/usr/bin/env python3
"""Project B autopilot: next N pending topics -> explanation video -> YouTube.
  python auto.py --list | --unit 6_सामान्य_विज्ञान_3 --no-upload | --n 2
"""
import argparse
import json
import os
import sys
import time
import traceback

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from yt_common import State  # noqa: E402
from ytstudio import config as ytconfig  # noqa: E402
from ytstudio.pipeline import _slug  # noqa: E402

import explain  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))


def load_cfg():
    cfg = ytconfig.load(os.path.join(HERE, "config.json"))
    raw = json.load(open(cfg["_path"], encoding="utf-8")) if os.path.exists(cfg["_path"]) else {}
    for k in ("data_dir", "out_dir"):  # relative paths are relative to THIS project, not yt_studio
        v = raw.get(k) or ("data" if k == "data_dir" else "output")
        cfg[k] = v if os.path.isabs(v) else os.path.join(HERE, v)
    cfg.setdefault("stds", [1, 2, 3, 4, 5, 6, 7, 8])
    return cfg


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--unit")
    ap.add_argument("--n", type=int)
    ap.add_argument("--no-upload", action="store_true")
    ap.add_argument("--list", action="store_true")
    ap.add_argument("--poems", action="store_true", help="only poem topics")
    a = ap.parse_args()
    cfg = load_cfg()
    st = State(os.path.join(HERE, "explain.db"))
    if a.list:
        for u in st.pending(2000, cfg["stds"], 99):
            print(u["unit_id"], u["std"], u["subject"], u["block"], u["title"], u["status"])
        return
    if a.unit:
        units = [dict(r) for r in st.db.execute("SELECT * FROM units WHERE unit_id=?", (a.unit,))]
    else:
        units = st.pending(500, cfg["stds"], cfg["auto"]["max_retries"])
        if a.poems:
            units = [u for u in units if u["block"] == "poem"]
        units = units[: a.n or cfg["auto"]["per_run"]]
    if not units:
        print("nothing pending")
        return
    upload = False if a.no_upload else None
    for u in units:
        meta = json.loads(u.get("meta_json") or "{}")
        print(f"=== {u['unit_id']}  std {u['std']} {u['subject']}  {u['title']}  {'(poem)' if meta.get('is_poem') else ''}")
        try:
            src = os.path.join(HERE, meta.get("sources_dir", ""))
            text = open(os.path.join(src, "01_textbook.txt"), encoding="utf-8").read() if os.path.isdir(src) else ""
            out_dir = os.path.join(cfg["out_dir"], f"std{u['std']}_{_slug(meta.get('tachan_subject', u['subject']))}_{meta.get('tachan_seq', 0):02d}_{_slug(u['title'])}")
            m = explain.run(cfg, u, meta, text, st.questions_of(u["unit_id"]), out_dir, upload=upload)
            st.mark(u["unit_id"], "done", meta=m)
        except Exception as e:
            traceback.print_exc()
            st.mark(u["unit_id"], "failed", error=str(e)[:500])
        time.sleep(cfg["auto"].get("pause_sec", 5))


if __name__ == "__main__":
    main()
