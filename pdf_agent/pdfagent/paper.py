"""Step – competitive / scholarship question papers (NMMS, शिष्यवृत्ती, MPSC-style PDFs): every page image goes to the vision
model, which returns each question with its passage (comprehension paragraph shared by several questions), options A-D, answer
when printed, and the bounding box of every picture that belongs to the question or to an option. The agent crops those boxes
from the page render at their real position into figures/<book_id>/paper/<page>_q<no>_<part>.png (part = q | A | B | C | D)
and stores the rows in paper_items; office.export_excel writes them with the image file names per column. Resumable per page."""
import json, os
from .ocr import render

SYS = "You read printed competitive-exam question papers for Indian school pupils and transcribe them exactly. Output only JSON."
PROMPT = """This is page {page} of {npages} of a question paper ({title}). {context}
Transcribe EVERY question on the page, in the printed language (keep numbers, symbols, formulas exactly). Return JSON:
{{"passages": [{{"id": "P1", "text": "full paragraph / poem / dialogue / instruction block that several questions refer to (e.g. 'प्र. 1 व 2 साठी ...',
                'प्रश्न क्रमांक 32 ते 35 साठी सूचना ...')", "box": [ymin,xmin,ymax,xmax] or null, "has_image": true/false}}],
  "questions": [{{"no": "<printed question number>", "passage": "P1" or null, "text": "question text", "q_image": [ymin,xmin,ymax,xmax] or null,
                 "options": ["option 1/A text", "option 2/B text", "option 3/C text", "option 4/D text"], "option_images": [box or null, box or null, box or null, box or null],
                 "answer": "A|B|C|D or null if not printed", "solution": "printed solution/explanation or null",
                 "section": "subject / section heading this question belongs to (English, बुद्धिमत्ता, मराठी, गणित, ...)",
                 "topic": "short chapter / topic name of the question in the paper's language (e.g. Punctuation, Odd one out, अपूर्णांक, समानार्थी शब्द, अक्षरमालिका)",
                 "marks": null}}],
  "answer_key": {{"<no>": "A"}},
  "section_end": "section heading in force at the bottom of this page"}}
Rules: boxes are normalised 0-1000 page coordinates [ymin, xmin, ymax, xmax], tight around the picture only (not the text around it);
"q_image" = picture that is part of the question (series, figure, map, table drawn as image); option_images = the picture of each option
when the options are pictures (text for that option then "" ); passage.box only when the passage itself is a picture/table (else null).
Text options that are plain words/numbers must not get boxes. Options may be printed 1) 2) 3) 4) or a) b) c) d) - always map them to A,B,C,D
in order. If no section heading is printed on this page, the section is the one carried over from the previous page (given above).
"answer_key": any answer key on the page - printed table OR a handwritten answer sheet (columns of question number + option number/letter,
possibly in Devanagari digits); convert option numbers 1-4 to A-D. If the page is only an answer sheet, questions = []."""


DEV = str.maketrans("०१२३४५६७८९", "0123456789")


def _num(v):
    """Question number as printed -> ASCII digits, no trailing punctuation."""
    return str(v or "").translate(DEV).strip().rstrip(".)]").strip()


def _letter(v):
    """Answer as 'A'..'D' from A-D, a-d, 1-4 (also Devanagari digits); None otherwise."""
    t = str(v or "").translate(DEV).strip().strip("()[].").upper()
    if t in ("1", "2", "3", "4"):
        return "ABCD"[int(t) - 1]
    return t[:1] if t[:1] in "ABCD" and t else None


def _runs(profile, gap=4):
    """Split a 1-D ink profile into [start,end) runs separated by >= gap blank cells."""
    runs, start, blank = [], None, 0
    for k, v in enumerate(profile):
        if v:
            if start is None:
                start = k
            blank = 0
        elif start is not None:
            blank += 1
            if blank >= gap:
                runs.append((start, k - blank + 1))
                start, blank = None, 0
    if start is not None:
        runs.append((start, len(profile)))
    return runs


def _trim_label(data, h, lo, hi, frac=0.7):
    """Pictures are taller than the '4)' / '35]' labels printed just left of them (often touching): drop leading columns whose
    ink height is well below the tallest column of the block."""
    hts = []
    for x in range(lo, hi):
        ys = [y for y in range(h) if data[x, y]]
        hts.append((ys[-1] - ys[0] + 1) if ys else 0)
    if not hts or max(hts) == 0:
        return (lo, hi)
    m = max(hts) * frac
    k = 0
    while k < len(hts) - 1 and hts[k] < m:
        k += 1
    return (lo + k, hi) if k < len(hts) * 0.6 else (lo, hi)


def _widest(cols, lo, hi, gap=2):
    """Widest ink sub-run inside cols[lo:hi] - drops a '4)' label glued to the picture by a tiny gap."""
    sub = _runs(cols[lo:hi], gap=gap)
    if not sub:
        return (lo, hi)
    a, b = max(sub, key=lambda r: r[1] - r[0])
    return (lo + a, lo + b)


