import json, os, re, subprocess, sys
items = json.load(open('/tmp/minishala_links.json'))
OUT = '/home/ubuntu/papers/pdf'
os.makedirs(OUT, exist_ok=True)

def infer_classes(rows):
    """Subjects restart at मराठी for each class; std1..8 in order."""
    std = 0
    out = []
    for r in rows:
        if r['subject'].strip().startswith('मराठी'):
            std += 1
        out.append(dict(r, std=std))
    return out

manifest = []
for exam, rows in items.items():
    rows = [r for r in rows if r['url']]
    for r in infer_classes(rows):
        fid = re.search(r'id=([\w-]+)', r['url']).group(1)
        subj = r['subject'].replace(' ', '_').replace('.', '')
        fn = f"{exam}_std{r['std']}_{subj}_{fid[:6]}.pdf"
        path = os.path.join(OUT, fn)
        manifest.append(dict(exam=exam, std=r['std'], subject=r['subject'], file_id=fid, file=fn))
        if os.path.exists(path) and os.path.getsize(path) > 1000:
            continue
        subprocess.run(['curl', '-sL', '-A', 'Mozilla/5.0', '-o', path,
                        f'https://drive.google.com/uc?id={fid}&export=download'], capture_output=True, timeout=120)
        ok = os.path.exists(path) and open(path, 'rb').read(4) == b'%PDF'
        print(('OK ' if ok else 'FAIL '), fn, flush=True)
        if not ok and os.path.exists(path):
            os.remove(path)
json.dump(manifest, open('/home/ubuntu/papers/manifest.json', 'w'), ensure_ascii=False, indent=1)
