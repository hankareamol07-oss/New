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

HERE = os.path.dirname(os.path.abspath(sys.executable if getattr(sys, "frozen", False) else __file__))
os.environ.setdefault("YTSTUDIO_ROOT", os.path.join(os.path.dirname(HERE), "yt_studio"))
sys.path.insert(0, os.path.dirname(HERE))
from yt_common import State  # noqa: E402
from ytstudio import config as ytconfig  # noqa: E402
from ytstudio.pipeline import _slug  # noqa: E402

import explain  # noqa: E402
import figures
import textparts  # noqa: E402



def load_cfg():
    cfg = ytconfig.load(os.path.join(HERE, "config.json"))
    raw = json.load(open(cfg["_path"], encoding="utf-8")) if os.path.exists(cfg["_path"]) else {}
    for k in ("data_dir", "out_dir"):  # relative paths are relative to THIS project, not yt_studio
        v = raw.get(k) or ("data" if k == "data_dir" else "output")
        cfg[k] = v if os.path.isabs(v) else os.path.join(HERE, v)
    cfg.setdefault("stds", [1, 2, 3, 4, 5, 6, 7, 8])
    kf = (cfg.get("vertex") or {}).get("key_file")
    if kf and not os.path.isabs(kf):   # service-account key next to config.json
        cfg["vertex"]["key_file"] = os.path.join(HERE, kf)
    return cfg


def open_state(cfg=None):
    """explain.db; when empty (fresh install) it is seeded from data/topics.json shipped with the project."""
    st = State(os.path.join(HERE, "explain.db"))
    textparts.ensure_table(st.db)
    figures.ensure_table(st.db)
    if st.db.execute("SELECT COUNT(*) FROM units").fetchone()[0] == 0:
        p = os.path.join((cfg or load_cfg())["data_dir"], "topics.json")
        if os.path.exists(p):
            for u in json.load(open(p, encoding="utf-8")).get("units", []):
                st.upsert_unit(u, [])
            st.commit()
    return st


def raw_text(u, meta):
    """OCR text of the chapter from the shipped source pack ('' if the topic has no textbook pages)."""
    src = os.path.join(HERE, meta.get("sources_dir", ""))
    p = os.path.join(src, "01_textbook.txt")
    return open(p, encoding="utf-8").read() if os.path.isfile(p) else ""


def unit_text(cfg, st, u, meta, log=print):
    """Text for the script: the ticked parts from explain.db (built by the LLM on first use), else the raw OCR text."""
    raw = raw_text(u, meta)
    parts = textparts.parts_or_build(cfg, st, u, meta, raw, log)
    if parts:
        sel = textparts.selected_text(parts)
        log(f"[text] {sum(1 for p in parts if p['selected'])}/{len(parts)} parts selected")
        return sel or raw
    return raw


def out_dir_of(cfg, u, meta):
    return os.path.join(cfg["out_dir"], f"std{u['std']}_{_slug(meta.get('tachan_subject', u['subject']))}_{meta.get('tachan_seq', 0):02d}_{_slug(u['title'])}")


def run_unit(cfg, st, u, upload, log=print, opts=None):
    meta = json.loads(u.get("meta_json") or "{}")
    text = unit_text(cfg, st, u, meta, log)
    figs = figures.for_script(st, meta, u["unit_id"], textparts.get_parts(st, u["unit_id"]))
    if figs:
        log(f"[figures] {len(figs)} textbook figures available")
    return explain.run(cfg, u, meta, text, st.questions_of(u["unit_id"]), out_dir_of(cfg, u, meta), upload=upload, log=log, opts=opts, figures=figs)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--unit")
    ap.add_argument("--n", type=int)
    ap.add_argument("--no-upload", action="store_true")
    ap.add_argument("--list", action="store_true")
    ap.add_argument("--poems", action="store_true", help="only poem topics")
    a = ap.parse_args()
    cfg = load_cfg()
    st = open_state(cfg)
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
            m = run_unit(cfg, st, u, upload)
            st.mark(u["unit_id"], "done", meta=m)
        except Exception as e:
            traceback.print_exc()
            st.mark(u["unit_id"], "failed", error=str(e)[:500])
        time.sleep(cfg["auto"].get("pause_sec", 5))


if __name__ == "__main__":
    main()
