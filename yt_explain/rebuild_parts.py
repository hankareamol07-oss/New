#!/usr/bin/env python3
"""Build the unit_text parts for every textbook-backed unit that has none yet (after rebuild_sources.py).
Resumable; usage: rebuild_parts.py [--std N] [--workers 4] [--limit N]"""
import argparse, json, os, sys, threading, time
from concurrent.futures import ThreadPoolExecutor

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "yt_studio"))
sys.path.insert(0, HERE)
import auto, figures, textparts  # noqa: E402

lock = threading.Lock()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--std", type=int)
    ap.add_argument("--workers", type=int, default=4)
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--order", help="llm order override, e.g. gemini,groq")
    a = ap.parse_args()
    cfg = auto.load_cfg()
    if a.order:
        cfg["llm_order"] = a.order.split(",")
    st = auto.open_state(cfg)
    rows = [dict(r) for r in st.db.execute(
        "SELECT * FROM units WHERE book_id IS NOT NULL AND unit_id NOT IN (SELECT DISTINCT unit_id FROM unit_text) ORDER BY std, subject, chapter_no")]
    todo = []
    for u in rows:
        if a.std and u["std"] != a.std:
            continue
        meta = json.loads(u.get("meta_json") or "{}")
        if meta.get("ocr") and auto.raw_text(u, meta).strip():
            todo.append((u, meta))
    if a.limit:
        todo = todo[:a.limit]
    print(f"{len(todo)} units need parts", flush=True)
    done = {"n": 0, "err": 0}

    def job(u, meta):
        text = auto.raw_text(u, meta)
        logs = []
        try:
            parts = textparts.build_parts(cfg, u, meta, text, log=logs.append)
            if not parts:
                raise RuntimeError("no parts returned")
            with lock:
                textparts.save_parts(st, u["unit_id"], parts)
                parts = textparts.get_parts(st, u["unit_id"])
                figures.assign_parts(st, u["unit_id"], parts)
                done["n"] += 1
                if done["n"] % 10 == 0:
                    print(f"[{time.strftime('%H:%M')}] {done}", flush=True)
        except Exception as e:
            with lock:
                done["err"] += 1
            print("ERR", u["unit_id"], u["title"], e, "|", " ".join(logs)[-300:], file=sys.stderr, flush=True)

    with ThreadPoolExecutor(a.workers) as ex:
        for u, meta in todo:
            ex.submit(job, u, meta)
    print("DONE", done, flush=True)


if __name__ == "__main__":
    main()
