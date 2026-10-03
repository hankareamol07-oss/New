"""Step 2 – chapters: turn OCR pages into units (one per chapter/lesson).
Primary signal: the '## <no>. <title>' line the OCR puts on a chapter's first page. Optional: a chapter list given by the
user (instruction / TOC) to name or correct chapters; the LLM reconciles both when they disagree."""
import json, re

DEV = str.maketrans("०१२३४५६७८९", "0123456789")
HEAD = re.compile(r"^##\s*(\d{1,2})?\s*[.)]?\s*(.+)$")
SYS = "You are a careful editor of Indian school textbooks. Output only JSON."
RECONCILE = """A textbook ({std} {subject}) has these OCR-detected chapter starts (pdf page -> heading):
{found}
The official chapter list (from the user / table of contents) is:
{toc}
Return the final chapter list as JSON {{"chapters": [{{"no": <int>, "title": "<official title>", "first_page": <pdf page>}}]}} in book order.
Use the detected page when the heading matches a chapter (ignore spelling/OCR differences); for a chapter with no detected
heading, estimate its first page from neighbouring chapters; drop detected headings that are not chapters (preface, index, answer key)."""


def detect(pages):
    """[(page, no|None, title)] from '## ' lines."""
    out = []
    for r in pages:
        for line in (r["md"] or "").splitlines():
            m = HEAD.match(line.strip())
            if m:
                no = int(m.group(1).translate(DEV)) if m.group(1) else None
                out.append((r["page"], no, m.group(2).strip(" .:-")))
                break
    return out


def build_units(client, store, book, toc=None, log=print):
    """Create units for a book. toc: optional list of "no. title" strings. Returns the chapter list."""
    pages = store.pages(book["book_id"])
    if not pages:
        raise ValueError("book has no OCR pages yet – run ocr first")
    found = detect(pages)
    n_pages = book["n_pages"]
    if toc:
        found_s = "\n".join(f"p{p}: {no or ''} {t}" for p, no, t in found) or "(none)"
        js, _ = client.chat_json(SYS, RECONCILE.format(std=book["std"], subject=book["subject"], found=found_s, toc="\n".join(toc)))
        chs = [c for c in js.get("chapters", []) if c.get("first_page")]
    else:
        chs, n = [], 0
        for p, no, t in found:
            n = no or n + 1
            chs.append({"no": n, "title": t, "first_page": p})
    chs.sort(key=lambda c: c["first_page"])
    for i, c in enumerate(chs):
        c["last_page"] = (chs[i + 1]["first_page"] - 1) if i + 1 < len(chs) else n_pages
        uid = f"{book['std']}_{book['subject']}_{c['no']}".replace(" ", "")
        store.save_unit(uid, book["book_id"], book["std"], book["subject"], book["lang"], c["no"], c["title"], c["first_page"], c["last_page"],
                        meta={"pdf": book["pdf"], "pages": [c["first_page"], c["last_page"]]})
    log(f"  [chapters] {book['title']}: {len(chs)} chapters" + ("" if chs else " – no '## ' headings found; give the chapter list (TOC) in your instruction"))
    return chs
