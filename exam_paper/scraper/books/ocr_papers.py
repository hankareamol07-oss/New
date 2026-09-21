import glob, os, subprocess, tempfile
import pymupdf
OUT = '/home/ubuntu/papers/ocr'
os.makedirs(OUT, exist_ok=True)
for f in sorted(glob.glob('/home/ubuntu/papers/pdf/*.pdf')):
    name = os.path.splitext(os.path.basename(f))[0]
    lang = 'hin+eng' if 'हिंदी' in name else 'eng' if 'इंग्रजी' in name else 'mar+eng'
    d = pymupdf.open(f)
    for i in range(len(d)):
        out = f'{OUT}/{name}__{i:02d}.txt'
        if os.path.exists(out):
            continue
        with tempfile.NamedTemporaryFile(suffix='.png', delete=False) as tmp:
            d[i].get_pixmap(dpi=220).save(tmp.name)
        r = subprocess.run(['tesseract', tmp.name, '-', '-l', lang, '--psm', '4'], capture_output=True, text=True)
        os.unlink(tmp.name)
        open(out, 'w').write(r.stdout)
    print(name, len(d), flush=True)
print('DONE')
