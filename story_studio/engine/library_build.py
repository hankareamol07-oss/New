"""Turn FLA character packs into ready-to-use library characters: auto-rig every stage character,
keep those with a usable rig (head with face, mouth, eyes, arm), skip near-duplicates, save thumbnails."""
import json
import os
import re

import numpy as np

from . import autorig, library, paths, xfl

SKIP = re.compile(r"back|sit|tween|hand|face|eye|lips?$|scene|catch|walrus|front wc", re.I)
FEMALE = re.compile(r"girl|wom|sari|saree|lady|madam|teacher|mother|aai|kaku|female", re.I)


def _sig(p):
    return np.asarray(p.convert("LA").resize((24, 40))).astype(np.int16)


def probe(root, name):
    d = xfl.Doc(root)
    r = autorig.guess(d, name)
    img, _ = xfl.render(d, name, 0, scale=0.5)
    return r, xfl.to_pil(img)


def headless(p):
    """Packs often keep the body and the face as separate stage symbols: a body-only symbol shows floating
    eyes/lips with empty rows between them and the neck."""
    a = np.asarray(p.convert("RGBA"))[:, :, 3] > 40
    rows = a.sum(1)
    ys = np.nonzero(rows)[0]
    if not len(ys):
        return True
    y0, y1 = ys[0], ys[-1]
    xs = np.nonzero(a.sum(0))[0]
    w = xs[-1] - xs[0] + 1
    top = rows[y0:y0 + int((y1 - y0) * 0.3)]
    run = best = 0
    for v in top < w * 0.08:
        run = run + 1 if v else 0
        best = max(best, run)
    return best >= max(3, (y1 - y0) * 0.012)


def usable(r):
    return bool(r and r.get("head") and r.get("mouth") and r.get("eyes") and r.get("arm_front"))


def build(srcs, log=print, progress=None, cancel=None):
    """srcs: .fla files or extracted XFL folders. -> list of new character ids."""
    sigs = [_sig(library.thumb(n)) for n in library.characters() if library.thumb(n)]
    added = []
    for si, src in enumerate(srcs):
        root = library.import_fla(src, log) if src.lower().endswith(".fla") else src
        pack = os.path.basename(os.path.normpath(root)).replace("_", " ")
        names = [n for n in library.stage_items(root) if not SKIP.search(n.split("/")[-1])]
        log(f"== {pack}: {len(names)} candidates")
        for ni, name in enumerate(names):
            if cancel and cancel():
                return added
            if progress:
                progress(si + ni / max(1, len(names)), len(srcs))
            short = name.split("/")[-1]
            try:
                r, p = probe(root, name)
            except Exception as e:      # odd symbols: skip, keep going
                log(f"  skip {short}: {type(e).__name__}: {e}")
                continue
            if not usable(r) or headless(p):
                log(f"  skip {short}: no usable face/arms")
                continue
            s = _sig(p)
            if any(np.abs(s - o).mean() < 6 for o in sigs):
                log(f"  same as an existing character: {short}")
                continue
            sigs.append(s)
            cid = library.safe_name(pack.split()[0] + "_" + short).lower()
            out = os.path.join(paths.CHARS, cid)
            hints = " ".join([name, pack] + list(r.get("eyes") or {}) + list(r.get("mouth") or {}))
            gender = "female" if FEMALE.search(hints) else "male"
            library.export_symbol(root, name, out, dict(name=cid, label=short, gender=gender, pack=pack, rig=r))
            p.thumbnail((240, 360))
            p.save(os.path.join(out, "thumb.png"))
            added.append(cid)
            log(f"  + {cid} ({gender})")
    log(f"added {len(added)} characters")
    return added


def save_index(ids):
    with open(os.path.join(paths.CHARS, "_built.json"), "w", encoding="utf8") as f:
        json.dump(ids, f, indent=1)
