#!/usr/bin/env python3
"""Re-OCR the yt_explain textbooks with Gemini on Vertex AI (paid GCP project) -> books/socr/<book>/<page:03d>.md,
same layout as sarvam_ocr.py so rebuild_sources.py consumes both. Resumable (existing pages skipped).

Needs "vertex": {"project", "location", "key_file"} in ~/wt_yt/yt_explain/config.json (key file path relative to yt_explain).
Env: WORKERS (default 4), MODEL (default gemini-2.5-flash), BATCH pages per request (default 3).
"""
import base64, json, os, sqlite3, sys, threading, time
from concurrent.futures import ThreadPoolExecutor

import pymupdf, requests

ROOT = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(ROOT, "socr")
EXPL = os.path.expanduser("~/wt_yt/yt_explain")
sys.path.insert(0, os.path.expanduser("~/wt_yt/yt_studio"))
from ytstudio import vertex  # noqa: E402

CFG = json.load(open(os.path.join(EXPL, "config.json"), encoding="utf-8"))
kf = CFG.get("vertex", {}).get("key_file")
if kf and not os.path.isabs(kf):
    CFG["vertex"]["key_file"] = os.path.join(EXPL, kf)
MODELS = [m for m in os.environ.get("MODEL", "gemini-2.5-flash").split(",") if m]
DPI = 160
BATCH = int(os.environ.get("BATCH", "3"))
WORKERS = int(os.environ.get("WORKERS", "4"))
PROMPT = """These are consecutive pages of a Maharashtra State Board school textbook (Marathi, Hindi or English).
Transcribe ALL text on each page exactly as printed, in natural reading order (columns top-to-bottom), as Markdown:
headings as '## ', paragraphs, numbered lists, questions, tables as Markdown tables, poems line by line,
activity boxes (करून पाहा / जरा डोके चालवा / माहीत आहे का तुम्हांला? ...) with their heading. Skip page numbers,
running headers and the 'free distribution' notice. Write '[IMG]' where a picture/figure is (no description).
Write any dotted / blank fill-in line as exactly '.....' (five dots) and never repeat dots, dashes or spaces.
Do NOT translate, summarise, correct or add anything. Use the exact Devanagari spelling as printed.
If a page begins a NEW chapter / lesson / poem (big chapter number and title at the top), write the number and the
title as two '## ' lines (e.g. '## ३' then '## विलग करू या घटक'). Do not use '## ' for ordinary sub-headings.
Separate pages with a line containing only '=== PAGE ==='. Output Markdown only."""

lock = threading.Lock()
stats = {"req": 0, "pages": 0, "err": 0, "in_tok": 0, "out_tok": 0}
PRICE_IN, PRICE_OUT = 0.50, 3.00          # USD per 1M tokens (upper estimate for flash models)
COST_CAP = float(os.environ.get("COST_CAP", "100"))


def cost():
    return stats["in_tok"] / 1e6 * PRICE_IN + stats["out_tok"] / 1e6 * PRICE_OUT


def find_pdf(name):
    for d, _, fs in os.walk(ROOT):
        if name in fs and "socr" not in d and "gocr" not in d:
            return os.path.join(d, name)
    return None


def call(parts):
    for attempt in range(8):
        model = MODELS[min(attempt, len(MODELS) - 1)]
        url, h = vertex.endpoint(CFG, model)
        body = {"contents": [{"role": "user", "parts": parts}], "generationConfig": {"temperature": 0.1, "maxOutputTokens": 16000, **({"thinkingConfig": {"thinkingBudget": 0}} if "flash" in model and "3.5" not in model else {})}}
        try:
            r = requests.post(url, json=body, headers=h, timeout=240)
        except Exception as e:
            print("net", e, file=sys.stderr); time.sleep(15); continue
        if r.status_code == 200:
            j = r.json()
            um = j.get("usageMetadata", {})
            with lock:
                stats["in_tok"] += um.get("promptTokenCount", 0)
                stats["out_tok"] += um.get("candidatesTokenCount", 0) + um.get("thoughtsTokenCount", 0)
                if cost() > COST_CAP:
                    raise SystemExit(f"COST CAP reached: ${cost():.2f}")
            try:
                return "".join(p.get("text", "") for p in j["candidates"][0]["content"]["parts"]), model
            except Exception:
                print("bad resp", str(j)[:300], file=sys.stderr); time.sleep(5); continue
        if r.status_code in (401, 403):
            raise SystemExit(f"auth error {r.status_code}: {r.text[:300]}")
        print("HTTP", r.status_code, model, r.text[:200], file=sys.stderr, flush=True)
        time.sleep(20 if r.status_code in (429, 500, 503, 504) else 5)
    raise RuntimeError("vertex: too many failures")


def job(pdf, outdir, pages):
    doc = pymupdf.open(pdf)
    parts = [{"text": PROMPT}]
    for p in pages:
        img = doc[p - 1].get_pixmap(dpi=DPI).tobytes("jpeg")
        parts.append({"inline_data": {"mime_type": "image/jpeg", "data": base64.b64encode(img).decode()}})
    doc.close()
    try:
        text, model = call(parts)
    except Exception as e:
        with lock:
            stats["err"] += 1
        print("ERR", os.path.basename(outdir), pages, e, file=sys.stderr, flush=True)
        return
    chunks = [c.strip("\n") for c in text.split("=== PAGE ===")]
    chunks = [c for c in chunks if c.strip()] if len(chunks) > len(pages) else chunks
    if len(chunks) != len(pages):
        if len(chunks) < len(pages) and len(pages) > 1:
            for p in pages:
                job(pdf, outdir, [p])
            return
        chunks = (chunks + [""] * len(pages))[:len(pages)]
    for p, c in zip(pages, chunks):
        tmp = os.path.join(outdir, f"{p:03d}.md.tmp")
        with open(tmp, "w", encoding="utf-8") as f:
            f.write(c.strip() + "\n")
        os.replace(tmp, os.path.join(outdir, f"{p:03d}.md"))
    with lock:
        stats["req"] += 1; stats["pages"] += len(pages)
        if stats["req"] % 10 == 0:
            print(f"[{time.strftime('%H:%M')}] {stats} est ${cost():.2f} {model}", flush=True)


def main():
    books = json.load(open(os.path.join(ROOT, "book_questions.json"), encoding="utf-8"))["books"]
    db = sqlite3.connect(os.path.join(EXPL, "explain.db"))
    used = {r[0] for r in db.execute("SELECT DISTINCT book_id FROM units WHERE book_id IS NOT NULL")}
    only = set(map(int, sys.argv[1:])) if len(sys.argv) > 1 else None
    jobs = []
    for b in books:
        if b["book_id"] not in used or (only and b["book_id"] not in only):
            continue
        pdf = find_pdf(b["file"])
        if not pdf:
            print("missing pdf", b["file"]); continue
        outdir = os.path.join(OUT, os.path.splitext(b["file"])[0])
        os.makedirs(outdir, exist_ok=True)
        n = len(pymupdf.open(pdf))
        todo = [p for p in range(1, n + 1) if not os.path.exists(os.path.join(outdir, f"{p:03d}.md"))]
        for i in range(0, len(todo), BATCH):
            jobs.append((pdf, outdir, todo[i:i + BATCH]))
    print(f"{len(jobs)} requests / ~{sum(len(j[2]) for j in jobs)} pages to do", flush=True)
    with ThreadPoolExecutor(WORKERS) as ex:
        for j in jobs:
            ex.submit(job, *j)
    print("DONE", stats, f"est ${cost():.2f}", flush=True)


if __name__ == "__main__":
    main()
