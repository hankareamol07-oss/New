"""Cartoon teacher avatar for yt_explain (Pillow + ffmpeg, no GPU).

Replaces the stick-man when config has  "avatar": {"enabled": true, "gender": "female"|"male"}.
The figure stands bottom-right of the slide; its mouth is lip-synced to the narration (loudness envelope
read from the audio with ffmpeg), it blinks, nods and gestures according to the slide pose
(wave / point / talk / think / cheer / sway).  Only the small avatar frames are rendered in Python; ffmpeg
overlays them on the static slide, so a 30-slide video renders in ~1-2 minutes.
"""
import math
import os
import struct
import subprocess

from PIL import Image, ImageDraw

FPS = 10
HEIGHT = 380
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


def _teacher(cfg):
    av = cfg.get("avatar") or {}
    return Teacher(av.get("gender", "female"),
                   _hex(av.get("color") or cfg.get("brand_primary", "#1e3c78")),
                   _hex(av.get("accent") or cfg.get("brand_accent", "#ffa000")))


def compose(cfg, slide, pose, phase, mouth=0.0, blink=False, height=HEIGHT):
    """Single composed RGB frame (used for previews/thumbnails)."""
    fig = _teacher(cfg).frame(pose, phase, mouth, blink)
    portrait = slide.height > slide.width
    if portrait:
        fig = fig.resize((int(fig.width * 230 / fig.height), 230), Image.LANCZOS)
    out = slide.convert("RGBA")
    x = out.width - fig.width - (30 if portrait else MARGIN[0])
    y = out.height - fig.height - (100 if portrait else MARGIN[1])
    out.alpha_composite(fig, (x, y))
    return out.convert("RGB")


def clip(cfg, slide_png, pose, secs, audio, out_mp4, size):
    """Slide + lip-synced avatar -> silent clip of `secs` seconds (audio only drives the mouth)."""
    slide = Image.open(slide_png)
    portrait = slide.height > slide.width
    teacher = _teacher(cfg)
    fdir = os.path.splitext(slide_png)[0] + "_av"
    os.makedirs(fdir, exist_ok=True)
    env = envelope(cfg, audio, secs)
    fig_h = 230 if portrait else HEIGHT
    for i, m in enumerate(env):
        phase = (i / FPS / 2.0) % 1.0
        blink = (i % (FPS * 3)) == FPS * 2          # one blink every 3 s
        fig = teacher.frame(pose, phase, m, blink)
        if fig.height != fig_h:
            fig = fig.resize((int(fig.width * fig_h / fig.height), fig_h), Image.LANCZOS)
        fig.save(os.path.join(fdir, f"{i:04d}.png"))
    w, h = size
    x = f"main_w-overlay_w-{30 if portrait else MARGIN[0]}"
    y = f"main_h-overlay_h-{100 if portrait else MARGIN[1]}"
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error",
                    "-loop", "1", "-framerate", str(FPS), "-i", slide_png,
                    "-framerate", str(FPS), "-i", os.path.join(fdir, "%04d.png"),
                    "-filter_complex",
                    f"[0:v]scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2[bg];"
                    f"[bg][1:v]overlay={x}:{y}:shortest=1,format=yuv420p",
                    "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "20", "-an", out_mp4], check=True)
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
