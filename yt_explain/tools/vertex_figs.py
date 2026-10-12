#!/usr/bin/env python3
"""Find the figures on textbook pages with Gemini (Vertex) and crop them -> books/figs/<book>/<page:03d>_<k>.png + .json
(caption, bbox, kind). Resumable: pages with an existing <page:03d>.json are skipped. Usage: vertex_figs.py [book_id ...]
Only chapter pages (per yt_explain explain.db units meta pages / book_questions chapters) are processed."""
import base64, json, os, sqlite3, sys, threading, time
from concurrent.futures import ThreadPoolExecutor

import pymupdf, requests

ROOT = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(ROOT, "figs")
EXPL = os.path.expanduser("~/wt_yt/yt_explain")
sys.path.insert(0, os.path.expanduser("~/wt_yt/yt_studio"))
from ytstudio import vertex  # noqa: E402

CFG = json.load(open(os.path.join(EXPL, "config.json"), encoding="utf-8"))
kf = CFG.get("vertex", {}).get("key_file")
if kf and not os.path.isabs(kf):
    CFG["vertex"]["key_file"] = os.path.join(EXPL, kf)
MODEL = os.environ.get("MODEL", "gemini-2.5-flash")
DPI_DETECT, DPI_CROP = 110, 200
WORKERS = int(os.environ.get("WORKERS", "6"))
MIN_SIDE = 0.12          # ignore figures smaller than 12% of page width/height (icons, bullets)
PROMPT = """This is one page of a Maharashtra State Board school textbook. List every illustration, photo, diagram,
chart, map or table-as-picture on the page that a teacher would show on a slide while explaining this text
(skip decorative icons, small bullets, logos, borders, the running header and the activity-box icons).
For each give the bounding box in normalised coordinates 0-1000 [ymin, xmin, ymax, xmax] covering the picture
(include its label/caption line if printed directly under it), the caption/label text as printed (or "" if none),
and the first 6-10 words of the paragraph on the page that this picture belongs to (nearby text), copied exactly.
Return STRICT JSON: {"figures": [{"box": [ymin, xmin, ymax, xmax], "caption": "...", "near": "...", "kind": "photo|diagram|drawing|chart|map|table"}]}
If there is no such figure return {"figures": []}."""

lock = threading.Lock()
stats = {"req": 0, "figs": 0, "err": 0, "in_tok": 0, "out_tok": 0}
PRICE_IN, PRICE_OUT = 0.50, 3.00
COST_CAP = float(os.environ.get("COST_CAP", "30"))


def cost():
    return stats["in_tok"] / 1e6 * PRICE_IN + stats["out_tok"] / 1e6 * PRICE_OUT


def find_pdf(name):
    for d, _, fs in os.walk(ROOT):
        if name in fs and "socr" not in d and "gocr" not in d and "figs" not in d:
            return os.path.join(d, name)
    return None


def call(img_b64):
    body = {"contents": [{"role": "user", "parts": [{"text": PROMPT}, {"inline_data": {"mime_type": "image/jpeg", "data": img_b64}}]}],
            "generationConfig": {"temperature": 0.0, "maxOutputTokens": 4000, "responseMimeType": "application/json",
                                 "thinkingConfig": {"thinkingBudget": 0}}}
    for attempt in range(6):
        url, h = vertex.endpoint(CFG, MODEL)
        try:
            r = requests.post(url, json=body, headers=h, timeout=300)
        except Exception as e:
            print("net", e, file=sys.stderr); time.sleep(10); continue
        if r.status_code == 200:
            j = r.json()
            um = j.get("usageMetadata", {})
            with lock:
                stats["in_tok"] += um.get("promptTokenCount", 0)
                stats["out_tok"] += um.get("candidatesTokenCount", 0)
                if cost() > COST_CAP:
                    raise SystemExit(f"COST CAP reached: ${cost():.2f}")
            try:
                txt = "".join(p.get("text", "") for p in j["candidates"][0]["content"]["parts"])
                return json.loads(txt).get("figures") or []
            except Exception as e:
                print("bad resp", e, str(j)[:200], file=sys.stderr); time.sleep(3); continue
        if r.status_code in (401, 403):
            raise SystemExit(f"auth error {r.status_code}: {r.text[:300]}")
        print("HTTP", r.status_code, r.text[:150], file=sys.stderr, flush=True)
        time.sleep(15 if r.status_code in (429, 500, 503, 504) else 5)
    raise RuntimeError("vertex: too many failures")


