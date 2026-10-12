#!/usr/bin/env python3
"""GUI / .exe entry: theme (or auto from trends) -> Marathi motivation Short (+ YouTube). Uses motivation.db like auto.py."""
import auto
from yt_common.gui import Adapter, main

cfg = auto.load_cfg()
db = auto.open_db()


def run(row, upload, log):
    theme = (row.get("theme") or "").strip()
    n = max(1, int(row.get("n") or 1))
    auto.research(cfg, db, log)
    units = [auto.add_unit(db, theme, "manual")] if theme else [auto.add_unit(db, t, "trend") for t in auto.propose_themes(cfg, db, n, log)]
    meta = {}
    for u in units:
        log(f"=== #{u['unit_id']}  {u['theme']}")
        try:
            meta = auto.run_unit(cfg, db, u, None if upload else False, log=log)
            auto.mark(db, u["unit_id"], "done", meta=meta)
        except Exception as e:
            auto.mark(db, u["unit_id"], "failed", error=str(e)[:500])
            raise
    return meta


if __name__ == "__main__":
    main(Adapter("मराठी Motivation Shorts", [], lambda: [], run,
                 extras=[("विषय (रिकामे = trending वरून आपोआप)", "theme", ""), ("किती Shorts", "n", 1)],
                 upload_default=cfg["youtube"]["enabled"]), cfg["out_dir"])
