#!/usr/bin/env python3
"""GUI / .exe entry: इयत्ता -> विषय -> पाठ -> स्वाध्याय/सरावसंच -> video (+ YouTube). Uses swadhyay.db like auto.py."""
import os

import auto
from yt_common import State
from yt_common.gui import Adapter, main

cfg = auto.load_cfg()
st = State(os.path.join(auto.HERE, "swadhyay.db"))


def rows():
    out = []
    for r in st.db.execute("SELECT * FROM units ORDER BY std, subject, book_id, chapter_no, block"):
        r = dict(r)
        r["chapter"] = f"{r['chapter_no']}. {r['chapter_title']}"
        r["set"] = f"{r['block']}  ({r['n_questions']} प्रश्न)"
        out.append(r)
    return out


def run(row, upload, log):
    u = dict(st.db.execute("SELECT * FROM units WHERE unit_id=?", (row["unit_id"],)).fetchone())
    try:
        meta, out_dir = auto.run_unit(cfg, st, u, None if upload else False, log=log)
        meta["out_dir"] = out_dir
        st.mark(u["unit_id"], "done", meta=meta)
        return meta
    except Exception as e:
        st.mark(u["unit_id"], "failed", error=str(e)[:500])
        raise


if __name__ == "__main__":
    main(Adapter("स्वाध्याय व्हिडिओ — इयत्ता 1-8", [("इयत्ता", "std"), ("विषय", "subject"), ("पाठ", "chapter"), ("स्वाध्याय / सरावसंच", "set")],
                 rows, run, upload_default=cfg["youtube"]["enabled"]), cfg["out_dir"])
