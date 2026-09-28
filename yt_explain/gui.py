#!/usr/bin/env python3
"""GUI / .exe entry: इयत्ता -> विषय -> घटक -> explanation video + Short (+ YouTube). Uses explain.db like auto.py."""
import json
import os

import auto
from yt_common import State
from yt_common.gui import Adapter, main

cfg = auto.load_cfg()
st = State(os.path.join(auto.HERE, "explain.db"))


def rows():
    out = []
    for r in st.db.execute("SELECT * FROM units ORDER BY std, subject, chapter_no, block"):
        r = dict(r)
        m = json.loads(r.get("meta_json") or "{}")
        r["topic"] = f"{m.get('tachan_seq', r['chapter_no'])}. {r['title']}" + (" (कविता)" if m.get("is_poem") else "")
        out.append(r)
    return out


def run(row, upload, log):
    u = dict(st.db.execute("SELECT * FROM units WHERE unit_id=?", (row["unit_id"],)).fetchone())
    try:
        meta = auto.run_unit(cfg, st, u, None if upload else False, log=log)
        st.mark(u["unit_id"], "done", meta=meta)
        return meta
    except Exception as e:
        st.mark(u["unit_id"], "failed", error=str(e)[:500])
        raise


if __name__ == "__main__":
    main(Adapter("घटक स्पष्टीकरण व्हिडिओ — इयत्ता 1-8", [("इयत्ता", "std"), ("विषय", "subject"), ("घटक", "topic")],
                 rows, run, upload_default=cfg["youtube"]["enabled"]), cfg["out_dir"])
