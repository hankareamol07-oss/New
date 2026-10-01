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
import difflib
import functools
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
    t = re.sub(r"^\s*[0-9०-९]+(\.[0-9०-९]+)?[\.\)]?\s+", "", t or "")  # "7." / "3.7" / "९" unit prefixes
    t = re.sub(r"\(.*?\)", "", t)
    return re.sub(r"[^\w\u0900-\u097F]+", "", t.lower())  # keep Devanagari matras (not \w)


def to_int(s):
    s = s.strip().translate(str.maketrans(DEV, "0123456789"))
    return int(s) if s.isdigit() else None


@functools.lru_cache(maxsize=None)
def page_md(d, p):
    fp = os.path.join(d, f"{p:03d}.md")
    return open(fp, encoding="utf-8").read() if os.path.exists(fp) else None


IMGREF = re.compile(r"\b(the|this|in the) (image|illustration|picture)\b|^\d+\.\s*\*\*.*\?\*\*$", re.I)
ANALYSIS = re.compile(r"^#{2,4}\s*(Analysis|Description|Relevant Knowledge|Integrated Knowledge|Conclusion|Interaction|Background|Art Style|Characters?)\b.*:?$", re.I)
BOLDLIST = re.compile(r"^(\d+\.|-)\s*\*\*[A-Z][^*]{2,40}\*\*:?")
DESC = re.compile(r"^\**(The|This) (image|picture|illustration|photo|figure) (depicts|shows|features|is|displays|presents|illustrates)\b", re.I)


def clean(md):
    out, skip_caption, in_desc = [], False, False
    for line in md.splitlines():
        s = line.strip()
        # OCR-generated figure descriptions: an italic block (may span paragraphs) or "The image depicts ..."
        if in_desc:
            if s.endswith("*") or s.startswith("#") or s == "[IMG]":
                in_desc = False
            if not s.startswith("#"):
                continue
        if DESC.search(s) or ANALYSIS.search(s):
            in_desc = not s.endswith("*") or ANALYSIS.search(s) is not None
            continue
        if BOLDLIST.search(s):
            continue
        s = re.sub(r"[ \t]{3,}", " ", s)
        s = re.sub(r"([.\-_…]\s?){6,}", ".....", s)
        s = re.sub(r"(.)\1{20,}", r"\1\1\1", s)
        if IMGREF.search(s):  # image-analysis Q&A lines the OCR model sometimes emits
            continue
        if s == "[IMG]":
            skip_caption = True
            continue
        if skip_caption and s.startswith("*") and s.endswith("*") and len(s) > 2:
            skip_caption = False
            continue
        if s and not s.startswith("*"):
            skip_caption = False
        if s.startswith("```"):
            continue
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


def trim_to_heading(txt, no, title):
    """Drop the tail of the previous chapter printed above this chapter's heading on its first page."""
    lines = txt.splitlines()
    nt = norm(title)
    for i, l in enumerate(lines[:40]):
        h = l.lstrip("#").strip()
        if not l.startswith("#") and to_int(h) != no:
            continue
        m = re.match(r"^([0-9०-९]+)[\.\\)]?\s*(.*)$", h)
        body = m.group(2) if m else h
        num_ok = m is not None and to_int(m.group(1)) == no
        nh = norm(body)
        if (num_ok and (not nh or nh == nt or nh in nt or nt in nh)) or (nt and len(nh) >= 3 and (nh == nt or (nh in nt and len(nh) >= 0.6 * len(nt)) or (nt in nh and len(nt) >= 5))):
            return "\n".join(lines[i:]).strip() if i else txt
    return txt


def next_chapter_page(d, first, n_pages):
    """First page after `first` that opens a new chapter ('## <number>' then '## <title>'), e.g. the civics
    section after the last history chapter of a combined book; n_pages when there is none."""
    for p in range(first + 2, n_pages + 1):
        heads = [l.lstrip("#").strip() for l in page_md(d, p).splitlines()[:6] if l.startswith("## ")]
        if len(heads) >= 2 and to_int(heads[0]) is not None and len(heads[0]) <= 3 and len(norm(heads[1])) >= 3:
            return p - 1
    return n_pages


ANSKEY = re.compile(r"^#{0,3}\s*\**(उत्तरसूची|उत्तरे|उत्तर सूची|Answers?( Key)?|ANSWERS)\**\s*$", re.M)


