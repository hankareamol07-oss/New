"""Story JSON -> MP4 (+ SRT + thumbnail). Scenes with a background, rigged characters that walk in, talk with
lip-sync from TTS audio, blink, gesture; narrator lines; captions; background music with ducking; fades."""
import json
import math
import os
import random
import shutil
import subprocess
import wave

import numpy as np
from PIL import Image, ImageDraw, ImageFont

from . import bgmaker, library, musicmaker, paths, rig, tts

FPS = 30
SR = 24000
POS = {"far-left": 0.14, "left": 0.28, "center": 0.5, "right": 0.72, "far-right": 0.86}
WALK_IN = 2.0
WALK_OUT = 2.2
GAP = 0.35
COLORS = [(255, 214, 90), (140, 220, 255), (255, 160, 200), (170, 240, 160), (255, 190, 130)]


class Cancelled(Exception):
    pass


def ffmpeg_bin():
    for c in (os.path.join(paths.HERE, "ffmpeg", "ffmpeg.exe"), os.path.join(paths.HERE, "ffmpeg.exe")):
        if os.path.exists(c):
            return c
    return shutil.which("ffmpeg") or "ffmpeg"


def font(size, bold=True, text=None):
    deva = text is None or any("\u0900" <= ch <= "\u097f" for ch in text)
    fam = "NotoSansDevanagari" if deva else "NotoSans"
    f = os.path.join(paths.FONTS, fam + ("-Bold.ttf" if bold else "-Regular.ttf"))
    try:
        return ImageFont.truetype(f, size, layout_engine=ImageFont.Layout.RAQM)
    except Exception:
        return ImageFont.truetype(f, size)


def wrap(text, fnt, max_w):
    lines, cur = [], ""
    for w in text.split():
        t = (cur + " " + w).strip()
        if fnt.getlength(t) <= max_w or not cur:
            cur = t
        else:
            lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines


def caption_img(W, name, text, color, size):
    fnt, fn = font(size, text=text), font(int(size * 0.8), text=name or "")
    lines = wrap(text, fnt, W * 0.84)
    lh = int(size * 1.45)
    head = int(size * 1.15) if name else 0
    w = int(max([fnt.getlength(s) for s in lines] + [fn.getlength(name or "")]) + size * 1.4)
    h = head + lh * len(lines) + int(size * 0.6)
    im = Image.new("RGBA", (w, h), (0, 0, 0, 0))
    d = ImageDraw.Draw(im)
    d.rounded_rectangle((0, 0, w - 1, h - 1), radius=int(size * 0.45), fill=(15, 20, 35, 200))
    y = int(size * 0.25)
    if name:
        d.text((size * 0.7, y), name, font=fn, fill=color)
        y += head
    for s in lines:
        d.text(((w - fnt.getlength(s)) / 2, y), s, font=fnt, fill=(255, 255, 255), stroke_width=2, stroke_fill=(0, 0, 0))
        y += lh
    return im


def envelope(x):
    """Loudness 0..1 per video frame for a mono clip."""
    n = int(math.ceil(len(x) / SR * FPS))
    step = SR // FPS
    v = np.array([float(np.sqrt(np.mean(x[i * step:(i + 1) * step] ** 2))) if len(x[i * step:(i + 1) * step]) else 0.0
                  for i in range(n)])
    pk = float(np.percentile(v, 95)) if len(v) else 1.0
    v = np.clip(v / max(pk * 0.85, 1e-4), 0, 1)
    out, prev = [], 0.0
    for a in v:
        prev = prev + (a - prev) * 0.6
        out.append(prev)
    return out


def background(spec, W, H):
    p = spec or "village"
    if p in bgmaker.PRESETS:
        f = os.path.join(paths.BGS, p + ".png")
        if not os.path.exists(f):
            bgmaker.make_all(paths.BGS)
        p = f
    elif not os.path.isabs(p):
        p = os.path.join(paths.BGS, p)
    im = Image.open(p).convert("RGB")
    k = max(W / im.width, H / im.height) * 1.08          # 8% margin for slow camera moves
    return im.resize((int(im.width * k + 1), int(im.height * k + 1)), Image.LANCZOS)


def srt_time(t):
    ms = int(round(t * 1000))
    return f"{ms // 3600000:02d}:{ms // 60000 % 60:02d}:{ms // 1000 % 60:02d},{ms % 1000:03d}"


