"""Step 1 – OCR: render PDF pages (PyMuPDF) and transcribe them with Gemini vision, 3 pages per request, resumable.
Each page is stored as markdown; a page that starts a chapter/lesson gets its title as a '## ' line (used by chapters.py).
Figures are marked inline as  [चित्र: short caption]  so parts/figures can be linked later."""
import threading
from concurrent.futures import ThreadPoolExecutor
import pymupdf

PROMPT = """These are {n} consecutive pages of a Maharashtra State Board (Balbharati) school textbook ({lang}).
Transcribe ALL text on each page exactly as printed, in natural reading order (columns top-to-bottom), keeping headings,
paragraphs, numbered lists, questions, tables (as markdown tables), poems line by line, figure captions and activity boxes
(करून पाहा / जरा डोके चालवा / माहीत आहे का तुम्हांला? / Try this / Do you know? etc.). Skip page numbers and running headers.
Do NOT translate, summarise, correct or add anything. Use correct Devanagari spelling as printed; keep maths symbols and formulas.
Where a picture/diagram/map/table-figure appears, put a line  [चित्र: <very short caption or what it shows>]  at its position.
If a page begins a NEW chapter / lesson / poem (big chapter title with its number at the top), write that title as the first line
prefixed with '## ' (e.g. '## ३. विलग करू या घटक'). Do not use '## ' for sub-headings.
Return JSON: {{"pages": ["<markdown of page 1>", "<page 2>", ...]}} with exactly {n} entries, in order."""

SYS = "You are a precise OCR transcriber for Indian school textbooks. Output only the requested JSON."


def render(pdf_path, page_no, dpi=150):
    doc = pymupdf.open(pdf_path)
    pix = doc[page_no - 1].get_pixmap(dpi=dpi)
    img = pix.pil_image() if hasattr(pix, "pil_image") else None
    if img is None:
        from PIL import Image
        img = Image.frombytes("RGB", (pix.width, pix.height), pix.samples)
    doc.close()
    return img


def n_pages(pdf_path):
    with pymupdf.open(pdf_path) as d:
        return len(d)


def ocr_book(client, store, book, pages=None, batch=3, workers=4, log=print):
    """OCR all (or the given) pages of a book into store.pages. Returns number of pages transcribed."""
    lang = {"mr": "Marathi", "hi": "Hindi", "en": "English", "ur": "Urdu"}.get(book["lang"], book["lang"])
    todo = [p for p in (pages or range(1, book["n_pages"] + 1)) if not store.has_page(book["book_id"], p)]
    batches = [todo[i:i + batch] for i in range(0, len(todo), batch)]
    done, lock = [0], threading.Lock()

    def run(bp):
        try:
            imgs = [render(book["pdf"], p) for p in bp]
            js, _ = client.gemini(SYS, PROMPT.format(n=len(bp), lang=lang), imgs, max_tokens=16384)
            out = js.get("pages") if isinstance(js, dict) else None
            if (not isinstance(out, list) or len(out) != len(bp)) and len(bp) > 1:
                out = []
                for p in bp:   # model mis-split the batch: fall back to one page per request
                    js1, _ = client.gemini(SYS, PROMPT.format(n=1, lang=lang), [render(book["pdf"], p)], max_tokens=8192)
                    o1 = js1.get("pages") if isinstance(js1, dict) else None
                    out.append("\n".join(map(str, o1)) if isinstance(o1, list) else str(js1))
            elif not isinstance(out, list) or len(out) != len(bp):
                raise ValueError(f"expected {len(bp)} pages, got {0 if not out else len(out)}")
            for p, md in zip(bp, out):
                store.save_page(book["book_id"], p, str(md))
            with lock:
                done[0] += len(bp)
                if done[0] % 30 < batch:
                    log(f"  [ocr] {book['title']}: {done[0]}/{len(todo)} pages, cost ${client.cost.usd:.2f}")
        except Exception as e:  # noqa: BLE001
            log(f"  [ocr] ERR pages {bp[0]}-{bp[-1]}: {str(e)[:140]}")
            if "cost cap" in str(e):
                raise

    with ThreadPoolExecutor(workers) as ex:
        list(ex.map(run, batches))
    return done[0]
