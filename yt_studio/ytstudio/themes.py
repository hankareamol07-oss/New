"""Per-subject slide themes: colours + a light background pattern + a corner icon.

config "theme": "auto" (default: pick by subject), a theme name below, or "brand" (plain brand colours).
Each theme: primary (header/titles), accent (lines/badges), bg (slide background), pattern (drawn by
draw_pattern), icon (unicode glyph drawn faintly at the top-right of the content area).
"""
import math
import random

from PIL import ImageDraw

THEMES = {
    "math":      dict(primary="#1e3a8a", accent="#38bdf8", bg="#f0f6ff", pattern="grid",   icon="1 2 3"),
    "science":   dict(primary="#065f46", accent="#84cc16", bg="#f0fdf6", pattern="atoms",  icon="H2O"),
    "marathi":   dict(primary="#7c2d12", accent="#f59e0b", bg="#fff8ee", pattern="lines",  icon="अ"),
    "hindi":     dict(primary="#7f1d1d", accent="#fb923c", bg="#fff7f2", pattern="lines",  icon="क"),
    "english":   dict(primary="#312e81", accent="#f472b6", bg="#f7f5ff", pattern="lines",  icon="Aa"),
    "history":   dict(primary="#5b3a1e", accent="#d4a017", bg="#f9f3e6", pattern="parchment", icon="1857"),
    "geography": dict(primary="#0c4a6e", accent="#22c55e", bg="#eefaf7", pattern="contours", icon="N-S"),
    "civics":    dict(primary="#1f2937", accent="#f97316", bg="#f5f7fa", pattern="dots",   icon="RTE"),
    "evs":       dict(primary="#166534", accent="#fbbf24", bg="#f3fbef", pattern="leaves", icon="EVS"),
    "sanskrit":  dict(primary="#78350f", accent="#eab308", bg="#fffbea", pattern="lines",  icon="ॐ"),
    "general":   dict(primary="#1e3c78", accent="#ffa000", bg="#f4f7ff", pattern="dots",   icon=""),
}

_KEYS = [
    ("math", ("math", "गणित", "ganit")),
    ("science", ("science", "विज्ञान", "vigyan", "सामान्य विज्ञान")),
    ("evs", ("परिसर", "evs", "environment", "parisar")),
    ("history", ("history", "इतिहास", "itihas")),
    ("geography", ("geography", "भूगोल", "bhugol")),
    ("civics", ("civics", "नागरिक", "political", "राज्यशास्त्र")),
    ("marathi", ("marathi", "मराठी", "बालभारती", "balbharati")),
    ("hindi", ("hindi", "हिंदी", "हिन्दी", "सुलभभारती")),
    ("english", ("english", "इंग्रजी", "इंग्लिश")),
    ("sanskrit", ("sanskrit", "संस्कृत")),
]


def pick(cfg, subject):
    """Theme dict for this subject (or the configured fixed theme)."""
    name = (cfg.get("theme") or "auto").lower()
    if name == "brand":
        return dict(THEMES["general"], primary=cfg["brand_primary"], accent=cfg["brand_accent"], pattern="none", icon="")
    if name in THEMES:
        return dict(THEMES[name])
    s = (subject or "").lower()
    for key, words in _KEYS:
        if any(w in s for w in words):
            return dict(THEMES[key])
    return dict(THEMES["general"], primary=cfg["brand_primary"], accent=cfg["brand_accent"])


def _mix(c, bg, a):
    return tuple(int(bg[i] * (1 - a) + c[i] * a) for i in range(3))


def draw_pattern(im, theme, primary, accent, bg, top, bottom):
    """Faint subject pattern between the header (top) and footer (bottom) bands."""
    d = ImageDraw.Draw(im)
    w = im.width
    p = theme.get("pattern", "none")
    line = _mix(primary, bg, 0.10)
    soft = _mix(accent, bg, 0.16)
    rnd = random.Random(7)  # deterministic pattern
    if p == "grid":
        for x in range(0, w, 60):
            d.line([x, top, x, bottom], fill=line, width=1)
        for y in range(top, bottom, 60):
            d.line([0, y, w, y], fill=line, width=1)
    elif p == "lines":
        for y in range(top + 40, bottom, 56):
            d.line([0, y, w, y], fill=line, width=2)
        d.line([140, top, 140, bottom], fill=_mix((220, 60, 60), bg, 0.25), width=3)
    elif p == "dots":
        for x in range(30, w, 48):
            for y in range(top + 30, bottom, 48):
                d.ellipse([x - 3, y - 3, x + 3, y + 3], fill=line)
    elif p == "atoms":
        for _ in range(9):
            cx, cy, r = rnd.randint(0, w), rnd.randint(top, bottom), rnd.randint(40, 120)
            d.ellipse([cx - r, cy - r // 3, cx + r, cy + r // 3], outline=line, width=2)
            d.ellipse([cx - r // 3, cy - r, cx + r // 3, cy + r], outline=line, width=2)
            d.ellipse([cx - r * 0.75, cy - r * 0.75, cx + r * 0.75, cy + r * 0.75], outline=line, width=2)
            d.ellipse([cx - 6, cy - 6, cx + 6, cy + 6], fill=soft)
    elif p == "parchment":
        for _ in range(1800):
            x, y = rnd.randint(0, w), rnd.randint(top, bottom)
            d.point((x, y), fill=_mix(primary, bg, rnd.uniform(0.05, 0.18)))
        for y in (top + 20, bottom - 20):
            d.line([60, y, w - 60, y], fill=soft, width=3)
    elif p == "contours":
        for k in range(6):
            pts = []
            for x in range(0, w + 40, 40):
                y = top + 80 + k * 150 + 40 * math.sin(x / 260 + k) + 20 * math.sin(x / 90 + k * 2)
                pts.append((x, y))
            d.line(pts, fill=line, width=2)
    elif p == "leaves":
        for _ in range(28):
            x, y = rnd.randint(0, w), rnd.randint(top, bottom)
            d.chord([x, y, x + 60, y + 30], 0, 180, fill=_mix(accent, bg, 0.10))
            d.chord([x, y, x + 60, y + 30], 180, 360, fill=_mix(primary, bg, 0.08))
    return im
