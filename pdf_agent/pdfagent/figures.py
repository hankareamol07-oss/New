"""Step 4 – figures: Gemini looks at each chapter page image and returns the bounding boxes of pictures / diagrams / maps /
charts / tables (not plain text), with a caption and the nearby text; the agent crops them from the PDF render into
<workdir>/figures/<book_id>/<page:03d>_<k>.png and links each figure to the text part containing its nearby words."""
import json, os, re
from .ocr import render

SYS = "You locate illustrations on textbook pages. Output only JSON."
PROMPT = """This is page {page} of a std {std} {subject} textbook. List every picture, diagram, map, chart, photo or table-figure on the page
(NOT plain text blocks, page borders or decorative strips). For each give its bounding box in normalised 0-1000 coordinates
[ymin, xmin, ymax, xmax], a kind (photo|drawing|diagram|map|chart|table), a short caption in the book's language, and "near":
the 6-10 printed words that appear right next to it (the sentence it illustrates). Return JSON {{"figures": [{{"box": [..], "kind": "...", "caption": "...", "near": "..."}}]}};
empty list if none."""


def _norm(s):
    return set(re.findall(r"\w+", (s or "").lower()))


def find_figures(client, store, unit, workdir, log=print, min_area=0.01):
    book = store.book(unit["book_id"])
    outdir = os.path.join(workdir, "figures", str(book["book_id"]))
    os.makedirs(outdir, exist_ok=True)
    parts = store.parts(unit["unit_id"])
    figs = []
    for page in range(unit["first_page"], unit["last_page"] + 1):
        key = f"fig:{book['book_id']}:{page}"
        cache = os.path.join(outdir, f"{page:03d}.json")
        if os.path.exists(cache):
            found = json.load(open(cache, encoding="utf-8"))
        else:
            img = render(book["pdf"], page, dpi=200)
            try:
                js, _ = client.gemini(SYS, PROMPT.format(page=page, std=unit["std"], subject=unit["subject"]), [img])
            except Exception as e:  # noqa: BLE001
                log(f"  [figures] ERR p{page}: {str(e)[:120]}")
                if "cost cap" in str(e):
                    raise
                continue
            found = []
            W, H = img.size
            for k, f in enumerate(js.get("figures", []) if isinstance(js, dict) else [], 1):
                b = f.get("box") or []
                if len(b) != 4:
                    continue
                y0, x0, y1, x1 = [max(0, min(1000, int(v))) for v in b]
                if (y1 - y0) * (x1 - x0) / 1e6 < min_area or y1 <= y0 or x1 <= x0:
                    continue
                crop = img.crop((x0 * W // 1000, y0 * H // 1000, x1 * W // 1000, y1 * H // 1000))
                fn = f"{page:03d}_{k}.png"
                crop.save(os.path.join(outdir, fn))
                found.append({"page": page, "file": f"figures/{book['book_id']}/{fn}", "kind": f.get("kind", "drawing"), "caption": f.get("caption", ""), "near": f.get("near", "")})
            json.dump(found, open(cache, "w", encoding="utf-8"), ensure_ascii=False)
            store.job(key, "done")
        figs += found
    # link to the part with the most overlapping 'near' words
    for f in figs:
        near = _norm(f.get("near")) | _norm(f.get("caption"))
        best, score = None, 0
        for p in parts:
            s = len(near & _norm(p["text"]))
            if s > score:
                best, score = p["part_no"], s
        f["part_no"] = best if score >= 2 else (parts[0]["part_no"] if parts else None)
    store.save_figures(unit["unit_id"], figs)
    return figs
