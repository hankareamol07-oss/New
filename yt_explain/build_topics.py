#!/usr/bin/env python3
"""Project B (topic explanation videos): one unit per 2026-27 tachan topic, std 1-8.

For every topic it writes a NotebookLM source pack  sources/<std>/<subject>/<seq>_<topic>/
    01_textbook.txt        chapter text from the current book OCR
    02_swadhyay.md         textbook exercise questions (+ known answers)
    03_practice.md         typed/AI practice set (if present in the bank)
    04_outcome.md          learning outcome + suggested activity from the tachan
and registers the unit in explain.db (status pending) + data/topics.json.
Poems (कविता / गीत / poem in the topic name or a verse layout in the text) are flagged is_poem=1 -> musical treatment.
"""
import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from yt_common import State, canonical, load_books, load_tachan, ocr_text, topic_key  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
TYPED = os.environ.get("TYPED_QUESTIONS", os.path.expanduser("~/books/typed_questions.json"))
POEM = re.compile(r"कविता|गीत|गाणे|poem|song|rhyme|प्रार्थना|पोवाडा|अभंग|ओवी", re.I)


def slug(s):
    return re.sub(r"[^\w\u0900-\u097F]+", "_", s).strip("_")[:40]


def looks_like_verse(text):
    lines = [l.strip() for l in text.splitlines() if l.strip()]
    if len(lines) < 12:
        return False
    short = sum(1 for l in lines[:60] if 8 <= len(l) <= 45 and not l.endswith(("।", ".", "?")))
    return short / max(1, min(60, len(lines))) > 0.6


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--stds", default="1,2,3,4,5,6,7,8")
    ap.add_argument("--lang", default="mr")
    a = ap.parse_args()
    stds = [int(x) for x in a.stds.split(",")]
    books, questions = load_books()
    tachan = load_tachan()
    typed = json.load(open(TYPED, encoding="utf-8"))["questions"] if os.path.exists(TYPED) else []
    by_ch = {}
    for q in questions:
        by_ch.setdefault((q["book_id"], q["chapter_no"]), []).append(q)
    typed_by = {}
    for q in typed:
        typed_by.setdefault((q["book_id"], q.get("chapter_no")), []).append(q)

    # topic -> book chapter (prefer requested medium)
    chap_of = {}
    for b in sorted(books, key=lambda b: b["lang"] != a.lang):
        if b["std"] not in stds:
            continue
        for c in b.get("chapters", []):
            can = canonical(b, c, tachan)
            if can and can[0] not in chap_of:
                chap_of[can[0]] = (b, c, can)

    st = State(os.path.join(HERE, "explain.db"))
    units = []
    for (std, sub), topics in tachan.items():
        if std not in stds:
            continue
        for name, v in topics.items():
            key = topic_key(std, sub, v["seq"])
            b, c, can = chap_of.get(key, (None, None, None))
            d = os.path.join(HERE, "sources", str(std), slug(sub), f"{v['seq']:02d}_{slug(name)}")
            os.makedirs(d, exist_ok=True)
            text = ocr_text(b, c, cap=60000) if b else ""
            open(os.path.join(d, "01_textbook.txt"), "w", encoding="utf-8").write(text or "(no textbook chapter - activity/skill topic)\n")
            qs = by_ch.get((b["book_id"], c["no"]), []) if b else []
            with open(os.path.join(d, "02_swadhyay.md"), "w", encoding="utf-8") as f:
                f.write(f"# {name} — स्वाध्याय\n\n")
                for q in qs:
                    f.write(f"- ({q.get('block') or 'स्वाध्याय'}; {q.get('qtype')}) {q.get('instruction', '')} {q['text']}")
                    if q.get("options"):
                        f.write("  पर्याय: " + " / ".join(q["options"]))
                    if q.get("answer"):
                        f.write(f"  → {q['answer']}")
                    f.write("\n")
            tq = typed_by.get((b["book_id"], c["no"]), []) if b else []
            with open(os.path.join(d, "03_practice.md"), "w", encoding="utf-8") as f:
                f.write(f"# {name} — सराव प्रश्नसंच\n\n")
                for q in tq:
                    f.write(f"- ({q.get('qtype')}) {q.get('text', '')}" + (f"  → {q['answer']}" if q.get("answer") else "") + "\n")
            with open(os.path.join(d, "04_outcome.md"), "w", encoding="utf-8") as f:
                f.write(f"# {name}\n\nइयत्ता {std} · {sub} · घटक {v['seq']}\n\n## अध्ययन निष्पत्ती\n{v['lo']}\n\n## सुचवलेली कृती\n{v['act']}\n")
            is_poem = bool(POEM.search(name)) or (sub in ("मराठी", "हिंदी", "इंग्लिश", "इंग्रजी") and looks_like_verse(text))
            u = {"unit_id": key.replace("|", "_"), "topic_key": key, "std": std, "subject": b["subject"] if b else sub,
                 "lang": b["lang"] if b else a.lang, "book_id": b["book_id"] if b else None, "chapter_no": c["no"] if c else v["seq"],
                 "chapter_title": name, "block": "poem" if is_poem else "topic", "title": name,
                 "meta": {"tachan_subject": sub, "tachan_seq": v["seq"], "learning_outcome": v["lo"], "activity": v["act"],
                          "is_poem": is_poem, "sources_dir": os.path.relpath(d, HERE), "has_textbook": bool(b),
                          "official_id": b.get("official_id") if b else None, "pages": [c.get("start_page"), c.get("end_page")] if c else None}}
            st.upsert_unit(u, qs)
            units.append(u)
    st.commit()
    os.makedirs(os.path.join(HERE, "data"), exist_ok=True)
    json.dump({"project": "yt_explain", "units": units}, open(os.path.join(HERE, "data", "topics.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    n_book = sum(1 for u in units if u["meta"]["has_textbook"])
    n_poem = sum(1 for u in units if u["meta"]["is_poem"])
    print(f"topics {len(units)}  with textbook chapter {n_book}  poems {n_poem}")
    for std in stds:
        us = [u for u in units if u["std"] == std]
        print(f"  std {std}: {len(us)} topics, {sum(1 for u in us if u['meta']['has_textbook'])} with chapter, {sum(1 for u in us if u['meta']['is_poem'])} poems")


if __name__ == "__main__":
    main()
