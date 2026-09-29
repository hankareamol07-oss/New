"""Assemble slides + narration into MP4 with ffmpeg (main 16:9 video and 9:16 Short)."""
import os
import shutil
import subprocess

from .tts import duration, prefetch, speak

PAUSE_AFTER_Q = 1.6   # seconds of silence between question and answer
PAUSE_AFTER_A = 0.8


def _sec(t):
    m, s = divmod(int(t), 60)
    return f"{m:02d}:{s:02d}"


def _concat(cfg, items, audio_out, video_out, size):
    """items: list of (png, audio_or_None, seconds)."""
    w, h = size
    lst = os.path.join(os.path.dirname(video_out), "_slides.txt")
    with open(lst, "w", encoding="utf-8") as f:
        for png, _, secs in items:
            f.write(f"file '{os.path.abspath(png)}'\nduration {secs:.3f}\n")
        f.write(f"file '{os.path.abspath(items[-1][0])}'\n")
    alst = os.path.join(os.path.dirname(video_out), "_audio.txt")
    silence = os.path.join(os.path.dirname(video_out), "_silence.wav")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "lavfi", "-i", "anullsrc=r=24000:cl=mono", "-t", "10", silence], check=True)
    with open(alst, "w", encoding="utf-8") as f:
        for png, aud, secs in items:
            if aud:
                wav = os.path.splitext(aud)[0] + ".wav"
                if not os.path.exists(wav):
                    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-i", aud, "-ar", "24000", "-ac", "1", wav], check=True)
                f.write(f"file '{os.path.abspath(wav)}'\n")
                gap = secs - duration(cfg, aud)
            else:
                gap = secs
            if gap > 0.05:
                f.write(f"file '{os.path.abspath(silence)}'\ninpoint 0\noutpoint {gap:.3f}\n")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", alst,
                    "-ar", "24000", "-ac", "1", audio_out], check=True)
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", lst, "-i", audio_out,
                    "-vf", f"scale={w}:{h}:force_original_aspect_ratio=decrease,pad={w}:{h}:(ow-iw)/2:(oh-ih)/2,format=yuv420p",
                    "-r", "30", "-c:v", "libx264", "-preset", "medium", "-crf", "20", "-tune", "stillimage",
                    "-c:a", "aac", "-b:a", "160k", "-shortest", "-movflags", "+faststart", video_out], check=True)


def build(cfg, script, renderer, book, out_dir, log=print):
    lang = book["lang"]
    sl = os.path.join(out_dir, "slides")
    au = os.path.join(out_dir, "audio")
    os.makedirs(sl, exist_ok=True)
    os.makedirs(au, exist_ok=True)
    items, chapters, t = [], [], 0.0

    plan = [(script.get("intro", {}).get("say"), "01_intro")]
    for seg in script["segments"]:
        n = int(seg.get("no", 0)) + 1
        plan += [(seg.get("say_q"), f"{n:02d}a_q"), (seg.get("say_a"), f"{n:02d}b_a")]
    plan += [(script.get("summary", {}).get("say"), "98_summary"), (script.get("outro", {}).get("say"), "99_outro")]
    prefetch(cfg, [(s, os.path.join(au, n + ".mp3")) for s, n in plan if s], lang, log)

    def add(img, name, say=None, extra=0.0):
        nonlocal t
        png = os.path.join(sl, name + ".png")
        img.save(png)
        aud = None
        secs = extra
        if say:
            aud = os.path.join(au, name + ".mp3")
            speak(cfg, say, aud, lang, log)
            secs += duration(cfg, aud)
        secs = max(secs, 2.0)
        items.append((png, aud, secs))
        start = t
        t += secs
        return start

    log("[video] title/intro")
    add(renderer.title(script), "00_title", script["intro"]["say"][:0] or None, extra=2.5)
    intro = script.get("intro", {})
    chapters.append((0, intro.get("slide_title", "Intro")))
    add(renderer.points(intro.get("slide_title", ""), intro.get("points", []), badge="या व्हिडिओमध्ये" if lang != "en" else "In this video"),
        "01_intro", intro.get("say"), extra=0.8)
    for seg in script["segments"]:
        n = int(seg.get("no", len(chapters)))
        log(f"[video] question {n}")
        st = add(renderer.question(seg, False), f"{n + 1:02d}a_q", seg.get("say_q"), extra=PAUSE_AFTER_Q)
        chapters.append((st, f"प्रश्न {n}" if lang != "en" else f"Question {n}"))
        add(renderer.question(seg, True), f"{n + 1:02d}b_a", seg.get("say_a"), extra=PAUSE_AFTER_A)
    summ = script.get("summary", {})
    st = add(renderer.points("सारांश" if lang != "en" else "Summary", summ.get("points", []), badge="लक्षात ठेवा" if lang != "en" else "Remember"),
             "98_summary", summ.get("say"), extra=0.8)
    chapters.append((st, "सारांश" if lang != "en" else "Summary"))
    add(renderer.title(script), "99_outro", script.get("outro", {}).get("say"), extra=1.5)

    mp4 = os.path.join(out_dir, "video.mp4")
    log("[video] encoding main video ...")
    _concat(cfg, items, os.path.join(out_dir, "narration.m4a").replace(".m4a", ".wav"), mp4, (1920, 1080))
    script["chapters_text"] = "\n".join(f"{_sec(s)} {title}" for s, title in chapters)
    script["duration_sec"] = round(t, 1)

    short_mp4 = None
    sh = script.get("short") or {}
    if cfg.get("make_short") and script["segments"]:
        idx = next((i for i, s in enumerate(script["segments"]) if s.get("no") == sh.get("segment_no")), 0)
        seg = script["segments"][idx]
        log("[video] Short")
        sn = int(seg.get("no", idx)) + 1
        for name, src in (("s1", f"{sn:02d}a_q"), ("s2", f"{sn:02d}b_a")):  # reuse the main video's narration
            src = os.path.join(au, src + ".mp3")
            if os.path.exists(src) and not os.path.exists(os.path.join(au, name + ".mp3")):
                shutil.copy(src, os.path.join(au, name + ".mp3"))
        sitems = []
        for name, show, say, extra in (("s1", False, seg.get("say_q"), 1.5), ("s2", True, seg.get("say_a"), 1.5)):
            png = os.path.join(sl, name + ".png")
            renderer.short(seg, sh.get("hook", ""), show).save(png)
            aud = os.path.join(au, name + ".mp3")
            speak(cfg, say, aud, lang, log)
            sitems.append((png, aud, duration(cfg, aud) + extra))
        short_mp4 = os.path.join(out_dir, "short.mp4")
        _concat(cfg, sitems, os.path.join(out_dir, "short_narration.wav"), short_mp4, (1080, 1920))
    return mp4, short_mp4
