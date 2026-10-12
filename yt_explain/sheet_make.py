"""Cut a teacher sprite sheet (the user's ChatGPT-style sheet on a checkerboard: viseme heads, eye heads, idle/talking
and gesture body frames, each row labelled) into single transparent frames for avatar.Sheet.

    python sheet_make.py sheet.png teacher_f

-> assets/avatar/<name>/NNN.png + manifest.json {"groups": {"idle": [...], "talking": [...], ...}}.
Rows are recognised by their order on the sheet (1 visemes, 2 eyes, 3 idle+talking, 4 pointing/reading/explaining/holding,
5 thinking/happy/surprised/sad); frames in a row are sorted left to right; touching figures are split at ink valleys."""
import json
import os
import sys

import numpy as np
from PIL import Image
from scipy import ndimage

HERE = os.path.dirname(os.path.abspath(__file__))
ASSETS = os.path.join(HERE, "assets", "avatar")
ROWS = [["viseme"], ["eyes"], ["idle", "talking"], ["pointing", "reading", "explaining", "holding"],
        ["thinking", "happy", "surprised", "sad"]]
COUNT = {"viseme": 12, "eyes": 15, "idle": 8, "talking": 8}


def _split(mask, box, n):
    """Split a component box holding n touching figures at the n-1 lowest ink columns near the expected positions."""
    y0, y1, x0, x1 = box
    prof = mask[y0:y1, x0:x1].sum(0)
    w = x1 - x0
    cuts = []
    for k in range(1, n):
        c = int(w * k / n)
        lo, hi = max(1, c - w // (3 * n)), min(w - 1, c + w // (3 * n))
        cuts.append(x0 + lo + int(np.argmin(prof[lo:hi])))
    xs = [x0] + cuts + [x1]
    return [(y0, y1, xs[i], xs[i + 1]) for i in range(n)]


def _clean(fr):
    """Keep the figure (largest component + anything sizeable); drop slivers of the neighbouring frame at the cut edges."""
    al = np.asarray(fr.getchannel("A")) > 0
    lab, n = ndimage.label(al)
    if n > 1:
        areas = ndimage.sum(al, lab, range(1, n + 1))
        keep = []
        for i, sl in enumerate(ndimage.find_objects(lab), 1):
            edge = sl[1].start == 0 or sl[1].stop == al.shape[1]
            if areas[i - 1] >= areas.max() * (0.25 if edge else 0.02):
                keep.append(i)
        fr = fr.copy()
        fr.putalpha(Image.fromarray((np.asarray(fr.getchannel("A")) * np.isin(lab, keep)).astype(np.uint8)))
    return fr.crop(fr.getbbox() or (0, 0, 1, 1))


def cut(path, name):
    im = Image.open(path).convert("RGB")
    a = np.asarray(im).astype(int)
    mx, mn = a.max(2), a.min(2)
    light = (mn > 180) & ((mx - mn) < 26)                        # checkerboard greys / whites
    lab, _ = ndimage.label(light)
    border = set(np.unique(np.concatenate([lab[0], lab[-1], lab[:, 0], lab[:, -1]]))) - {0}
    fg = ~np.isin(lab, list(border))                             # background = light pixels connected to the border
    r, g, b = a[..., 0], a[..., 1], a[..., 2]
    navy = (r < 22) & (g > r + 12) & (b > g + 4)                 # the dark-navy label pills under the figures ...
    plab, pn = ndimage.label(ndimage.binary_closing(navy, iterations=2))
    pill = np.zeros_like(navy)
    for i, sl in enumerate(ndimage.find_objects(plab), 1):       # ... = flat wide boxes only (not dark-blue clothes/books)
        if sl and 14 <= sl[0].stop - sl[0].start <= 40 and sl[1].stop - sl[1].start >= 24:
            pill |= plab == i
    fg &= ~ndimage.binary_dilation(pill, iterations=3)
    comp, _ = ndimage.label(fg)
    boxes = []
    for i, sl in enumerate(ndimage.find_objects(comp), 1):
        if sl is None:
            continue
        y0, y1, x0, x1 = sl[0].start, sl[0].stop, sl[1].start, sl[1].stop
        if y1 - y0 < 40 or x1 - x0 < 20:                        # labels (dark pills), specks, pointer sticks
            continue
        boxes.append((y0, y1, x0, x1))
    boxes.sort(key=lambda b: (b[0] + b[1]) / 2)
    rows, cur = [], []
    for b in boxes:
        if cur and (b[0] + b[1]) / 2 - (cur[-1][0] + cur[-1][1]) / 2 > 60:
            rows.append(cur)
            cur = []
        cur.append(b)
    if cur:
        rows.append(cur)
    rows = [sorted(r, key=lambda b: b[2]) for r in rows][:5]
    out = os.path.join(ASSETS, name)
    os.makedirs(out, exist_ok=True)
    rgba = im.convert("RGBA")
    rgba.putalpha(Image.fromarray((fg * 255).astype(np.uint8)))
    manifest = {"groups": {}, "size": im.size, "source": os.path.basename(path)}
    k = 0
    for names, row in zip(ROWS, rows):
        want = sum(COUNT.get(g, 4) for g in names)
        ws = sorted(b[3] - b[2] for b in row)
        unit = float(np.median([w for w in ws if w <= ws[0] * 1.35]))
        row = [p for b in row for p in _split(fg, b, max(1, int(round((b[3] - b[2]) / unit))))]   # touching figures
        row = sorted([b for b in row if b[3] - b[2] >= unit * 0.5], key=lambda b: b[2])            # slivers
        if len(row) > want:
            row = row[:want]
        per = len(row) / len(names)
        for gi, g in enumerate(names):
            files = []
            for (y0, y1, x0, x1) in row[int(round(gi * per)):int(round((gi + 1) * per))]:
                fr = _clean(rgba.crop((x0, y0, x1, y1)))
                f = f"{k:03d}.png"
                fr.save(os.path.join(out, f))
                files.append(f)
                k += 1
            # drop broken cuts (a frame that lost its body keeps only an outline) and refill from its neighbours
            cov = [np.asarray(Image.open(os.path.join(out, f)).getchannel("A")).mean() for f in files]
            med = float(np.median(cov))
            good = [f for f, c in zip(files, cov) if c >= med * 0.6]
            manifest["groups"][g] = good or files
    json.dump(manifest, open(os.path.join(out, "manifest.json"), "w"), indent=1)
    print(name, {g: len(v) for g, v in manifest["groups"].items()})
    return manifest


if __name__ == "__main__":
    cut(sys.argv[1], sys.argv[2])
