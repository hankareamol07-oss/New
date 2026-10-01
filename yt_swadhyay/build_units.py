#!/usr/bin/env python3
"""Project A (स्वाध्याय videos): build data/units.json + swadhyay.db from the 2026 textbook bank.

One unit = one video:
  * language / science / social books : the chapter's whole स्वाध्याय (Exercise) block
  * Maths                              : every सरावसंच / Practice Set (1.1, 1.2, ...) is its own unit
Only textbook exercise questions (source=book); typed/AI questions, उपक्रम/कृती/प्रकल्प activities and
figure-only items are excluded. Standards: 1, 2, 3, 4, 6 (cfg "stds").
"""
import argparse
import json
import os
import re
import sys
from collections import OrderedDict

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from yt_common import State, canonical, load_books, load_tachan  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
STDS = [1, 2, 3, 4, 6]
ACTIVITY = re.compile(r"उपक्रम|कृती|प्रकल्प|Activity|Project", re.I)
SET_RX = re.compile(r"(सराव\s*संच|सरावसंच|Practice\s*Set|Exercise)\s*([0-9०-९]+(?:\.[0-9०-९]+)?)", re.I)
DEV = str.maketrans("०१२३४५६७८९", "0123456789")


def block_label(book, block):
    """Normalized unit label inside a chapter; None = not an exercise block."""
    b = (block or "").strip()
    if ACTIVITY.search(b):
        return None
    m = SET_RX.search(b)
    if m:
        kind = "Practice Set" if book["lang"] == "en" else "सरावसंच"
        return f"{kind} {m.group(2).translate(DEV)}"
    if book["subject"] == "Maths" and not b:
        return "Practice Set" if book["lang"] == "en" else "सरावसंच"
    return "Exercise" if book["lang"] == "en" else "स्वाध्याय"


def build(stds, min_questions):
    books, questions = load_books()
    tachan = load_tachan()
    units = OrderedDict()
    for q in questions:
        if q.get("source", "book") != "book" or q.get("needs_figure") or not q.get("text"):
            continue
        book = next((b for b in books if b["book_id"] == q["book_id"]), None)
        if not book or book["std"] not in stds:
            continue
        ch = next((c for c in book.get("chapters", []) if c["no"] == q["chapter_no"]), None)
        if not ch:
            continue
        label = block_label(book, q.get("block"))
        if not label:
            continue
        uid = f"b{book['book_id']}_c{ch['no']:02d}_{re.sub(r'[^0-9A-Za-z.]+', '', label) or 'ex'}"
        u = units.get(uid)
        if not u:
            can = canonical(book, ch, tachan)
            u = units[uid] = {
                "unit_id": uid, "topic_key": can[0] if can else None, "std": book["std"], "subject": book["subject"],
                "lang": book["lang"], "book_id": book["book_id"], "chapter_no": ch["no"], "chapter_title": ch["title"],
                "block": label, "title": f"{ch['no']}. {ch['title']} | {label}",
                "meta": {"tachan_subject": can[1] if can else None, "tachan_seq": can[2] if can else None,
                         "tachan_topic": can[3] if can else None, "learning_outcome": (can[4]["lo"] if can else ""),
                         "official_id": book.get("official_id"), "source": book.get("source"), "edition": book.get("edition"),
                         "pages": [ch.get("start_page"), ch.get("end_page")]},
                "questions": [],
            }
        u["questions"].append(q)
    out = [u for u in units.values() if len(u["questions"]) >= min_questions]
    for u in out:
        u["questions"].sort(key=lambda q: (q.get("page") or 0, q.get("item_no") or 0, q.get("id") or 0))
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--stds", default=",".join(map(str, STDS)))
    ap.add_argument("--min", type=int, default=3)
    a = ap.parse_args()
    stds = [int(x) for x in a.stds.split(",") if x.strip()]
    units = build(stds, a.min)
    os.makedirs(os.path.join(HERE, "data"), exist_ok=True)
    with open(os.path.join(HERE, "data", "units.json"), "w", encoding="utf-8") as f:
        json.dump({"project": "yt_swadhyay", "stds": stds, "units": units}, f, ensure_ascii=False, indent=1)
    st = State(os.path.join(HERE, "swadhyay.db"))
    for u in units:
        st.upsert_unit(u, u["questions"])
    st.commit()
    by = {}
    for u in units:
        by.setdefault((u["std"], u["subject"], u["lang"]), []).append(u)
    for k in sorted(by):
        sets = sum(1 for u in by[k] if re.search(r"\d", u["block"]))
        print(f"std {k[0]} {k[1]:<10} {k[2]}  units {len(by[k]):3d}  (numbered sets {sets})  questions {sum(u['n_questions'] if 'n_questions' in u else len(u['questions']) for u in by[k])}")
    print(f"total units {len(units)}, with tachan topic {sum(1 for u in units if u['topic_key'])}")


if __name__ == "__main__":
    main()
