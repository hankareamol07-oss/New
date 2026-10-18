"""Backgrounds from Adobe Animate files: every keyframe of the main stage becomes one 1920x1080 JPG."""
import os

import numpy as np
from PIL import Image

from . import fla_import, library, paths, xfl


def _blank(im):
    a = np.asarray(im.convert("L").resize((96, 54)), dtype=np.float32)
    return a.std() < 6 or (a > 245).mean() > 0.6


def from_fla(src, out_dir=None, log=print, progress=None, cancel=None, W=1920, H=1080):
    """src: .fla file or extracted XFL folder. Returns list of saved file paths."""
    out_dir = out_dir or paths.BGS
    os.makedirs(out_dir, exist_ok=True)
    base = library.safe_name(os.path.splitext(os.path.basename(src.rstrip("/\\")))[0]).lower()
    root = src
    if os.path.isfile(src):
        root = os.path.join(paths.IMPORTS, "bg_" + base)
        if not os.path.exists(os.path.join(root, "DOMDocument.xml")):
            fla_import.extract(src, root, log=log)
    d = xfl.Doc(root)
    sw, sh = float(d.dom.get("width", 550)), float(d.dom.get("height", 400))
    tl = d.dom.find(xfl.NS + "timelines/" + xfl.NS + "DOMTimeline")
    starts = sorted({int(f.get("index")) for L in d.layers(tl) for f in L.iter(xfl.NS + "DOMFrame")})
    saved, seen = [], []
    for i, k in enumerate(starts):
        if cancel and cancel():
            break
        if progress:
            progress(i, len(starts))
        try:
            img, _ = xfl.render(d, "stage", k, scale=min(W / sw, H / sh), bounds=(0, 0, sw, sh))
        except Exception as e:
            log(f"  frame {k}: {type(e).__name__}: {e}")
            continue
        p = xfl.to_pil(img)
        im = Image.new("RGB", p.size, "white")
        im.paste(p, (0, 0), p)
        im = im.resize((W, H), Image.LANCZOS)
        if _blank(im):
            continue
        sig = np.asarray(im.convert("L").resize((32, 18)), dtype=np.float32)
        if any(np.abs(sig - s).mean() < 4 for s in seen):
            continue
        seen.append(sig)
        f = os.path.join(out_dir, f"{base}_{len(saved) + 1}.jpg")
        im.save(f, quality=88)
        saved.append(f)
        log(f"  + {os.path.basename(f)}")
    log(f"{base}: {len(saved)} backgrounds")
    return saved