# ----------------------------------------------------------------------------------------------
def plan(story, log=print):
    """TTS every line (cached) and lay out the timeline. Returns (scenes, total secs, voice track)."""
    cast = story.get("cast", {})
    lang = story.get("lang", "mr")
    for cid, c in cast.items():               # gender from the character's manifest when the story doesn't say
        if not c.get("gender"):
            try:
                c["gender"] = library.load_manifest(c.get("character", cid)).get("gender", "female")
            except (OSError, ValueError):
                pass
    voices = tts.auto_cast(lang, cast, story.get("narrator"))
    for who, (v, st) in voices.items():
        log(f"  voice: {who} -> {v} ({st})")
    t = 0.0
    scenes, clips = [], []
    for si, sc in enumerate(story["scenes"]):
        start = t
        chars = sc.get("characters", [])
        if any(c.get("enter") for c in chars):
            t += WALK_IN
        t += 0.3
        lines = []
        for ln in sc.get("lines", []):
            who = ln.get("speaker", "narrator")
            voice, style = voices.get(who) or voices["narrator"]
            log(f"  TTS: {who}: {ln['text'][:40]}")
            mp3 = tts.speak(ln["text"], voice, style, log)
            x = tts.pcm(mp3, SR, ffmpeg_bin())
            dur = len(x) / SR
            lines.append(dict(ln, start=t, end=t + dur, env=envelope(x)))
            clips.append((t, x))
            t += dur + GAP
        if any(c.get("exit") for c in chars):
            t += WALK_OUT
        t += float(sc.get("hold", 0.5))
        scenes.append(dict(sc, start=start, end=t, lines=lines, index=si))
    total = t
    track = np.zeros(int(total * SR) + SR, dtype=np.float32)
    for st, x in clips:
        i = int(st * SR)
        track[i:i + len(x)] += x
    return scenes, total, track


def music_path(music):
    """Preset name ("calm"), file name in library/music, or full path -> existing file or None."""
    if not music or music == "none":
        return None
    if os.path.isabs(music) and os.path.exists(music):
        return music
    for c in (os.path.join(paths.MUSIC, music), os.path.join(paths.MUSIC, music + ".wav")):
        if os.path.exists(c):
            return c
    if music in musicmaker.PRESETS:
        musicmaker.make_all(paths.MUSIC)
        return os.path.join(paths.MUSIC, music + ".wav")
    return None


