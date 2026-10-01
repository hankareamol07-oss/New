#!/usr/bin/env python3
"""Replace the Tesseract chapter text in sources/*/01_textbook.txt with the Sarvam Vision transcription.

Reads books/socr/<book>/<page>.md (see books/sarvam_ocr.py), finds the real first page of every chapter
(the page carrying the chapter heading, searched around the catalogue start page), cleans the markdown
(image placeholders + AI picture captions, page numbers, footnote marks) and writes
    01_textbook.txt        clean chapter text (pages separated by a blank line)
    01_textbook.pages.json {"pdf_pages": [first, last], "ocr": "sarvam"}
For every rewritten unit the stale LLM parts in unit_text are dropped (they are rebuilt from the clean
text on next use) and meta.pages is updated.  Usage: python3 rebuild_sources.py [--dry] [--std 6] [--book 37]
"""
import argparse
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from yt_common import State, load_books  # noqa: E402

HERE = os.path.dirname(os.path.abspath(__file__))
SOCR = os.environ.get("SOCR_DIR", os.path.expanduser("~/books/socr"))
DEV = "०१२३४५६७८९"
JUNK = re.compile(r"विनामूल्य वितरणासाठी|Free distribution|For free distribution|मुफ्त वितरण", re.I)


def norm(t):
    t = re.sub(r"^\s*[0-9०-९]+[\.\)]\s*", "", t or "")
    t = re.sub(r"\(.*?\)", "", t)
    return re.sub(r"[^\w]+", "", t.lower())


def to_int(s):
    s = s.strip().translate(str.maketrans(DEV, "0123456789"))
    return int(s) if s.isdigit() else None


def page_md(d, p):
    fp = os.path.join(d, f"{p:03d}.md")
    return open(fp, encoding="utf-8").read() if os.path.exists(fp) else None


def clean(md):
    out, skip_caption = [], False
    for line in md.splitlines():
        s = line.strip()
        if s == "[IMG]":
            skip_caption = True
            continue
        if skip_caption and s.startswith("*") and s.endswith("*") and len(s) > 2:
            skip_caption = False
            continue
        if s and not s.startswith("*"):
            skip_caption = False
        if not s or s == "---" or JUNK.search(s):
            if not s:
                out.append("")
            continue
        if to_int(s) is not None and len(s) <= 4:  # page number
            continue
        s = re.sub(r"\[\^\d+\]:?\s*", "", s)
        s = s.replace("[IMG]", "").strip()
        if s:
            out.append(s)
    txt = "\n".join(out)
    return re.sub(r"\n{3,}", "\n\n", txt).strip()


def heading_score(md, no, title):
    """How strongly the top of this page looks like the start of chapter `no` / `title`.
    Title match on a '## ' heading is the strong signal; the printed chapter number is often mis-read."""
    head = [l.strip() for l in md.splitlines()[:16] if l.strip() and not l.strip().startswith("*")]
    nt = norm(title)
    score = 0
    for l in head:
        h = l.lstrip("#").strip()
        m = re.match(r"^([0-9०-९]+)[\.\)]?\s+(.+)$", h)
        if m and to_int(m.group(1)) == no:
            h = m.group(2)
            score += 1
        elif to_int(h) == no:
            score += 1
            continue
        nh = norm(h)
        if nt and len(nh) >= 3 and (nh == nt or (nh in nt and len(nh) >= 0.6 * len(nt)) or (nt in nh and len(nt) >= 5)):
            score += 3 if l.startswith("#") else 2
    return score