def _crop(img, box, path, pad=0.04):
    """Crop a normalised [ymin,xmin,ymax,xmax] box. Model boxes are often a few % off, so: pad generously, find the ink
    blocks inside (columns/rows separated by white gaps), keep the block that overlaps the model's box most (drops option
    labels / neighbouring pictures caught by the padding) and trim to it with a small margin."""
    if not box or len(box) != 4:
        return None
    W, H = img.size
    y0, x0, y1, x1 = [max(0, min(1000, int(v))) for v in box]
    bx0, by0, bx1, by1 = x0 * W // 1000, y0 * H // 1000, x1 * W // 1000, y1 * H // 1000
    px, py = int(W * pad), int(H * pad)
    reg = (max(0, bx0 - px), max(0, by0 - py), min(W, bx1 + px), min(H, by1 + py))
    c = img.crop(reg)
    ink = c.convert("L").point(lambda v: 1 if v < 190 else 0)
    w, h = ink.size
    data = ink.load()
    cols = [any(data[x, y] for y in range(h)) for x in range(w)]
    rows = [any(data[x, y] for x in range(w)) for y in range(h)]

    def best(runs, lo, hi):
        # labels like "(B)" are small; the picture is the biggest ink block touching the model's box, else the biggest overall
        if not runs:
            return None
        return max(runs, key=lambda r: (min(r[1], hi) > max(r[0], lo), r[1] - r[0]))

    wide = (bx1 - bx0) > 2.5 * max(1, by1 - by0)
    cx = (bx0 - reg[0], bx1 - reg[0]) if wide else best(_runs(cols, gap=max(4, w // 40)), bx0 - reg[0], bx1 - reg[0])
    if wide:
        r = [q for q in _runs(cols, gap=max(4, w // 40)) if q[1] > cx[0] and q[0] < cx[1]]
        if r:
            cx = (min(q[0] for q in r), max(q[1] for q in r))
            if len(r) > 1 and (r[0][1] - r[0][0]) < 0.5 * max(q[1] - q[0] for q in r):
                cx = (r[1][0], cx[1])  # leading '35]' question label
            cx = _trim_label(data, h, *cx)
    if cx:
        rows = [any(data[x, y] for x in range(cx[0], cx[1])) for y in range(h)]
    cy = best(_runs(rows, gap=max(4, h // 40)), by0 - reg[1], by1 - reg[1])
    if cx and cy:
        m = 6
        c = c.crop((max(0, cx[0] - m), max(0, cy[0] - m), min(w, cx[1] + m), min(h, cy[1] + m)))
    c.save(path)
    return os.path.basename(path)


def _crop_options(img, boxes, paths):
    """Four picture options printed in one row. Model boxes usually sit a little left (they start at the '1)' label) and are
    the right height, so: take the row band from the boxes, scan the whole row for ink columns, drop narrow label blocks and
    assign the four widest columns A-D left to right - far more reliable than four separate model boxes."""
    bs = [b for b in boxes if b and len(b) == 4]
    if len(bs) != 4:
        return None
    W, H = img.size
    bw = max(b[3] - b[1] for b in bs) * W // 1000
    y0, y1 = min(b[0] for b in bs) * H // 1000, max(b[2] for b in bs) * H // 1000
    x0, x1 = min(b[1] for b in bs) * W // 1000, max(b[3] for b in bs) * W // 1000
    py, px = int(H * 0.012), int(W * 0.02)
    reg = (max(0, x0 - px), max(0, y0 - py), min(W, x1 + bw + px), min(H, y1 + py))
    c = img.crop(reg)
    ink = c.convert("L").point(lambda v: 1 if v < 190 else 0)
    w, h = ink.size
    data = ink.load()
    cols = [any(data[x, y] for y in range(h)) for x in range(w)]
    runs = _runs(cols, gap=max(4, w // 80))
    if len(runs) < 4:
        return None
    runs = sorted(sorted(runs, key=lambda r: r[1] - r[0])[-4:])  # 4 widest, left to right
    widths = [r[1] - r[0] for r in runs]
    if min(widths) < 0.3 * max(widths):  # one of them is a label, not a picture
        return None
    out = []
    for r, path in zip(runs, paths):
        r = _trim_label(data, h, *_widest(cols, r[0], r[1]))
        rows = [any(data[x, y] for x in range(r[0], r[1])) for y in range(h)]
        ry = max(_runs(rows, gap=4), key=lambda q: q[1] - q[0])
        m = 6
        c.crop((max(0, r[0] - m), max(0, ry[0] - m), min(w, r[1] + m), min(h, ry[1] + m))).save(path)
        out.append(path)
    return out



def read_paper(client, store, book, workdir, pages=None, log=print):
    outdir = os.path.join(workdir, "figures", str(book["book_id"]), "paper")
    os.makedirs(outdir, exist_ok=True)
    rel = f"figures/{book['book_id']}/paper"
    total = 0
    keys = {}
    section = store.last_section(book["book_id"]) or ""
    for page in (pages or range(1, book["n_pages"] + 1)):
        key = f"paper:{book['book_id']}:{page}"
        if store.job_done(key):
            continue
        img = render(book["pdf"], page, dpi=200)
        try:
            ctx = f'The previous page ended inside the section "{section}".' if section else ""
            js, model = client.chat_json(SYS, PROMPT.format(page=page, npages=book["n_pages"], title=book["title"], context=ctx), [img], max_tokens=16384)
        except Exception as e:  # noqa: BLE001
            log(f"  [paper] ERR p{page}: {str(e)[:140]}")
            if "cost cap" in str(e):
                raise
            continue
        if not isinstance(js, dict):
            continue
        pas = {}
        for p in js.get("passages") or []:
            pid = str(p.get("id") or f"P{len(pas) + 1}")
            fn = _crop(img, p.get("box"), os.path.join(outdir, f"{page:03d}_{pid}.png")) if p.get("box") else None
            pas[pid] = {"text": p.get("text") or "", "file": f"{rel}/{page:03d}_{pid}.png" if fn else None}
        keys.update({_num(k): _letter(v) for k, v in (js.get("answer_key") or {}).items() if _letter(v)})
        rows = []
        for q in js.get("questions") or []:
            no = str(q.get("no") or len(rows) + 1).strip().rstrip(".)")
            opts = [str(o or "") for o in (q.get("options") or [])][:4]
            opts += [""] * (4 - len(opts))
            oi = list(q.get("option_images") or []) + [None] * 4
            qi = _crop(img, q.get("q_image"), os.path.join(outdir, f"{page:03d}_q{no}_q.png"))
            opaths = [os.path.join(outdir, f"{page:03d}_q{no}_{'ABCD'[i]}.png") for i in range(4)]
            oimgs = _crop_options(img, oi[:4], opaths) or [_crop(img, oi[i], opaths[i]) for i in range(4)]
            if not (q.get("text") or qi or any(oimgs)):
                continue
            ans = _letter(q.get("answer"))
            pid = q.get("passage")
            section = (q.get("section") or section or "").strip()
            rows.append(dict(page=page, no=_num(no), section=section, topic=(q.get("topic") or section or "").strip(), passage_id=pid, passage=pas.get(pid, {}).get("text", "") if pid else "",
                             passage_file=pas.get(pid, {}).get("file") if pid else None, text=q.get("text") or "",
                             q_file=f"{rel}/{os.path.basename(qi)}" if qi else None, options=opts,
                             option_files=[f"{rel}/{os.path.basename(f)}" if f else None for f in oimgs],
                             answer=ans, solution=q.get("solution") or "", marks=q.get("marks"), model=model))
        section = (js.get("section_end") or section or "").strip()
        store.save_paper_items(book["book_id"], page, rows)
        store.job(key, "done")
        total += len(rows)
        log(f"  [paper] p{page}: {len(rows)} questions, {sum(1 for r in rows if r['q_file'] or any(r['option_files']))} with images")
    if keys:
        n = store.apply_answer_key(book["book_id"], keys)
        log(f"  [paper] answer key applied to {n} questions")
    fill_topics(client, store, book, log)
    return total


TOPIC_SYS = "You classify school exam questions by syllabus chapter / topic. Output only JSON."


def fill_topics(client, store, book, log=print):
    """Questions whose topic the vision pass left empty (then = section name) get a short chapter/topic name from a cheap text
    call, 40 at a time: {"<no>": "topic"} in the question's language."""
    todo = [dict(r) for r in store.paper_items(book["book_id"]) if not r["topic"] or r["topic"] == r["section"]]
    n = 0
    for i in range(0, len(todo), 40):
        batch = todo[i:i + 40]
        lines = "\n".join(f'{r["no"]} [{r["section"]}]: {(r["text"] or "")[:300]} | options: {json.loads(r["options_json"] or "[]")}' for r in batch)
        try:
            js, _ = client.chat_json(TOPIC_SYS, f"Std {book.get('std') or ''} question paper. For each question give a short chapter / topic name "
                                     f"(2-4 words, in the question's language, e.g. Punctuation, अपूर्णांक, अक्षरमालिका, बेरीज) that a teacher would file it under. "
                                     f'Return JSON {{"<no>": "topic"}} for every number.\n\n{lines}', max_tokens=4096)
        except Exception as e:  # noqa: BLE001
            log(f"  [paper] topics ERR: {str(e)[:120]}")
            if "cost cap" in str(e):
                raise
            break
        if isinstance(js, dict):
            n += store.set_topics(book["book_id"], {_num(k): str(v).strip() for k, v in js.items() if str(v).strip()})
    if n:
        log(f"  [paper] topic names filled for {n} questions")
