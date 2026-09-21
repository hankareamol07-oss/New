#!/usr/bin/env python3
"""OCR every page of every textbook PDF into books/ocr/<book>/<page>.txt (resumable)."""
import csv, os, subprocess, sys, tempfile
from concurrent.futures import ThreadPoolExecutor
import pymupdf

ROOT = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(ROOT, 'ocr')
DPI = 160

def lang_for(fname):
    if 'Hindi' in fname:
        return 'hin+eng'
    if 'Marathi' in fname:          # Marathi textbook or "_in_Marathi" edition
        return 'mar+eng'
    return 'eng'

def find_pdf(name):
    for d, _, fs in os.walk(ROOT):
        if name in fs:
            return os.path.join(d, name)
    return None

def ocr_page(pdf, page_no, lang, out_txt):
    if os.path.exists(out_txt):
        return
    doc = pymupdf.open(pdf)
    with tempfile.NamedTemporaryFile(suffix='.png', delete=False) as tmp:
        doc[page_no].get_pixmap(dpi=DPI).save(tmp.name)
    doc.close()
    try:
        r = subprocess.run(['tesseract', tmp.name, '-', '-l', lang, '--psm', '4', '-c', 'tessedit_do_invert=0'],
                           capture_output=True, text=True, timeout=180)
        text = r.stdout
    except subprocess.TimeoutExpired:
        text = ''
    finally:
        os.unlink(tmp.name)
    with open(out_txt + '.tmp', 'w') as f:
        f.write(text)
    os.replace(out_txt + '.tmp', out_txt)

def main():
    rows = list(csv.DictReader(open(os.path.join(ROOT, 'INDEX.csv'))))
    # Marathi-medium books first, then Hindi, then English editions
    rows.sort(key=lambda r: (0 if 'Marathi' in r['File'] else 1 if 'Hindi' in r['File'] else 2, r['Standard']))
    jobs = []
    for r in rows:
        pdf = find_pdf(r['File'])
        if not pdf:
            print('MISSING', r['File'], file=sys.stderr); continue
        book = os.path.splitext(r['File'])[0]
        os.makedirs(os.path.join(OUT, book), exist_ok=True)
        n = len(pymupdf.open(pdf))
        lang = lang_for(r['File'])
        for p in range(n):
            jobs.append((pdf, p, lang, os.path.join(OUT, book, f'{p:03d}.txt')))
    todo = [j for j in jobs if not os.path.exists(j[3])]
    print(f'{len(jobs)} pages total, {len(todo)} to do', flush=True)
    done = 0
    with ThreadPoolExecutor(max_workers=int(os.environ.get('WORKERS', '2'))) as ex:
        for _ in ex.map(lambda j: ocr_page(*j), todo):
            done += 1
            if done % 50 == 0:
                print(f'{done}/{len(todo)}', flush=True)
    print('DONE', flush=True)

if __name__ == '__main__':
    main()
