"""Teacher avatar for yt_explain (Pillow + ffmpeg, no GPU).

Replaces the stick-man when config has  "avatar": {"enabled": true, "gender": "female"|"male"}.
Two styles: "pro" (default) = illustrated teacher sprite from assets/avatar/<gender>.png (+ .json face boxes made by
avatar_make.py) with mouth/eyes/head animated on top; "cartoon" = the built-in hand-drawn figure (Teacher).
The figure stands bottom-right of the slide; its mouth is lip-synced to the narration (loudness envelope
read from the audio with ffmpeg), it blinks, nods and gestures according to the slide pose
(wave / point / talk / think / cheer / sway).  Only the small avatar frames are rendered in Python; ffmpeg
overlays them on the static slide, so a 30-slide video renders in ~1-2 minutes.
"""
import json
import math
import os
import struct
import subprocess

from PIL import Image, ImageDraw

HERE = os.path.dirname(os.path.abspath(__file__))
ASSETS = os.path.join(HERE, "assets", "avatar")
FPS = 10
HEIGHT = 380
PRO_HEIGHT = 540
MARGIN = (50, 90)
POSES = ("wave", "point", "talk", "think", "cheer", "sway")

SKIN = (236, 188, 148)
SKIN_DARK = (205, 150, 110)
HAIR = (40, 30, 30)
MOUTH = (140, 40, 50)
TEETH = (250, 250, 250)


def _hex(c):
    c = c.lstrip("#")
    return tuple(int(c[i:i + 2], 16) for i in (0, 2, 4))


def _pt(x, y, ang, ln):
    return x + ln * math.cos(math.radians(ang)), y + ln * math.sin(math.radians(ang))


def _limb(d, p0, p1, w, color):
    d.line([p0, p1], fill=color, width=w)
    for x, y in (p0, p1):
        d.ellipse([x - w / 2, y - w / 2, x + w / 2, y + w / 2], fill=color)


def envelope(cfg, audio, secs, fps=FPS):
    """Loudness 0..1 per frame from the audio (ffmpeg -> mono 8 kHz s16le). Silence -> zeros."""
    n = max(1, int(round(secs * fps)))
    if not audio or not os.path.exists(audio):
        return [0.0] * n
    try:
        r = subprocess.run([cfg["ffmpeg"], "-v", "error", "-i", audio, "-f", "s16le", "-ac", "1", "-ar", "8000", "-"],
                           capture_output=True, check=True)
    except Exception:
        return [0.0] * n
    raw = r.stdout
    step = 8000 // fps
    vals = []
    for i in range(n):
        chunk = raw[i * step * 2:(i + 1) * step * 2]
        if len(chunk) < 4:
            vals.append(0.0)
            continue
        s = struct.unpack(f"<{len(chunk) // 2}h", chunk)
        vals.append(math.sqrt(sum(v * v for v in s) / len(s)) / 32768)
    peak = max(vals) or 1.0
    vals = [min(1.0, v / (peak * 0.7)) for v in vals]
    out = []                       # light smoothing: mouth cannot jump fully open/closed each 100 ms
    for i, v in enumerate(vals):
        prev = out[-1] if out else 0
        out.append(prev + (v - prev) * 0.65)
    return out


