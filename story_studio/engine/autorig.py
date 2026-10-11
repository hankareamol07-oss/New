"""Auto-rig: guess head / mouth / eyes / arms / legs of a character symbol from layer names, shapes and colours,
plus joint positions (neck, shoulder, elbow, hip) from the rendered layer masks. The GUI lets the user correct it."""
import html
import re

import numpy as np

from . import xfl

NS = xfl.NS
RX_MOUTH = re.compile(r"lip|mouth|muh|month|mout", re.I)
RX_EYE = re.compile(r"eye|aankh|blink|ankh", re.I)
RX_HEAD = re.compile(r"head|face|sir|chehra", re.I)
K = 2.0                       # mask resolution: px per file unit


def _rgba(img):
    a = img.toarray()
    if img.colorType() == xfl.skia.kBGRA_8888_ColorType:
        a = a[:, :, [2, 1, 0, 3]]
    return a


def _layers(doc, root):
    tl = doc.timeline(doc.symbol(root))
    out = []
    for L in doc.layers(tl):
        lt = L.get("layerType", "normal")
        if lt in ("guide", "folder", "camera", "mask") or L.get("visible", "true") == "false":
            continue
        out.append(L)
    return out


def layer_symbols(doc, L, frame=0):
    df, _ = doc.frame_at(L, frame)
    if df is None:
        return []
    return [html.unescape(e.get("libraryItemName")) for e in df.iter(NS + "DOMSymbolInstance")]


def subtree(doc, names, depth=6):
    """Symbol names reachable from `names` -> {name: timeline length}."""
    out = {}
    stack = [(n, 0) for n in names]
    while stack:
        n, dpt = stack.pop()
        if n in out or dpt > depth:
            continue
        try:
            sym = doc.symbol(n)
        except Exception:
            continue
        tl = doc.timeline(sym)
        out[n] = doc.length(tl)
        for e in sym.iter(NS + "DOMSymbolInstance"):
            stack.append((html.unescape(e.get("libraryItemName")), dpt + 1))
    return out


def masks(doc, root, frame=0):
    full, b = xfl.render(doc, root, frame, scale=1.0, pad=8)
    if full is None:
        raise ValueError("empty symbol")
    res = {}
    for L in _layers(doc, root):
        n = L.get("name")
        img, _ = xfl.render(doc, root, frame, scale=K, bounds=b, only_layers={n})
        res[n] = _rgba(img)
    return b, res


def _bbox(a):
    ys, xs = np.nonzero(a[:, :, 3] > 40)
    if len(xs) == 0:
        return None
    return xs.min(), ys.min(), xs.max() + 1, ys.max() + 1, len(xs)


def _skin(a):
    r, g, b, al = [a[:, :, i].astype(int) for i in range(4)]
    return (al > 120) & (r > 140) & (r > g) & (g > b) & (r - b > 35) & (g > 80) & (r - g < 110)


def _to_units(b, x, y):
    return round(b[0] + x / K, 2), round(b[1] + y / K, 2)


def frame_stats(doc, sym, n):
    """Per frame: (opaque area, white area) of a symbol rendered alone."""
    _, bb = xfl.render(doc, sym, 0, scale=1.0, pad=6)
    if bb is None:
        return []
    x0, y0, x1, y1 = bb
    w, h = x1 - x0, y1 - y0
    bb = (x0 - w * 0.4, y0 - h * 0.6, x1 + w * 0.4, y1 + h * 0.8)
    out = []
    for f in range(n):
        img, _ = xfl.render(doc, sym, f, scale=4.0, bounds=bb)
        a = _rgba(img)
        al = a[:, :, 3] > 60
        white = al & (a[:, :, :3].min(2) > 215)
        out.append((int(al.sum()), int(white.sum())))
    return out


def mouth_frames(doc, sym, n):
    ar = [a for a, _ in frame_stats(doc, sym, min(n, 40))]
    if not ar:
        return dict(closed=0, mid=0, open=0)
    lo, hi = min(ar), max(ar)
    closed = 0 if ar[0] <= lo + (hi - lo) * 0.15 else ar.index(lo)
    opn = ar.index(hi)
    mid = min(range(len(ar)), key=lambda i: abs(ar[i] - (lo + hi) / 2))
    return dict(closed=closed, mid=mid, open=opn)


