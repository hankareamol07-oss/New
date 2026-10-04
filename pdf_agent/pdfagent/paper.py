"""Step – competitive / scholarship question papers (NMMS, शिष्यवृत्ती, MPSC-style PDFs): every page image goes to the vision
model, which returns each question with its passage (comprehension paragraph shared by several questions), options A-D, answer
when printed, and the bounding box of every picture that belongs to the question or to an option. The agent crops those boxes
from the page render at their real position into figures/<book_id>/paper/<page>_q<no>_<part>.png (part = q | A | B | C | D)
and stores the rows in paper_items; office.export_excel writes them with the image file names per column. Resumable per page."""
import json, os
from .ocr import render

SYS = "You read printed competitive-exam question papers for Indian school pupils and transcribe them exactly. Output only JSON."
PROMPT = """This is page {page} of a question paper ({title}). Transcribe EVERY question on the page, in the printed language (keep numbers,
symbols, formulas exactly). Return JSON:
{{"passages": [{{"id": "P1", "text": "full paragraph / poem / table text that several questions refer to", "box": [ymin,xmin,ymax,xmax] or null,
                "has_image": true/false}}],
  "questions": [{{"no": "<printed question number>", "passage": "P1" or null, "text": "question text", "q_image": [ymin,xmin,ymax,xmax] or null,
                 "options": ["A text", "B text", "C text", "D text"], "option_images": [box or null, box or null, box or null, box or null],
                 "answer": "A|B|C|D or null if not printed", "solution": "printed solution/explanation or null", "section": "subject/section heading", "marks": null}}],
  "answer_key": {{"<no>": "A"}} }}
Rules: boxes are normalised 0-1000 page coordinates [ymin, xmin, ymax, xmax], tight around the picture only (not the text around it);
"q_image" = picture that is part of the question (series, figure, map, table drawn as image); option_images = the picture of each option
when the options are pictures (text for that option then "" ); passage.box only when the passage itself is a picture/table
(else null). Text options that are plain words/numbers must not get boxes. Include an answer-key table printed on the page in "answer_key"."""


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

    cx = best(_runs(cols, gap=max(4, w // 40)), bx0 - reg[0], bx1 - reg[0])
    if cx:
        rows = [any(data[x, y] for x in range(cx[0], cx[1])) for y in range(h)]
    cy = best(_runs(rows, gap=max(4, h // 40)), by0 - reg[1], by1 - reg[1])
    if cx and cy:
        m = 6
        c = c.crop((max(0, cx[0] - m), max(0, cy[0] - m), min(w, cx[1] + m), min(h, cy[1] + m)))
    c.save(path)
    return os.path.basename(path)


def _crop_options(img, boxes, paths):
    """Four picture options printed in one row: find the ink blocks across the whole row band (ignoring small label blocks)
    and assign them A-D left to right - far more reliable than four separate model boxes."""
    bs = [b for b in boxes if b and len(b) == 4]
    if len(bs) != 4:
        return None
    W, H = img.size
    y0, y1 = min(b[0] for b in bs) * H // 1000, max(b[2] for b in bs) * H // 1000
    x0, x1 = min(b[1] for b in bs) * W // 1000, max(b[3] for b in bs) * W // 1000
    py, px = int(H * 0.03), int(W * 0.04)
    reg = (max(0, x0 - px), max(0, y0 - py), min(W, x1 + px), min(H, y1 + py))
    c = img.crop(reg)
    ink = c.convert("L").point(lambda v: 1 if v < 190 else 0)
    w, h = ink.size
    data = ink.load()
    cols = [any(data[x, y] for y in range(h)) for x in range(w)]
    runs = _runs(cols, gap=max(4, w // 80))
    if len(runs) < 4:
        return None
    runs = sorted(sorted(runs, key=lambda r: r[1] - r[0])[-4:])  # 4 widest, left to right
    out = []
    for r, path in zip(runs, paths):
        rows = [any(data[x, y] for x in range(r[0], r[1])) for y in range(h)]
        ry = max(_runs(rows, gap=4), key=lambda q: q[1] - q[0])
        m = 6
        c.crop((max(0, r[0] - m), max(0, ry[0] - m), min(w, r[1] + m), min(h, ry[1] + m))).save(path)
        out.append(os.path.basename(path))
    return out


def read_paper(client, store, book, workdir, pages=None, log=print):
    outdir = os.path.join(workdir, "figures", str(book["book_id"]), "paper")
    os.makedirs(outdir, exist_ok=True)
    rel = f"figures/{book['book_id']}/paper"
    total = 0
    keys = {}
    for page in (pages or range(1, book["n_pages"] + 1)):
        key = f"paper:{book['book_id']}:{page}"
        if store.job_done(key):
            continue
        img = render(book["pdf"], page, dpi=200)
        try:
            js, model = client.chat_json(SYS, PROMPT.format(page=page, title=book["title"]), [img], max_tokens=16384)
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
        keys.update({str(k): str(v).strip().upper()[:1] for k, v in (js.get("answer_key") or {}).items()})
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
            ans = (str(q.get("answer") or "").strip().upper()[:1]) or None
            pid = q.get("passage")
            rows.append(dict(page=page, no=no, section=q.get("section") or "", passage_id=pid, passage=pas.get(pid, {}).get("text", "") if pid else "",
                             passage_file=pas.get(pid, {}).get("file") if pid else None, text=q.get("text") or "",
                             q_file=f"{rel}/{os.path.basename(qi)}" if qi else None, options=opts,
                             option_files=[f"{rel}/{os.path.basename(f)}" if f else None for f in oimgs],
                             answer=ans if ans in list("ABCD") else None, solution=q.get("solution") or "", marks=q.get("marks"), model=model))
        store.save_paper_items(book["book_id"], page, rows)
        store.job(key, "done")
        total += len(rows)
        log(f"  [paper] p{page}: {len(rows)} questions, {sum(1 for r in rows if r['q_file'] or any(r['option_files']))} with images")
    if keys:
        n = store.apply_answer_key(book["book_id"], keys)
        log(f"  [paper] answer key applied to {n} questions")
    return total