def job(pdf, outdir, p):
    try:
        doc = pymupdf.open(pdf)
        page = doc[p - 1]
        small = page.get_pixmap(dpi=DPI_DETECT).tobytes("jpeg")
        figs = call(base64.b64encode(small).decode())
        kept = []
        W, H = page.rect.width, page.rect.height
        for k, f in enumerate(figs, 1):
            try:
                y0, x0, y1, x1 = [max(0, min(1000, float(v))) / 1000 for v in f["box"]]
            except Exception:
                continue
            if x1 - x0 < MIN_SIDE or y1 - y0 < MIN_SIDE:
                continue
            clip = pymupdf.Rect(x0 * W, y0 * H, x1 * W, y1 * H)
            fn = f"{p:03d}_{k}.png"
            page.get_pixmap(dpi=DPI_CROP, clip=clip).save(os.path.join(outdir, fn))
            kept.append({"file": fn, "box": [y0, x0, y1, x1], "caption": (f.get("caption") or "").strip(),
                         "near": (f.get("near") or "").strip(), "kind": f.get("kind", "")})
        doc.close()
        tmp = os.path.join(outdir, f"{p:03d}.json.tmp")
        json.dump({"page": p, "figures": kept}, open(tmp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
        os.replace(tmp, os.path.join(outdir, f"{p:03d}.json"))
        with lock:
            stats["req"] += 1; stats["figs"] += len(kept)
            if stats["req"] % 25 == 0:
                print(f"[{time.strftime('%H:%M')}] {stats} est ${cost():.2f}", flush=True)
    except SystemExit:
        raise
    except Exception as e:
        with lock:
            stats["err"] += 1
        print("ERR", os.path.basename(outdir), p, e, file=sys.stderr, flush=True)


def chapter_pages(db, book_id):
    """pdf pages covered by this book's units (meta pages, set by rebuild_sources) -> set of ints."""
    pages = set()
    for (mj,) in db.execute("SELECT meta_json FROM units WHERE book_id=?", (book_id,)):
        m = json.loads(mj or "{}")
        pg = m.get("pages")
        if pg and len(pg) == 2:
            pages.update(range(int(pg[0]), int(pg[1]) + 1))
    return pages


def main():
    books = json.load(open(os.path.join(ROOT, "book_questions.json"), encoding="utf-8"))["books"]
    db = sqlite3.connect(os.path.join(EXPL, "explain.db"))
    used = {r[0] for r in db.execute("SELECT DISTINCT book_id FROM units WHERE book_id IS NOT NULL")}
    args = sys.argv[1:]
    only = set(map(int, [a for a in args if a.isdigit()])) or None
    jobs = []
    for b in books:
        if b["book_id"] not in used or (only and b["book_id"] not in only):
            continue
        pdf = find_pdf(b["file"])
        if not pdf:
            print("missing pdf", b["file"]); continue
        outdir = os.path.join(OUT, os.path.splitext(b["file"])[0])
        os.makedirs(outdir, exist_ok=True)
        pages = chapter_pages(db, b["book_id"])
        if os.environ.get("PAGES"):
            a, z = map(int, os.environ["PAGES"].split("-")); pages = set(range(a, z + 1))
        for p in sorted(pages):
            if not os.path.exists(os.path.join(outdir, f"{p:03d}.json")):
                jobs.append((pdf, outdir, p))
    print(f"{len(jobs)} pages to scan for figures", flush=True)
    with ThreadPoolExecutor(WORKERS) as ex:
        for j in jobs:
            ex.submit(job, *j)
    print("DONE", stats, f"est ${cost():.2f}", flush=True)


if __name__ == "__main__":
    main()