def mix_music(track, music, gain=0.16, duck=0.05):
    music = music_path(music)
    if not music:
        return track
    m = tts.pcm(music, SR, ffmpeg_bin(), trim=False)
    if not len(m):
        return track
    m = np.tile(m, int(len(track) / len(m)) + 1)[:len(track)]
    # duck under the voice: smoothed voice activity
    win = SR // 10
    act = np.convolve(np.abs(track), np.ones(win) / win, mode="same") > 0.01
    g = np.where(act, duck, gain).astype(np.float32)
    g = np.convolve(g, np.ones(SR // 4) / (SR // 4), mode="same").astype(np.float32)
    fade = np.minimum(1, np.minimum(np.arange(len(m)) / SR, (len(m) - np.arange(len(m))) / (SR * 2)))
    return track + m / max(1e-4, np.abs(m).max()) * g * fade


def write_wav(path, x):
    x = np.clip(x, -1, 1)
    with wave.open(path, "wb") as w:
        w.setnchannels(1)
        w.setsampwidth(2)
        w.setframerate(SR)
        w.writeframes((x * 32767).astype("<i2").tobytes())


def get_rig(name, cache=None):
    cache = _RIGS if cache is None else cache
    if name not in cache:
        cache[name] = rig.Rig(os.path.join(paths.CHARS, name))
    return cache[name]


_RIGS = {}


class Actor:
    def __init__(self, cid, spec, cast, W, H, shorts, rigs=None):
        c = cast.get(cid, {})
        self.id = cid
        self.name = c.get("name", cid)
        self.rig = get_rig(c.get("character", cid), rigs)
        self.sc = float(spec.get("scale", 1.0))
        hpx = H * (0.40 if shorts else 0.60) * self.sc
        self.k = hpx / self.rig.height
        self.x = POS.get(spec.get("position", "center"), 0.5) if isinstance(spec.get("position"), str) else float(spec["position"])
        self.enter, self.exit = spec.get("enter"), spec.get("exit")
        self.face = spec.get("face")              # "left"/"right" override
        self.hpx = hpx
        rnd = random.Random(hash(cid) & 0xffff)
        self.blinks, b = [], rnd.uniform(0.8, 2.0)
        while b < 600:
            self.blinks.append(b)
            b += rnd.uniform(2.4, 5.0)

    def blinking(self, t):
        for b in self.blinks:
            if b <= t < b + 0.13:
                return True
            if b > t:
                break
        return False


def render(story, out_mp4, shorts=False, log=print, progress=None, cancel=None):
    W, H = (1080, 1920) if shorts else (1920, 1080)
    ff = ffmpeg_bin()
    log("Voices ...")
    scenes, total, track = plan(story, log)
    track = mix_music(track, story.get("music"), float(story.get("music_volume", 0.16)))
    os.makedirs(os.path.dirname(os.path.abspath(out_mp4)), exist_ok=True)
    wav = out_mp4 + ".wav"
    write_wav(wav, track[:int(total * SR)])
    cast = story.get("cast", {})
    names = list(cast)
    cap_size = 48 if shorts else 54
    feet_y = H * (0.70 if shorts else 0.82)
    cap_y = H * (0.74 if shorts else 0.845)
    srt = []
    proc = subprocess.Popen([ff, "-y", "-loglevel", "error", "-f", "rawvideo", "-pix_fmt", "rgb24", "-s", f"{W}x{H}",
                             "-r", str(FPS), "-i", "-", "-i", wav, "-c:v", "libx264", "-preset", "medium", "-crf", "19",
                             "-pix_fmt", "yuv420p", "-c:a", "aac", "-b:a", "160k", "-shortest", "-movflags", "+faststart",
                             out_mp4], stdin=subprocess.PIPE)
    nframes = int(total * FPS)
    thumb_at = None
    title_img = caption_img(W, None, story.get("title", ""), None, int(cap_size * 1.35)) if story.get("title") else None
    try:
        for sc in scenes:
            bg = background(sc.get("background"), W, H)
            actors = [Actor(c["id"], c, cast, W, H, shorts) for c in sc.get("characters", [])]
            caps = {}
            for li, ln in enumerate(sc["lines"]):
                who = ln.get("speaker", "narrator")
                nm = "" if who == "narrator" else cast.get(who, {}).get("name", who)
                col = COLORS[names.index(who) % len(COLORS)] if who in names else (220, 220, 220)
                caps[li] = caption_img(W, nm, ln["text"], col, cap_size)
                srt.append((ln["start"], ln["end"], (nm + ": " if nm else "") + ln["text"]))
            first = sc["lines"][0]["start"] if sc["lines"] else sc["start"]
            last = sc["lines"][-1]["end"] if sc["lines"] else sc["end"]
            f0, f1 = int(sc["start"] * FPS), min(nframes, int(sc["end"] * FPS))
            zoom = sc.get("camera", "slow-zoom")
            for fi in range(f0, f1):
                if cancel and cancel():
                    raise Cancelled()
                t = fi / FPS
                ts = t - sc["start"]
                k = (t - sc["start"]) / max(0.1, sc["end"] - sc["start"])
                # camera: gentle Ken Burns on the background
                z = 1.0 + (0.05 * k if zoom == "slow-zoom" else 0.0)
                cw, ch = W / z, H / z
                cx = bg.width / 2 + (W * 0.03 * (k - 0.5) if zoom == "pan" else 0)
                cy = bg.height / 2
                frame = bg.resize((W, H), Image.BILINEAR, box=(cx - cw / 2, cy - ch / 2, cx + cw / 2, cy + ch / 2))
                frame = frame.convert("RGBA")
                speaking = None
                cur = None
                for li, ln in enumerate(sc["lines"]):
                    if ln["start"] - 0.05 <= t < ln["end"] + GAP * 0.8:
                        cur = li
                    if ln["start"] <= t < ln["end"]:
                        speaking = (li, ln)
                for a in actors:
                    tx = a.x * W
                    x, walk, facing_dir = tx, None, None
                    if a.enter and ts < WALK_IN:
                        sx = -0.12 * W if a.enter == "left" else 1.12 * W
                        p = min(1.0, ts / WALK_IN)
                        pe = p * p * (3 - 2 * p) * 0.25 + p * 0.75
                        x = sx + (tx - sx) * pe
                        walk = (abs(x - sx) / (a.hpx * 0.55)) * math.pi if p < 1 else None
                        facing_dir = "right" if a.enter == "left" else "left"
                    elif a.exit and t > last + 0.3:
                        p = min(1.0, (t - last - 0.3) / WALK_OUT)
                        ex = -0.15 * W if a.exit == "left" else 1.15 * W
                        x = tx + (ex - tx) * p
                        walk = (abs(x - tx) / (a.hpx * 0.55)) * math.pi
                        facing_dir = a.exit
                    if facing_dir is None:
                        facing_dir = a.face or ("right" if a.x < 0.5 else "left")
                    flip = (a.rig.sgn > 0) == (facing_dir == "right")
                    mouth, gesture, gk = 0.0, "idle", 0.0
                    if speaking and speaking[1].get("speaker") == a.id and walk is None:
                        li, ln = speaking
                        e = ln["env"]
                        j = int((t - ln["start"]) * FPS)
                        mouth = e[j] if j < len(e) else 0.0
                        gesture = ln.get("action") or "talk"
                        dd = ln["end"] - ln["start"]
                        tt = t - ln["start"]
                        gk = min(1.0, tt / 0.6, max(0.0, (dd - tt) / 0.6))
                        if gesture in ("point", "wave", "think", "greet") and dd > 3.5:
                            gk = min(gk, max(0.0, (2.8 - tt) / 0.6)) if tt > 2.2 else gk
                    img, (ax, ay) = a.rig.frame(t, mouth=mouth, blink=a.blinking(t), gesture=gesture, gk=gk,
                                                walk=walk, scale=a.k, flip=flip)
                    frame.alpha_composite(img, (int(x - ax), int(feet_y - ay)))
                if cur is not None:
                    ci = caps[cur]
                    frame.alpha_composite(ci, ((W - ci.width) // 2, int(cap_y)))
                    if thumb_at is None and speaking:
                        thumb_at = fi
                if title_img is not None and sc["index"] == 0 and t < first - 0.1:
                    frame.alpha_composite(title_img, ((W - title_img.width) // 2, int(H * 0.06)))
                rgb = frame.convert("RGB")
                fade = sc.get("transition", "fade") == "fade"
                if fade:
                    a_in = min(1.0, (t - sc["start"]) / 0.4) if sc["index"] > 0 else min(1.0, t / 0.3)
                    a_out = min(1.0, (sc["end"] - t) / 0.4)
                    a = min(a_in, a_out)
                    if a < 1.0:
                        rgb = Image.blend(Image.new("RGB", (W, H)), rgb, max(0.0, a))
                if fi == thumb_at:
                    rgb.save(os.path.splitext(out_mp4)[0] + ".jpg", quality=92)
                proc.stdin.write(rgb.tobytes())
                if progress:
                    progress(fi + 1, nframes)
        proc.stdin.close()
        proc.wait()
    except BaseException:
        try:
            proc.stdin.close()
        except Exception:
            pass
        proc.kill()
        raise
    finally:
        if os.path.exists(wav):
            os.remove(wav)
    with open(os.path.splitext(out_mp4)[0] + ".srt", "w", encoding="utf8") as f:
        for i, (a, b, s) in enumerate(srt, 1):
            f.write(f"{i}\n{srt_time(a)} --> {srt_time(b)}\n{s}\n\n")
    log(f"done: {out_mp4} ({total:.1f}s)")
    return out_mp4


def preview(story, si=0, W=960, H=540, line=0, rigs=None):
    """Still of scene si (characters standing at their marks, caption of `line`) for the editor - no TTS."""
    shorts = H > W
    sc = story["scenes"][si]
    bg = background(sc.get("background"), W, H)
    frame = bg.crop(((bg.width - W) // 2, (bg.height - H) // 2, (bg.width + W) // 2, (bg.height + H) // 2)).convert("RGBA")
    cast = story.get("cast", {})
    names = list(cast)
    lines = sc.get("lines") or []
    speaker = lines[line].get("speaker") if 0 <= line < len(lines) else None
    feet_y = H * (0.70 if shorts else 0.82)
    for c in sc.get("characters", []):
        try:
            a = Actor(c["id"], c, cast, W, H, shorts, rigs)
        except (OSError, KeyError, ValueError):
            continue
        facing = a.face or ("right" if a.x < 0.5 else "left")
        talking = speaker == a.id
        img, (ax, ay) = a.rig.frame(0.0, mouth=0.5 if talking else 0.0,
                                    gesture=(lines[line].get("action") or "talk") if talking else "idle", gk=1.0 if talking else 0.0,
                                    scale=a.k, flip=(a.rig.sgn > 0) == (facing == "right"))
        frame.alpha_composite(img, (int(a.x * W - ax), int(feet_y - ay)))
    if 0 <= line < len(lines):
        ln = lines[line]
        who = ln.get("speaker", "narrator")
        nm = "" if who == "narrator" else cast.get(who, {}).get("name", who)
        col = COLORS[names.index(who) % len(COLORS)] if who in names else (220, 220, 220)
        ci = caption_img(W, nm, ln.get("text", ""), col, int(H * (0.025 if shorts else 0.05)))
        frame.alpha_composite(ci, ((W - ci.width) // 2, int(H * (0.74 if shorts else 0.845))))
    return frame.convert("RGB")


def load(path):
    with open(path, encoding="utf8") as f:
        return json.load(f)
