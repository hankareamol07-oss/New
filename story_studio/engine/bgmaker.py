"""Built-in painted backgrounds (no image needed): village, classroom, garden, home. 1920x1080 PNG."""
import math
import random

from PIL import Image, ImageDraw, ImageFilter

W, H = 1920, 1080


def _grad(d, box, top, bot):
    x0, y0, x1, y1 = box
    for y in range(y0, y1):
        k = (y - y0) / max(1, y1 - y0 - 1)
        d.line([(x0, y), (x1, y)], fill=tuple(int(a + (b - a) * k) for a, b in zip(top, bot)))


def _tree(d, x, y, s, rnd):
    d.rectangle((x - 9 * s, y - 120 * s, x + 9 * s, y), fill=(110, 72, 40))
    for dx, dy, r in ((0, -170, 70), (-50, -140, 52), (50, -140, 52), (-25, -205, 50), (28, -205, 48)):
        g = rnd.randint(-12, 12)
        d.ellipse((x + (dx - r) * s, y + (dy - r) * s, x + (dx + r) * s, y + (dy + r) * s), fill=(58 + g, 140 + g, 62 + g))


def _house(d, x, y, w, h, wall, roof):
    d.rectangle((x, y - h, x + w, y), fill=wall)
    d.polygon([(x - 18, y - h), (x + w / 2, y - h - h * 0.55), (x + w + 18, y - h)], fill=roof)
    d.rectangle((x + w * 0.4, y - h * 0.6, x + w * 0.6, y), fill=(120, 75, 40))
    d.rectangle((x + w * 0.1, y - h * 0.7, x + w * 0.28, y - h * 0.4), fill=(90, 140, 190))
    d.rectangle((x + w * 0.72, y - h * 0.7, x + w * 0.9, y - h * 0.4), fill=(90, 140, 190))


def village(seed=3):
    rnd = random.Random(seed)
    im = Image.new("RGB", (W, H))
    d = ImageDraw.Draw(im)
    _grad(d, (0, 0, W, 700), (120, 185, 240), (215, 236, 250))
    d.ellipse((1500, 90, 1640, 230), fill=(255, 230, 140))
    for cx, cy in ((300, 150), (900, 110), (1250, 210)):
        for dx, r in ((0, 45), (50, 60), (110, 42)):
            d.ellipse((cx + dx - r, cy - r, cx + dx + r, cy + r * 0.8), fill=(250, 252, 255))
    for i, col in enumerate(((150, 190, 150), (120, 170, 110))):
        pts = [(0, 700)] + [(x, 560 + i * 50 - 60 * math.sin(x / (260 + i * 90) + i)) for x in range(0, W + 40, 40)] + [(W, 700)]
        d.polygon(pts, fill=col)
    _grad(d, (0, 640, W, H), (130, 190, 90), (96, 150, 66))
    d.polygon([(760, 640), (1160, 640), (1500, H), (420, H)], fill=(214, 184, 140))
    _house(d, 140, 660, 300, 180, (236, 214, 170), (178, 70, 50))
    _house(d, 1500, 650, 260, 160, (226, 200, 160), (160, 60, 45))
    for x, s in ((560, 1.0), (1330, 0.9), (60, 1.2), (1860, 1.1)):
        _tree(d, x, 660, s, rnd)
    return im.filter(ImageFilter.GaussianBlur(1.2))


def classroom(seed=1):
    im = Image.new("RGB", (W, H))
    d = ImageDraw.Draw(im)
    _grad(d, (0, 0, W, 760), (246, 232, 200), (236, 214, 176))
    _grad(d, (0, 760, W, H), (178, 128, 86), (140, 96, 60))
    d.rectangle((520, 150, 1400, 560), fill=(120, 80, 40))
    d.rectangle((540, 170, 1380, 540), fill=(40, 78, 58))
    d.line([(600, 260), (900, 260)], fill=(230, 230, 220), width=5)
    d.line([(600, 330), (1050, 330)], fill=(230, 230, 220), width=5)
    for x in (150, 1620):
        d.rectangle((x, 180, x + 220, 460), fill=(150, 200, 235), outline=(255, 255, 255), width=12)
        d.line([(x + 110, 180), (x + 110, 460)], fill=(255, 255, 255), width=8)
    d.rectangle((0, 740, W, 770), fill=(120, 84, 50))
    return im.filter(ImageFilter.GaussianBlur(1.2))


def garden(seed=5):
    rnd = random.Random(seed)
    im = village(seed)
    d = ImageDraw.Draw(im)
    _grad(d, (0, 640, W, H), (120, 188, 84), (88, 150, 60))
    for _ in range(140):
        x, y = rnd.randint(0, W), rnd.randint(700, H)
        c = rnd.choice(((240, 90, 110), (250, 210, 70), (250, 250, 250), (200, 110, 220)))
        d.ellipse((x - 7, y - 7, x + 7, y + 7), fill=c)
    for x in (300, 820, 1260, 1700):
        _tree(d, x, 680, 1.1, rnd)
    return im.filter(ImageFilter.GaussianBlur(1.0))


def home(seed=2):
    im = Image.new("RGB", (W, H))
    d = ImageDraw.Draw(im)
    _grad(d, (0, 0, W, 760), (250, 226, 196), (240, 208, 170))
    _grad(d, (0, 760, W, H), (196, 150, 110), (160, 116, 80))
    d.rectangle((760, 160, 1160, 480), fill=(140, 200, 240), outline=(150, 100, 60), width=16)
    d.line([(960, 160), (960, 480)], fill=(150, 100, 60), width=10)
    d.rectangle((120, 420, 520, 760), fill=(150, 96, 60))
    for y in (500, 590, 680):
        d.line([(130, y), (510, y)], fill=(110, 70, 40), width=8)
    d.ellipse((1500, 200, 1620, 320), fill=(255, 255, 255), outline=(120, 80, 50), width=8)
    d.line([(1560, 260), (1560, 220)], fill=(40, 40, 40), width=6)
    d.line([(1560, 260), (1590, 270)], fill=(40, 40, 40), width=6)
    return im.filter(ImageFilter.GaussianBlur(1.0))


PRESETS = {"village": village, "classroom": classroom, "garden": garden, "home": home}


def make_all(out_dir):
    import os
    os.makedirs(out_dir, exist_ok=True)
    for k, f in PRESETS.items():
        p = os.path.join(out_dir, f"{k}.png")
        if not os.path.exists(p):
            f().save(p)