def trim_answer_key(txt):
    """Drop the back-of-book answer key that follows the last chapter."""
    m = ANSKEY.search(txt, len(txt) // 5)
    return txt[: m.start()].rstrip() if m else txt


def trim_tail(txt, nxt):
    """Cut the text at the heading of the NEXT chapter (dict with no/title) when the last page already starts it."""
    if not nxt:
        return txt
    lines = txt.splitlines()
    nt = norm(nxt.get("title") or "")
    for i in range(max(1, len(lines) // 4), len(lines)):
        l = lines[i]
        if not l.startswith("## "):
            continue
        h = l.lstrip("#").strip()
        m = re.match(r"^([0-9०-९]+(?:\.[0-9०-९]+)?)[\.\)]?\s*(.*)$", h)
        body = m.group(2) if m else h
        num_ok = m is not None and to_int(m.group(1)) == nxt.get("no")
        nh = norm(body)
        if (num_ok and (not nh or nh in nt or nt in nh)) or (nt and len(nh) >= 4 and (nh == nt or (nh in nt and len(nh) >= 0.6 * len(nt)) or (nt in nh and len(nt) >= 5))):
            if not nh and i + 1 < len(lines) and lines[i + 1].startswith("## ") and norm(lines[i + 1].lstrip("#")) not in nt:
                continue
            return "\n".join(lines[:i]).strip()
    return txt


def heading_score(md, no, title):
    """How strongly the top of this page looks like the start of chapter `no` / `title`.
    Title match on a '## ' heading is the strong signal; the printed chapter number is often mis-read."""
    head = [l.strip() for l in md.splitlines()[:16] if l.strip() and not l.strip().startswith("*")]
    nt = norm(title)
    score = 0
    # titles printed over 2-3 short lines ('## All' / 'about' / 'Money'): also try the joined runs
    joined = []
    for i in range(len(head) - 1):
        for k in (2, 3):
            run = head[i:i + k]
            if len(run) == k and all(len(x) <= 24 for x in run):
                joined.append(("## " if run[0].startswith("#") else "") + " ".join(x.lstrip("#").strip() for x in run))
    for l in head + joined:
        h = l.lstrip("#").strip()
        m = re.match(r"^([0-9०-९]+(?:\.[0-9०-९]+)?)[\.\)]?\s+(.+)$", h)
        if m and "." in m.group(1):  # unit-style "3.8 Title": number carries no chapter info
            h = m.group(2)
        elif m and to_int(m.group(1)) == no:
            h = m.group(2)
            score += 1
        elif to_int(h) == no:
            score += 1
            continue
        nh = norm(h)
        if nt and len(nh) >= 3 and (nh == nt or (nh in nt and len(nh) >= 0.6 * len(nt)) or (nt in nh and len(nt) >= 5)
                                    or (len(nt) >= 6 and difflib.SequenceMatcher(None, nh[:len(nt) + 4], nt).ratio() >= 0.8)):
            score += 3 if l.startswith("#") or (l in head[:4] and (nh == nt or len(nt) >= 8)) else 2
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


def detect(d, chs, n_pages, whole):
    """Chapter start pages for one book. whole=False: scan forward from the previous chapter's start
    (catalogue order enforced by construction). whole=True: best hit anywhere, then drop the weaker of
    any out-of-order pair (handles catalogue placeholder pages). Returns {no: [page, score]}."""
    found, seen, prev = {}, set(), 0
    for c in chs:
        guess = int(c.get("start_page") or 0)
        placeholder = guess in seen  # catalogue placeholders: several chapters listed on one page
        seen.add(guess)
        p, s = find_start(d, c, n_pages, 0 if whole else prev)
        found[c["no"]] = [p if p is not None else guess, s, guess, placeholder]
        if not whole and p is not None:
            prev = p
    if whole:
        seq = [c["no"] for c in chs if not found[c["no"]][3]]
        changed = True
        while changed:
            changed = False
            for i in range(len(seq) - 1):
                a_, b_ = found[seq[i]], found[seq[i + 1]]
                if a_[0] >= b_[0] and (a_[1] or b_[1]):
                    bad = a_ if (a_[1], -abs(a_[0] - a_[2])) <= (b_[1], -abs(b_[0] - b_[2])) else b_
                    if (bad[0], bad[1]) != (bad[2], 0):
                        bad[0], bad[1] = bad[2], 0
                        changed = True
    for no in found:
        del found[no][2:]
    return found


def candidates(d, c, n_pages):
    out = []
    for p in range(1, n_pages + 1):
        md = page_md(d, p)
        if md:
            sc = heading_score(md, c["no"], c["title"])
            if sc >= 3:
                out.append((p, sc))
    return out


def detect_dp(d, chs, n_pages):
    """Pick one heading hit (or none) per chapter so that picked pages strictly increase in catalogue
    order, maximising total (score - small distance-from-catalogue-page penalty). Unpicked chapters
    fall back to their catalogue page. Returns {no: [page, score]}."""
    guesses = [int(c.get("start_page") or 0) for c in chs]
    cands = [candidates(d, c, n_pages) for c in chs]
    # dp[i] = {page: (best_total, prev_choice)} after deciding chapter i (page=0: nothing picked yet)
    NONE = 0
    dp = [{NONE: (0.0, None)}]
    for i, c in enumerate(chs):
        cur = {}
        for lp, (tot, _) in dp[-1].items():
            # skip this chapter
            if lp not in cur or tot > cur[lp][0]:
                cur[lp] = (tot, (lp, None))
            for p, sc in cands[i]:
                if p <= lp:
                    continue
                val = tot + sc - 0.08 * abs(p - guesses[i])
                if p not in cur or val > cur[p][0]:
                    cur[p] = (val, (lp, (p, sc)))
        dp.append(cur)
    # backtrack
    found = {}
    lp = max(dp[-1].items(), key=lambda kv: kv[1][0])[0]
    for i in range(len(chs) - 1, -1, -1):
        _, (prev_lp, pick) = dp[i + 1][lp]
        found[chs[i]["no"]] = [pick[0], pick[1]] if pick else [guesses[i], 0]
        lp = prev_lp
    return found


def main():


    ap = argparse.ArgumentParser()
    ap.add_argument("--dry", action="store_true")
    ap.add_argument("--verbose", action="store_true")
    ap.add_argument("--std", type=int)
    ap.add_argument("--book", type=int)
    ap.add_argument("--ocr", default="vertex", help="provenance label written to meta/pages.json")
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
        found = detect_dp(d, chs, n_pages)
        # end = next chapter start - 1
        ordered = sorted(found.items(), key=lambda kv: kv[1][0])
        for i, (no, v) in enumerate(ordered):
            nxt = ordered[i + 1][1][0] - 1 if i + 1 < len(ordered) else None
            c = next(c for c in chs if c["no"] == no)
            end = nxt if nxt else min(int(c.get("end_page") or v[0]) + 1, next_chapter_page(d, v[0], n_pages))
            v.append(min(max(end, v[0]), n_pages))
        # a catalogue-page fallback is only trusted when it lies between detected neighbours
        for i, (no, v) in enumerate(ordered):
            if v[1]:
                v.append(True)
                continue
            prev_p = max([w[0] for _, w in ordered[:i] if w[1]] or [0])
            next_p = min([w[0] for _, w in ordered[i + 1:] if w[1]] or [n_pages + 1])
            v.append(prev_p < v[0] < next_p)
        starts[bid] = (d, found, n_pages)
    n_ok = n_guess = n_skip = n_changed = 0
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
        first, score, last = found[r["chapter_no"]][:3]
        meta = json.loads(r["meta_json"] or "{}")
        pages = [clean(page_md(d, p) or "") for p in range(first, last + 1)]
        if score and pages:
            pages[0] = trim_to_heading(pages[0], r["chapter_no"], r["title"])
        text = "\n\n".join(p for p in pages if p)
        chs_b = by_id[r["book_id"]].get("chapters", [])
        text = trim_answer_key(trim_tail(text, next((c for c in chs_b if c["no"] == r["chapter_no"] + 1), None)))
        if len(text) < 60:
            n_skip += 1
            print("  short text, skipped:", r["unit_id"], r["title"], first, last)
            continue
        if score < 3 and not found[r["chapter_no"]][3]:
            n_skip += 1
            print(f"  unresolved (catalogue page outside detected neighbours), kept old source: std {r['std']} {r['title']} p{first}")
            continue
        if score < 3:
            n_guess += 1
            print(f"  heading not found, using catalogue page: std {r['std']} {r['title']} p{first}-{last}")
        else:
            n_ok += 1
            if a.verbose:
                print(f"  ok: std {r['std']} {r['title']} p{first}-{last} score {score}")
        src = os.path.join(HERE, meta.get("sources_dir", ""))
        if a.dry:
            continue
        os.makedirs(src, exist_ok=True)
        old = os.path.join(src, "01_textbook.txt")
        if os.path.exists(old) and not os.path.exists(old + ".tesseract"):
            os.replace(old, old + ".tesseract")
        unchanged = os.path.exists(old) and open(old, encoding="utf-8").read() == text + "\n"
        open(old, "w", encoding="utf-8").write(text + "\n")
        json.dump({"pdf_pages": [first, last], "ocr": a.ocr, "heading_score": score},
                  open(os.path.join(src, "01_textbook.pages.json"), "w", encoding="utf-8"), ensure_ascii=False)
        meta["pages"] = [first, last]
        meta["ocr"] = a.ocr
        st.db.execute("UPDATE units SET meta_json=? WHERE unit_id=?", (json.dumps(meta, ensure_ascii=False), r["unit_id"]))
        if not unchanged:
            n_changed += 1
            st.db.execute("DELETE FROM unit_text WHERE unit_id=?", (r["unit_id"],))
    st.commit()
    print(f"rewritten: heading found {n_ok}, catalogue page used {n_guess}, skipped {n_skip}, text changed {n_changed}")


if __name__ == "__main__":
    main()