def blink_frames(doc, sym, n):
    """Blink = frames where the white of the eyes disappears (lids drawn over)."""
    wt = [w for _, w in frame_stats(doc, sym, min(n, 90))]
    if not wt or not max(wt):
        return dict(open=0, blink=[])
    ref = max(wt[0], 1)
    opn = 0 if wt[0] > max(wt) * 0.7 else wt.index(max(wt))
    lo = min(wt)
    if lo > ref * 0.5:
        return dict(open=opn, blink=[])
    best = wt.index(lo)
    run = [best]
    for i in range(best - 1, max(-1, best - 4), -1):
        if wt[i] < ref * 0.6:
            run.insert(0, i)
        else:
            break
    for i in range(best + 1, min(len(wt), best + 4)):
        if wt[i] < ref * 0.6:
            run.append(i)
        else:
            break
    return dict(open=opn, blink=run)


def guess(doc, root, frame=0, log=None):
    """-> manifest dict (rig part of it)."""
    b, ms = masks(doc, root, frame)
    W = (b[2] - b[0]) * K
    H = (b[3] - b[1]) * K
    info = {}
    for n, a in ms.items():
        bb = _bbox(a)
        if bb:
            info[n] = bb
    names = list(info)
    syms = {n: subtree(doc, layer_symbols(doc, L, frame)) for L in _layers(doc, root) for n in [L.get("name")] if n in info}

    # head: layer whose sub-symbols include eyes/mouth (or named head/face), highest on screen
    def head_score(n):
        s = syms.get(n, {})
        hits = sum(1 for k in s if RX_MOUTH.search(k) or RX_EYE.search(k))
        named = 1 if RX_HEAD.search(n) or any(RX_HEAD.search(k) for k in list(s)[:2]) else 0
        x0, y0, x1, y1, ar = info[n]
        return (hits > 0 or named, -y0 / H, -ar / (W * H))
    cands = [n for n in names if info[n][1] < H * 0.35]
    head = max(cands or names, key=head_score)
    hs = syms.get(head, {})
    mouth_syms = [k for k, ln in hs.items() if ln > 2 and RX_MOUTH.search(k)]
    eye_syms = [k for k, ln in hs.items() if ln > 2 and RX_EYE.search(k)]
    if not mouth_syms:      # unnamed mouth: single-frame graphic sets in the head
        mouth_syms = [k for k, ln in hs.items() if 4 <= ln <= 30 and not RX_EYE.search(k)][:1]
    mouth = {k: mouth_frames(doc, k, hs[k]) for k in mouth_syms}
    eyes = {k: blink_frames(doc, k, hs[k]) for k in eye_syms}

    hx0, hy0, hx1, hy1, _ = info[head]
    ha = ms[head][:, :, 3] > 40
    rows = np.nonzero(ha.any(1))[0]
    bot = rows[-max(1, len(rows) // 12):]
    nx = float(np.nonzero(ha[bot].any(0))[0].mean())
    neck = _to_units(b, nx, float(rows[-1]) - (hy1 - hy0) * 0.08)
    cx = nx                                                     # body centre line

    # arms: smallish pieces between the shoulders and the hips containing skin; per side the lowest one is the
    # forearm (hand), the piece overlapping its upper end is the upper arm (sleeve)
    parts = []
    for n in names:
        if n == head:
            continue
        x0, y0, x1, y1, ar = info[n]
        h = y1 - y0
        if h < H * 0.06 or h > H * 0.45 or y0 < hy1 - (hy1 - hy0) * 0.3 or y0 > H * 0.55 or y1 > H * 0.8 or ar > W * H * 0.08:
            continue
        if _skin(ms[n]).sum() > ar * 0.08:
            parts.append(n)
    arms = {}
    for side in ("left", "right"):
        mine = [n for n in parts if ((info[n][0] + info[n][2]) / 2 < cx) == (side == "left")]
        if not mine:
            continue
        n = max(mine, key=lambda n: info[n][3])
        x0, y0, x1, y1, ar = info[n]
        fa = ms[n][:, :, 3] > 40
        top = np.zeros_like(fa)
        cut = y0 + max(2, int((y1 - y0) * 0.25))
        top[y0:cut] = fa[y0:cut]
        top = _dilate(top, 6)
        best, bs = None, 0
        for u in names:
            if u in (n, head):
                continue
            ux0, uy0, ux1, uy1, uar = info[u]
            if uy0 >= y0 or uar > ar * 2.5 or uy1 > y1:
                continue
            ov = int(((ms[u][:, :, 3] > 40) & top).sum())
            if ov > bs:
                best, bs = u, ov
        if best is None:          # one-piece arm
            arms[side] = dict(upper=n, fore=None, shoulder=_to_units(b, (x0 + x1) / 2, y0 + 4), elbow=None)
            continue
        ua = ms[best][:, :, 3] > 40
        ux0, uy0, ux1, uy1, _ = info[best]
        rr = ua[uy0:uy0 + max(2, (uy1 - uy0) // 8)]
        sx = float(np.nonzero(rr.any(0))[0].mean()) if rr.any() else (ux0 + ux1) / 2
        ov = _dilate(ua, 3) & _dilate(fa, 3)
        ys, xs = np.nonzero(ov)
        if len(xs):
            ey = ys.min() + (ys.max() - ys.min()) * 0.35
            elbow = _to_units(b, float(xs.mean()), float(ey))
        else:
            elbow = _to_units(b, (x0 + x1) / 2, y0 + 3)
        arms[side] = dict(upper=best, fore=n, shoulder=_to_units(b, sx, uy0 + (uy1 - uy0) * 0.12), elbow=elbow)

    # legs: tall layers reaching the floor; feet: small layers at the floor; shadow: flat wide blob at the floor
    legs, feet, shadow = [], [], []
    for n in names:
        if n == head:
            continue
        x0, y0, x1, y1, ar = info[n]
        h, w = y1 - y0, x1 - x0
        if y1 < H * 0.9:
            continue
        a = ms[n]
        grey = (a[:, :, 3] > 40) & (np.ptp(a[:, :, :3].astype(int), axis=2) < 25)
        if h < w * 0.35 and grey[y0:y1, x0:x1].sum() > ar * 0.7:
            shadow.append(n)
        elif h < H * 0.12:
            feet.append(n)
        elif h > H * 0.2 and w < W * 0.45:
            legs.append(n)
    legs = sorted(legs, key=lambda n: info[n][0])
    if len(legs) > 2:
        legs = sorted(sorted(legs, key=lambda n: info[n][2] - info[n][0])[:2], key=lambda n: info[n][0])
    hips = {}
    for n in legs:
        x0, y0, x1, y1, ar = info[n]
        hips[n] = _to_units(b, (x0 + x1) / 2, y0 + 3)

    # facing: the face (skin) sits on the side the character looks to; hair/bun on the back side
    faces = "left"
    sk = _skin(ms[head])
    ys, xs = np.nonzero(sk[hy0:hy1, hx0:hx1])
    if len(xs):
        faces = "left" if xs.mean() + hx0 < (hx0 + hx1) / 2 else "right"
    front = "left" if faces == "left" else "right"
    back = "right" if front == "left" else "left"
    return dict(
        root=root, frame=frame, bounds=[round(v, 2) for v in b], faces=faces,
        head=head, neck=neck, mouth=mouth, eyes=eyes,
        arm_front=arms.get(front), arm_back=arms.get(back),
        legs=legs, hips=hips, feet=feet, shadow=shadow,
    )


def _dilate(m, r):
    out = m.copy()
    for dy in range(-r, r + 1, max(1, r // 2)):
        for dx in range(-r, r + 1, max(1, r // 2)):
            out |= np.roll(np.roll(m, dy, 0), dx, 1)
    return out