class Teacher:
    def __init__(self, gender="female", primary=(30, 60, 120), accent=(255, 160, 0), h=HEIGHT):
        self.g = gender
        self.primary, self.accent, self.h = primary, accent, h

    def frame(self, pose, phase, mouth=0.0, blink=False):
        """RGBA avatar image. phase 0..1 loop position (2 s), mouth 0..1 openness."""
        s = 2
        H = self.h * s
        W = int(self.h * 0.85) * s
        im = Image.new("RGBA", (W, H), (0, 0, 0, 0))
        d = ImageDraw.Draw(im)
        w = math.sin(phase * 2 * math.pi)
        nod = int(H * 0.008 * math.sin(phase * 4 * math.pi)) if mouth > 0.05 else 0
        cx = W // 2 + (int(H * 0.03 * w) if pose == "sway" else 0)
        r = H * 0.115                              # head radius
        hy = H * 0.19 + nod                        # head centre
        neck = hy + r * 0.95
        sh = neck + H * 0.045                      # shoulders
        hip = sh + H * 0.30
        bottom = H - H * 0.02
        lw = int(H * 0.045)                        # limb width
        female = self.g == "female"
        cloth = self.primary
        # ---- legs / lower body
        if female:
            d.polygon([(cx - H * 0.12, hip - H * 0.05), (cx + H * 0.12, hip - H * 0.05),
                       (cx + H * 0.19, bottom), (cx - H * 0.19, bottom)], fill=cloth)
            d.polygon([(cx - H * 0.11, hip - H * 0.05), (cx - H * 0.03, hip - H * 0.05), (cx + H * 0.02, bottom), (cx - H * 0.14, bottom)],
                      fill=tuple(min(255, c + 40) for c in cloth))
        else:
            trouser = (50, 55, 70)
            for side in (-1, 1):
                x = cx + side * H * 0.06
                d.rounded_rectangle([x - lw * 0.9, hip - H * 0.03, x + lw * 0.9, bottom], radius=lw // 2, fill=trouser)
            d.rectangle([cx - H * 0.10, hip - H * 0.03, cx + H * 0.10, hip + H * 0.04], fill=trouser)
        for side in (-1, 1):  # shoes
            x = cx + side * H * (0.09 if female else 0.06)
            d.ellipse([x - lw, bottom - lw * 0.8, x + lw * 1.1, bottom + lw * 0.3], fill=(40, 30, 25))
        # ---- torso
        shirt = cloth if female else (245, 245, 250)
        d.rounded_rectangle([cx - H * 0.15, sh - H * 0.02, cx + H * 0.15, hip], radius=int(H * 0.05), fill=shirt)
        if female:  # saree pallu in accent colour across the torso
            d.polygon([(cx - H * 0.15, hip - H * 0.02), (cx - H * 0.15, sh + H * 0.10), (cx + H * 0.06, sh - H * 0.02),
                       (cx + H * 0.15, sh + H * 0.02), (cx + H * 0.15, hip - H * 0.12)], fill=self.accent)
            d.line([cx + H * 0.06, sh - H * 0.02, cx - H * 0.15, hip - H * 0.02], fill=tuple(max(0, c - 40) for c in self.accent), width=lw // 3)
        else:  # collar + tie in accent, waistcoat in primary
            d.polygon([(cx - H * 0.15, sh - H * 0.02), (cx - H * 0.07, sh - H * 0.02), (cx - H * 0.15, hip - H * 0.06)], fill=cloth)
            d.polygon([(cx + H * 0.15, sh - H * 0.02), (cx + H * 0.07, sh - H * 0.02), (cx + H * 0.15, hip - H * 0.06)], fill=cloth)
            d.polygon([(cx - lw * 0.6, sh), (cx + lw * 0.6, sh), (cx + lw * 0.4, sh + H * 0.16), (cx, sh + H * 0.19), (cx - lw * 0.4, sh + H * 0.16)], fill=self.accent)
        # ---- arms  (angles: 0 = right, 90 = down; sleeve = cloth, hand = skin)
        arm, fore = H * 0.16, H * 0.14
        if pose == "wave":
            l1, l2 = (115, 95), (-65, -105 + 35 * w)
        elif pose == "point":
            l1, l2 = (112, 95), (198 + 5 * w, 188 + 8 * w)
        elif pose == "think":
            l1, l2 = (108, 95), (-100, 175 + 12 * w)
        elif pose == "cheer":
            l1, l2 = (-115 + 10 * w, -95 + 15 * w), (-65 - 10 * w, -85 - 15 * w)
        else:
            l1, l2 = (118 + 6 * w, 100 + 10 * w), (62 - 6 * w, 75 - 10 * w)
        sleeve = cloth if female else (245, 245, 250)
        for side, (a1, a2) in ((-1, l1), (1, l2)):
            sx = cx + side * H * 0.14
            ex, ey = _pt(sx, sh + H * 0.02, a1, arm)
            hx, hy2 = _pt(ex, ey, a2, fore)
            _limb(d, (sx, sh + H * 0.02), (ex, ey), lw, sleeve)
            _limb(d, (ex, ey), (hx, hy2), int(lw * 0.85), SKIN)
            d.ellipse([hx - lw * 0.75, hy2 - lw * 0.75, hx + lw * 0.75, hy2 + lw * 0.75], fill=SKIN)
            if pose == "point" and side == 1:
                px, py = _pt(hx, hy2, a2, lw * 1.6)
                d.line([hx, hy2, px, py], fill=SKIN, width=int(lw * 0.45))
        # ---- neck + head
        d.rectangle([cx - lw * 0.7, neck - r * 0.3, cx + lw * 0.7, sh + 2], fill=SKIN_DARK)
        if female:  # hair behind head (bun + side)
            d.ellipse([cx - r * 1.08, hy - r * 1.08, cx + r * 1.08, hy + r * 0.9], fill=HAIR)
            d.ellipse([cx + r * 0.65, hy - r * 0.2, cx + r * 1.35, hy + r * 0.5], fill=HAIR)
        else:
            d.ellipse([cx - r * 1.05, hy - r * 1.08, cx + r * 1.05, hy + r * 0.6], fill=HAIR)
        d.ellipse([cx - r, hy - r, cx + r, hy + r], fill=SKIN)
        d.ellipse([cx - r * 0.5, hy + r * 0.15, cx - r * 0.2, hy + r * 0.4], fill=(245, 205, 175))  # cheeks
        d.ellipse([cx + r * 0.2, hy + r * 0.15, cx + r * 0.5, hy + r * 0.4], fill=(245, 205, 175))
        for ex in (cx - r * 1.0, cx + r * 0.82):  # ears
            d.ellipse([ex, hy - r * 0.15, ex + r * 0.18, hy + r * 0.25], fill=SKIN_DARK)
        # hair front
        if female:
            d.chord([cx - r * 1.02, hy - r * 1.05, cx + r * 1.02, hy + r * 0.25], 190, 350, fill=HAIR)
            d.polygon([(cx - r * 1.0, hy - r * 0.35), (cx - r * 0.2, hy - r * 0.92), (cx - r * 0.95, hy + r * 0.05)], fill=HAIR)
            d.ellipse([cx - r * 0.07, hy - r * 0.45, cx + r * 0.07, hy - r * 0.31], fill=(200, 30, 40))  # bindi
        else:
            d.chord([cx - r * 1.02, hy - r * 1.08, cx + r * 1.02, hy + r * 0.05], 195, 355, fill=HAIR)
            d.polygon([(cx - r * 0.9, hy - r * 0.5), (cx + r * 0.3, hy - r * 0.95), (cx - r * 0.6, hy - r * 0.15)], fill=HAIR)
        # eyebrows + eyes
        eb = 0 if pose != "think" else -r * 0.06
        for side in (-1, 1):
            ex = cx + side * r * 0.4
            ey = hy - r * 0.12
            d.line([ex - r * 0.2, ey - r * 0.32 + (eb if side == 1 else 0), ex + r * 0.2, ey - r * 0.34 + (eb if side == -1 else 0)], fill=HAIR, width=max(2, lw // 4))
            if blink:
                d.line([ex - r * 0.16, ey, ex + r * 0.16, ey], fill=HAIR, width=max(2, lw // 4))
            else:
                d.ellipse([ex - r * 0.17, ey - r * 0.17, ex + r * 0.17, ey + r * 0.17], fill="white", outline=HAIR, width=2)
                d.ellipse([ex - r * 0.09, ey - r * 0.09, ex + r * 0.09, ey + r * 0.09], fill=HAIR)
                d.ellipse([ex - r * 0.02, ey - r * 0.06, ex + r * 0.04, ey], fill="white")
        # glasses (male) for a "teacher" look
        if not female:
            for side in (-1, 1):
                ex = cx + side * r * 0.4
                d.ellipse([ex - r * 0.3, hy - r * 0.42, ex + r * 0.3, hy + r * 0.16], outline=(50, 50, 60), width=max(2, lw // 5))
            d.line([cx - r * 0.1, hy - r * 0.15, cx + r * 0.1, hy - r * 0.15], fill=(50, 50, 60), width=max(2, lw // 5))
        # nose
        d.arc([cx - r * 0.12, hy + r * 0.05, cx + r * 0.12, hy + r * 0.35], 20, 160, fill=SKIN_DARK, width=max(2, lw // 5))
        # mouth (visemes by openness)
        my = hy + r * 0.58
        if pose == "think" and mouth < 0.1:
            d.line([cx - r * 0.2, my, cx + r * 0.2, my], fill=MOUTH, width=max(2, lw // 4))
        elif mouth < 0.08:
            d.arc([cx - r * 0.32, my - r * 0.25, cx + r * 0.32, my + r * 0.12], 15, 165, fill=MOUTH, width=max(3, lw // 4))
        else:
            op = r * (0.08 + 0.32 * mouth)
            wd = r * (0.34 - 0.08 * mouth)
            d.ellipse([cx - wd, my - op * 0.5, cx + wd, my + op * 0.5], fill=MOUTH)
            if mouth > 0.35:
                d.rectangle([cx - wd * 0.7, my - op * 0.5, cx + wd * 0.7, my - op * 0.5 + op * 0.28], fill=TEETH)
        return im.resize((W // s, H // s), Image.LANCZOS)


class Sprite:
    """Illustrated teacher (png + json from avatar_make.py) animated in place: lip-sync mouth, blinks, head bob."""

    def __init__(self, name, h=PRO_HEIGHT, flip=None):
        png = os.path.join(ASSETS, name + ".png")
        meta = json.load(open(os.path.join(ASSETS, name + ".json")))
        im = Image.open(png).convert("RGBA")
        if meta.get("flip", False) if flip is None else flip:      # mirror so the teacher faces the slide content
            im = im.transpose(Image.FLIP_LEFT_RIGHT)
            fx = lambda x: im.width - x
            mx0, _, mx1, _ = meta["mouth"]
            meta["mouth"] = [fx(mx1), meta["mouth"][1], fx(mx0), meta["mouth"][3]]
            meta["eyes"] = [[fx(c), b, fx(a), d] for a, b, c, d in meta["eyes"]]
        box = im.getbbox() or (0, 0) + im.size
        im = im.crop(box)
        self.k = h / im.height
        body = im.resize((int(im.width * self.k), h), Image.LANCZOS)
        pad = int(h * 0.05)                                   # room for the tilt so shoulders/hand are not cut
        self.base = Image.new("RGBA", (body.width + 2 * pad, h), (0, 0, 0, 0))
        self.base.alpha_composite(body, (pad, 0))
        off = (box[0] - pad / self.k, box[1])
        sc = lambda x, y: ((x - off[0]) * self.k, (y - off[1]) * self.k)
        mx0, my0, mx1, my1 = meta["mouth"]
        self.mouth = sc(mx0, my0) + sc(mx1, my1)
        self.eyes = [sc(a, b) + sc(c, d) for a, b, c, d in meta["eyes"]]
        self.skin = tuple(meta["skin"])
        self.lip = tuple(meta["lip"])
        self.dark = tuple(max(0, int(c * 0.35)) for c in self.lip)
        self.h = h
        self._faces = {}

    def _face(self, mouth, blink, wide):
        key = (mouth, blink, wide)
        if key not in self._faces:
            self._faces[key] = self._draw_face(mouth, blink, wide)
        return self._faces[key]

    def _draw_face(self, mouth, blink, wide):
        im = self.base.copy()
        d = ImageDraw.Draw(im)
        x0, y0, x1, y1 = self.mouth
        mw, mh = x1 - x0, y1 - y0
        cx, cy = (x0 + x1) / 2, (y0 + y1) / 2
        if mouth > 0.06:
            pad = mw * 0.14
            d.ellipse([x0 - pad, y0 - mh * 0.35, x1 + pad, y1 + mh * 0.45], fill=self.skin)   # hide painted lips
            op = mh * 0.9 + mouth * mw * 0.42
            w = mw / 2 * (1.0 - 0.18 * mouth) * wide
            d.ellipse([cx - w, cy - op * 0.45, cx + w, cy + op * 0.55], fill=self.lip)
            iw, ih = w * 0.86, op * 0.36
            d.ellipse([cx - iw, cy - ih * 0.9, cx + iw, cy + ih * 1.3], fill=self.dark)
            if mouth > 0.28:
                d.rounded_rectangle([cx - iw * 0.8, cy - ih * 0.9, cx + iw * 0.8, cy - ih * 0.9 + op * 0.16],
                                    radius=3, fill=(248, 246, 240))
        if blink:
            for ex0, ey0, ex1, ey1 in self.eyes:
                ew, eh = ex1 - ex0, ey1 - ey0
                d.ellipse([ex0 - ew * 0.15, ey0 - eh * 0.6, ex1 + ew * 0.15, ey1 + eh * 0.4], fill=self.skin)
                d.arc([ex0 - ew * 0.1, ey0, ex1 + ew * 0.1, ey1 + eh * 0.9], 15, 165, fill=self.dark, width=max(2, int(eh * 0.25)))
        return im

    def frame(self, pose, phase, mouth=0.0, blink=False):
        m = round(min(1.0, max(0.0, mouth)) * 6) / 6           # 7 mouth steps -> cached faces
        wide = 1.0 if m < 0.5 else (0.9 if int(phase * 20) % 2 else 1.05)
        im = self._face(m, blink, wide)
        # head/body motion: nod while speaking, slow sway when idle, pose-specific lean
        w = math.sin(phase * 2 * math.pi)
        nod = self.h * 0.006 * math.sin(phase * 6 * math.pi) if m > 0.1 else self.h * 0.003 * w
        tilt = {"point": -1.5, "think": 2.0, "cheer": 2.5 * w, "wave": -1.0 + 1.5 * w}.get(pose, 0.8 * w)
        rot = im.rotate(tilt, resample=Image.BICUBIC, center=(im.width / 2, im.height), expand=False)
        out = Image.new("RGBA", (im.width, im.height + int(self.h * 0.02)), (0, 0, 0, 0))
        out.alpha_composite(rot, (0, int(self.h * 0.01 + nod)))
        return out


class Sheet:
    """The user's own teacher sprite sheet cut by sheet_make.py (assets/avatar/<name>/manifest.json): the drawn frames are
    shown as they are - talking frames cycle while the narration is loud, idle frames sway in silence, gesture frames
    (pointing / explaining / thinking / happy / reading) follow the slide pose. No repainting of the face."""

    POSE_GROUP = {"talk": "talking", "point": "pointing", "think": "thinking", "cheer": "happy", "wave": "explaining",
                  "sway": "idle", "read": "reading"}

    def __init__(self, name, h=PRO_HEIGHT, flip=None):
        d = os.path.join(ASSETS, name)
        meta = json.load(open(os.path.join(d, "manifest.json")))
        self.h = h
        self.flip = bool(meta.get("flip", False) if flip is None else flip)
        self.groups = {}
        for g, files in meta["groups"].items():
            if g in ("viseme", "eyes"):
                continue
            ims = [Image.open(os.path.join(d, f)).convert("RGBA") for f in files]
            k = h / max(im.height for im in ims)
            ims = [im.resize((max(1, int(im.width * k)), max(1, int(im.height * k))), Image.LANCZOS) for im in ims]
            if self.flip:
                ims = [im.transpose(Image.FLIP_LEFT_RIGHT) for im in ims]
            W = max(im.width for im in ims) + int(h * 0.06)
            frames = []
            for im in ims:                                     # same canvas for the whole group -> no jitter
                c = Image.new("RGBA", (W, h), (0, 0, 0, 0))
                c.alpha_composite(im, ((W - im.width) // 2, h - im.height))
                frames.append(c)
            self.groups[g] = frames + frames[-2:0:-1]          # ping-pong loop
        self.idle = self.groups.get("idle") or next(iter(self.groups.values()))

    def frame(self, pose, phase, mouth=0.0, blink=False):
        g = self.POSE_GROUP.get(pose, "idle")
        if g == "idle" and mouth > 0.12:
            g = "talking"
        seq = self.groups.get(g) or self.idle
        speed = 2.0 if g in ("idle",) else (4.0 if mouth > 0.12 else 1.5)      # frames per second through the loop
        idx = int(phase * 2.0 * speed * len(seq) / 2.0) % len(seq)             # phase = 2-second loop
        im = seq[idx]
        w = math.sin(phase * 2 * math.pi)
        nod = self.h * 0.006 * math.sin(phase * 6 * math.pi) if mouth > 0.12 else self.h * 0.003 * w
        out = Image.new("RGBA", (im.width, im.height + int(self.h * 0.02)), (0, 0, 0, 0))
        out.alpha_composite(im, (0, int(self.h * 0.01 + nod)))
        return out


def _sprite_name(cfg):
    av = cfg.get("avatar") or {}
    name = av.get("name") or av.get("gender", "female")
    if av.get("style", "pro") == "pro" and (os.path.exists(os.path.join(ASSETS, name + ".json"))
                                            or os.path.exists(os.path.join(ASSETS, name, "manifest.json"))):
        return name
    return None


def figure_height(cfg):
    return PRO_HEIGHT if _sprite_name(cfg) else HEIGHT


_CACHE = {}


def _teacher(cfg):
    av = cfg.get("avatar") or {}
    name = _sprite_name(cfg)
    if name:
        if name not in _CACHE:
            sheet = os.path.exists(os.path.join(ASSETS, name, "manifest.json"))
            _CACHE[name] = (Sheet if sheet else Sprite)(name, flip=av.get("flip"))
        return _CACHE[name]
    return Teacher(av.get("gender", "female"),
                   _hex(av.get("color") or cfg.get("brand_primary", "#1e3c78")),
                   _hex(av.get("accent") or cfg.get("brand_accent", "#ffa000")))


def compose(cfg, slide, pose, phase, mouth=0.0, blink=False, height=None):
    """Single composed RGB frame (used for previews/thumbnails)."""
    fig = _teacher(cfg).frame(pose, phase, mouth, blink)
    portrait = slide.height > slide.width
    if portrait:
        fig = fig.resize((int(fig.width * 300 / fig.height), 300), Image.LANCZOS)
    out = slide.convert("RGBA")
    x = out.width - fig.width - (30 if portrait else MARGIN[0])
    y = out.height - fig.height - (100 if portrait else _bottom(cfg))
    out.alpha_composite(fig, (x, y))
    return out.convert("RGB")


def _bottom(cfg):
    return -14 if _sprite_name(cfg) else MARGIN[1]    # waist-up sprite sits on (slightly below) the frame edge


def clip(cfg, slide_png, pose, secs, audio, out_mp4, size):
    """Slide + lip-synced avatar -> silent clip of `secs` seconds (audio only drives the mouth)."""
    slide = Image.open(slide_png)
    portrait = slide.height > slide.width
    teacher = _teacher(cfg)
    fdir = os.path.splitext(slide_png)[0] + "_av"
    os.makedirs(fdir, exist_ok=True)
    env = envelope(cfg, audio, secs)
    fig_h = 300 if portrait else figure_height(cfg)
    for i, m in enumerate(env):
        phase = (i / FPS / 2.0) % 1.0
        blink = (i % (FPS * 3)) in (FPS * 2, FPS * 2 + 1) or (i % 71 == 40)    # blink every 3 s (+ an odd one)
        fig = teacher.frame(pose, phase, m, blink)
        if abs(fig.height - fig_h) > fig_h * 0.05:
            fig = fig.resize((int(fig.width * fig_h / fig.height), fig_h), Image.LANCZOS)
        fig.save(os.path.join(fdir, f"{i:04d}.png"))
    w, h = size
    x = f"main_w-overlay_w-{30 if portrait else MARGIN[0]}"
    y = f"main_h-overlay_h-{100 if portrait else _bottom(cfg)}"
    # the frame sequence is looped and the clip cut at exactly `secs` (a frame-sequence input ends one
    # frame early with shortest=1, and 0.1 s per slide adds up to seconds of drift over a video)
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error",
                    "-loop", "1", "-framerate", str(FPS), "-i", slide_png,
                    "-stream_loop", "-1", "-framerate", str(FPS), "-i", os.path.join(fdir, "%04d.png"),
                    "-filter_complex",
                    f"[0:v]scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2[bg];"
                    f"[bg][1:v]overlay={x}:{y},format=yuv420p",
                    "-t", f"{secs:.3f}", "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "20", "-an", out_mp4], check=True)
    for f in os.listdir(fdir):      # frames are only needed for the encode
        os.remove(os.path.join(fdir, f))
    os.rmdir(fdir)
    return out_mp4


def concat_clips(cfg, items, audio_out, video_out, size):
    """items: list of (png, audio_or_None, seconds, pose) -> final MP4 (same contract as stickman.concat_clips)."""
    from ytstudio.tts import duration
    d = os.path.dirname(video_out)
    silence = os.path.join(d, "_silence.wav")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "lavfi", "-i", "anullsrc=r=24000:cl=mono", "-t", "10", silence], check=True)
    alst, vlst = os.path.join(d, "_audio.txt"), os.path.join(d, "_clips.txt")
    with open(alst, "w", encoding="utf-8") as fa, open(vlst, "w", encoding="utf-8") as fv:
        for png, aud, secs, pose in items:
            mp4 = os.path.splitext(png)[0] + ".mp4"
            if not os.path.exists(mp4):
                clip(cfg, png, pose, secs, aud, mp4, size)
            fv.write(f"file '{os.path.abspath(mp4)}'\n")
            secs = duration(cfg, mp4) or secs          # pad audio to the clip's real length so slides never drift
            if aud:
                wav = os.path.splitext(aud)[0] + ".wav"
                if not os.path.exists(wav):
                    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-i", aud, "-ar", "24000", "-ac", "1", wav], check=True)
                fa.write(f"file '{os.path.abspath(wav)}'\n")
                gap = secs - duration(cfg, aud)
            else:
                gap = secs
            if gap > 0.05:
                fa.write(f"file '{os.path.abspath(silence)}'\ninpoint 0\noutpoint {gap:.3f}\n")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", alst, "-ar", "24000", "-ac", "1", audio_out], check=True)
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", vlst, "-i", audio_out,
                    "-c:v", "copy", "-c:a", "aac", "-b:a", "160k", "-shortest", "-movflags", "+faststart", video_out], check=True)