def find_start(d, c, n_pages, prev_end):
    """Scan the whole book for the chapter heading; prefer the hit nearest the catalogue page, after prev_end."""
    guess = int(c.get("start_page") or 0)
    best, best_p = 0, None
    for p in range(max(1, prev_end + 1), n_pages + 1):
        md = page_md(d, p)
        if not md:
            continue
        s = heading_score(md, c["no"], c["title"])
        if s < 3:
            continue
        if best_p is None or s > best + 1 or (abs(s - best) <= 1 and abs(p - guess) < abs(best_p - guess)):
            best, best_p = s, p
    return (best_p, best) if best_p is not None else (None, 0)


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry", action="store_true")
    ap.add_argument("--std", type=int)
    ap.add_argument("--book", type=int)
    a = ap.parse_args()
    books, _ = load_books()
    by_id = {b["book_id"]: b for b in books}
    st = State(os.path.join(HERE, "explain.db"))
    rows = [dict(r) for r in st.db.execute("SELECT unit_id, std, book_id, chapter_no, title, meta_json FROM units WHERE book_id IS NOT NULL")]
    # chapter start pages per book, detected once
    starts = {}
    for bid, b in by_id.items():
        if a.book and bid != a.book:
            continue
        d = os.path.join(SOCR, os.path.splitext(b["file"])[0])
        if not os.path.isdir(d):
            continue
        n_pages = max([int(f[:3]) for f in os.listdir(d) if f.endswith(".md")] or [0])
        chs = sorted(b.get("chapters", []), key=lambda c: int(c.get("start_page") or 0))
        found, prev = {}, 0
        for c in chs:
            p, s = find_start(d, c, n_pages, prev)
            if p is None:
                p = int(c.get("start_page") or 0)
            found[c["no"]] = [p, s]
            prev = p
        # end = next chapter start - 1
        ordered = sorted(found.items(), key=lambda kv: kv[1][0])
        for i, (no, v) in enumerate(ordered):
            nxt = ordered[i + 1][1][0] - 1 if i + 1 < len(ordered) else None
            c = next(c for c in chs if c["no"] == no)
            end = nxt if nxt else int(c.get("end_page") or v[0]) + 1
            v.append(min(max(end, v[0]), n_pages))
        starts[bid] = (d, found, n_pages)
    n_ok = n_guess = n_skip = 0
    for r in rows:
        if a.std and r["std"] != a.std:
            continue
        if r["book_id"] not in starts:
            n_skip += 1
            continue
        d, found, _ = starts[r["book_id"]]
        if r["chapter_no"] not in found:
            n_skip += 1
            continue
        first, score, last = found[r["chapter_no"]]
        meta = json.loads(r["meta_json"] or "{}")
        pages = [clean(page_md(d, p) or "") for p in range(first, last + 1)]
        text = "\n\n".join(p for p in pages if p)
        if len(text) < 200:
            n_skip += 1
            print("  short text, skipped:", r["unit_id"], r["title"], first, last)
            continue
        if score < 3:
            n_guess += 1
            print(f"  heading not found, using catalogue page: std {r['std']} {r['title']} p{first}-{last}")
        else:
            n_ok += 1
        src = os.path.join(HERE, meta.get("sources_dir", ""))
        if a.dry:
            continue
        os.makedirs(src, exist_ok=True)
        old = os.path.join(src, "01_textbook.txt")
        if os.path.exists(old) and not os.path.exists(old + ".tesseract"):
            os.replace(old, old + ".tesseract")
        open(old, "w", encoding="utf-8").write(text + "\n")
        json.dump({"pdf_pages": [first, last], "ocr": "sarvam", "heading_score": score},
                  open(os.path.join(src, "01_textbook.pages.json"), "w", encoding="utf-8"), ensure_ascii=False)
        meta["pages"] = [first, last]
        meta["ocr"] = "sarvam"
        st.db.execute("UPDATE units SET meta_json=? WHERE unit_id=?", (json.dumps(meta, ensure_ascii=False), r["unit_id"]))
        st.db.execute("DELETE FROM unit_text WHERE unit_id=?", (r["unit_id"],))
    st.commit()
    print(f"rewritten: heading found {n_ok}, catalogue page used {n_guess}, skipped {n_skip}")


if __name__ == "__main__":
    main()
