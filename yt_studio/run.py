#!/usr/bin/env python3
"""CLI:  python run.py list [--std 6]
        python run.py make --book 37 --chapter 1 [--source book|typed|both] [--upload] [--descript]
        python run.py make-file --file ch1.pdf --std 6 --subject Science --lang mr --title "..." [--chapter 2] [--upload]
        python run.py batch --std 6 --subject Science --lang mr [--from 1 --to 5] [--upload]"""
import argparse
import sys

from ytstudio import config, pipeline
from ytstudio.data import Bank


def main():
    ap = argparse.ArgumentParser()
    sub = ap.add_subparsers(dest="cmd", required=True)
    ls = sub.add_parser("list")
    ls.add_argument("--std", type=int)
    ls.add_argument("--book", type=int)
    mk = sub.add_parser("make")
    mk.add_argument("--book", type=int, required=True)
    mk.add_argument("--chapter", type=int, required=True)
    mk.add_argument("--source", default="both", choices=["book", "typed", "both"])
    mf = sub.add_parser("make-file")
    mf.add_argument("--file", required=True)
    mf.add_argument("--std", type=int, required=True)
    mf.add_argument("--subject", required=True)
    mf.add_argument("--lang", required=True, choices=["mr", "hi", "en"])
    mf.add_argument("--title")
    mf.add_argument("--chapter", type=int, default=0, help="chapter number for title/output folder")
    bt = sub.add_parser("batch")
    bt.add_argument("--std", type=int, required=True)
    bt.add_argument("--subject", required=True)
    bt.add_argument("--lang", required=True)
    bt.add_argument("--from", dest="frm", type=int, default=1)
    bt.add_argument("--to", type=int, default=99)
    bt.add_argument("--source", default="both")
    for p in (mk, mf, bt):
        p.add_argument("--upload", action="store_true")
        p.add_argument("--descript", action="store_true")
        p.add_argument("--seed", type=int)
    a = ap.parse_args()
    cfg = config.load()

    if a.cmd == "list":
        bank = Bank(cfg["data_dir"])
        if a.book:
            for c in bank.chapters(a.book):
                n = len(bank.chapter_questions(a.book, c["no"]))
                print(f"  ch {c['no']:>2}  {c['title']}  ({n} q)")
            return
        for b in bank.book_list(a.std):
            print(f"book {b['book_id']:>3}  std {b['std']}  {b['subject']:<15} {b['lang']}  {len(b['chapters'])} ch  {b['title']}")
        return
    kw = dict(upload=a.upload or None, use_descript=a.descript or None, seed=a.seed)
    if a.cmd == "make":
        pipeline.run_bank(cfg, a.book, a.chapter, a.source, **kw)
    elif a.cmd == "make-file":
        pipeline.run_file(cfg, a.file, a.std, a.subject, a.lang, a.title, no=a.chapter, **kw)
    else:
        bank = Bank(cfg["data_dir"])
        for b in bank.book_list(a.std, a.subject, a.lang):
            for c in bank.chapters(b["book_id"]):
                if a.frm <= c["no"] <= a.to:
                    try:
                        pipeline.run_bank(cfg, b["book_id"], c["no"], a.source, **kw)
                    except Exception as e:
                        print(f"!! chapter {c['no']} failed: {e}", file=sys.stderr)


if __name__ == "__main__":
    main()
