"""Animated stick-man teacher for yt_explain slides (Pillow only).

A slide becomes a short looping clip: the figure stands bottom-right, gestures according to the slide type
(wave / point / talk / think / cheer / sway) and its mouth moves while narration plays.
compose(...) draws one frame; clip(...) renders the frames and encodes them with ffmpeg; concat_clips(...)
joins all clips + narration into the final MP4 (replaces ytstudio.video._concat for stickman mode).
"""
import math
import os
import subprocess

from PIL import Image, ImageDraw

FPS = 8            # animation frame rate (stick figure loop)
FRAMES = 16        # frames per loop (2 s)
HEIGHT = 330       # figure height on a 1920x1080 slide
MARGIN = (60, 90)  # right / bottom margin (above the accent footer)

POSES = ("wave", "point", "talk", "think", "cheer", "sway")


def _pt(x, y, ang, ln):
    return x + ln * math.cos(math.radians(ang)), y + ln * math.sin(math.radians(ang))


def figure(pose, phase, h=HEIGHT, color=(35, 45, 90), accent=(255, 168, 0), talking=True):
    """RGBA image of the stick man. phase in [0,1) = position in the loop."""
    s = 3  # supersample for smooth lines
    H = h * s
    W = int(h * 0.9) * s
    im = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    lw = max(4, int(H * 0.035))
    w = math.sin(phase * 2 * math.pi)                      # -1..1 slow wobble
    bob = int(H * 0.012 * w)
    cx = W // 2 + (int(H * 0.03 * w) if pose == "sway" else 0)
    r = H * 0.12                                           # head radius
    hy = H * 0.16 + bob                                    # head centre y
    neck = hy + r
    hip = neck + H * 0.36
    sh = neck + H * 0.05                                   # shoulder y
    arm, fore, leg = H * 0.17, H * 0.15, H * 0.22
    # legs
    spread = 0.15 + (0.05 * w if pose in ("sway", "cheer") else 0)
    d.line([cx, hip, cx - H * spread, hip + leg + H * 0.06], fill=color, width=lw)
    d.line([cx, hip, cx + H * spread, hip + leg + H * 0.06], fill=color, width=lw)
    # body
    d.line([cx, neck, cx, hip], fill=color, width=lw)
    # arms (angles: 0 = right, 90 = down)
    if pose == "wave":
        l1, l2 = (120, 100), (-70, -110 + 40 * w)            # left arm down, right arm up waving
    elif pose == "point":
        l1, l2 = (115, 95), (200 + 6 * w, 190 + 8 * w)        # right arm pointing at the slide (left side)
    elif pose == "think":
        l1, l2 = (110, 95), (-100, 180 + 15 * w)              # right hand to chin
    elif pose == "cheer":
        l1, l2 = (-120 + 10 * w, -100 + 15 * w), (-60 - 10 * w, -80 - 15 * w)
    else:  # talk / sway: gentle arm movement
        l1, l2 = (120 + 8 * w, 100 + 12 * w), (60 - 8 * w, 70 - 12 * w)
    for side, (a1, a2) in ((-1, l1), (1, l2)):
        ex, ey = _pt(cx, sh, a1, arm)
        hx, hy2 = _pt(ex, ey, a2, fore)
        d.line([cx, sh, ex, ey], fill=color, width=lw)
        d.line([ex, ey, hx, hy2], fill=color, width=lw)
        d.ellipse([hx - lw, hy2 - lw, hx + lw, hy2 + lw], fill=color)
    if pose == "point":
        ex, ey = _pt(cx, sh, l2[0], arm)
        hx, hy2 = _pt(ex, ey, l2[1], fore)
        d.line([hx, hy2, hx - lw * 3, hy2 - lw], fill=accent, width=lw)  # pointer tip
    # head
    d.ellipse([cx - r, hy - r, cx + r, hy + r], fill=(255, 224, 189), outline=color, width=lw)
    # eyes
    er = lw * 0.9
    blink = (phase * FRAMES) % FRAMES < 1
    for ex in (cx - r * 0.38, cx + r * 0.38):
        ey = hy - r * 0.15
        if blink:
            d.line([ex - er, ey, ex + er, ey], fill=color, width=lw // 2)
        else:
            d.ellipse([ex - er, ey - er, ex + er, ey + er], fill=color)
    # mouth
    my = hy + r * 0.45
    if talking and pose != "think":
        openness = r * (0.12 + 0.22 * abs(math.sin(phase * 2 * math.pi * 3)))
        d.ellipse([cx - r * 0.3, my - openness / 2, cx + r * 0.3, my + openness / 2], fill=(120, 40, 40))
    elif pose == "think":
        d.line([cx - r * 0.25, my, cx + r * 0.25, my], fill=color, width=lw // 2)
    else:
        d.arc([cx - r * 0.35, my - r * 0.3, cx + r * 0.35, my + r * 0.15], 20, 160, fill=color, width=lw // 2)
    # simple tie / scarf in accent colour
    d.polygon([(cx - lw, neck + lw), (cx + lw, neck + lw), (cx, neck + H * 0.12)], fill=accent)
    return im.resize((W // s, H // s), Image.LANCZOS)


def compose(slide, pose, phase, talking=True, height=HEIGHT):
    """Slide PNG (RGB) + stick man bottom-right -> new RGB image."""
    portrait = slide.height > slide.width
    if portrait:
        height = min(height, 200)
    fig = figure(pose, phase, height, talking=talking)
    out = slide.convert("RGBA")
    x = out.width - fig.width - (30 if portrait else MARGIN[0])
    y = out.height - fig.height - (100 if portrait else MARGIN[1])
    out.alpha_composite(fig, (x, y))
    return out.convert("RGB")


def clip(cfg, slide_png, pose, secs, out_mp4, size, talking=True, height=HEIGHT):
    """Render the loop frames of one slide and encode a silent clip of `secs` seconds."""
    slide = Image.open(slide_png).convert("RGB")
    fdir = os.path.splitext(slide_png)[0] + "_f"
    os.makedirs(fdir, exist_ok=True)
    for i in range(FRAMES):
        compose(slide, pose, i / FRAMES, talking, height).save(os.path.join(fdir, f"{i:02d}.png"))
    w, h = size
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-stream_loop", "-1", "-framerate", str(FPS),
                    "-i", os.path.join(fdir, "%02d.png"), "-t", f"{secs:.3f}",
                    "-vf", f"scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2,format=yuv420p",
                    "-r", "30", "-c:v", "libx264", "-preset", "veryfast", "-crf", "20", "-an", out_mp4], check=True)
    return out_mp4


def concat_clips(cfg, items, audio_out, video_out, size):
    """items: list of (png, audio_or_None, seconds, pose). Same audio handling as ytstudio.video._concat."""
    from ytstudio.tts import duration
    d = os.path.dirname(video_out)
    silence = os.path.join(d, "_silence.wav")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "lavfi", "-i", "anullsrc=r=24000:cl=mono", "-t", "10", silence], check=True)
    alst, vlst = os.path.join(d, "_audio.txt"), os.path.join(d, "_clips.txt")
    with open(alst, "w", encoding="utf-8") as fa, open(vlst, "w", encoding="utf-8") as fv:
        for png, aud, secs, pose in items:
            mp4 = os.path.splitext(png)[0] + ".mp4"
            if not os.path.exists(mp4):
                clip(cfg, png, pose, secs, mp4, size, talking=bool(aud))
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
