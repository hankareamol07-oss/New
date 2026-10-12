"""
Animated clips from Google Flow / Veo.

Free route: write a ready Veo prompt (flow_prompt.txt) per video, open Flow in the browser with the prompt on the
clipboard, and pick up clips the user downloads into the video folder (intro.mp4, clip*.mp4, bg.mp4).
Paid route: cfg["veo"] = {"enabled": true, "model": "veo-3.0-fast-generate-001"} renders the same prompt
through the Gemini API (billing required).
"""
import glob
import os
import subprocess
import time
import webbrowser

import requests

FLOW_URL = "https://labs.google/fx/tools/flow"
API = "https://generativelanguage.googleapis.com/v1beta"

INTRO = ("Short 2D educational animation for school children, bright flat style, no text, no letters, no logos. "
         "Topic: {topic} ({subject}, grade {std}). Show one simple, friendly scene that introduces the idea: {hint}. "
         "Smooth camera, cheerful mood, 8 seconds.")
MOTIVATION = ("Cinematic vertical 9:16 background video, no people faces, no text, no letters. Mood: {theme}. "
              "Slow, calm motion (sunrise over hills, a lone runner on a road at dawn, ink spreading in water, "
              "a candle in the dark), soft warm colours, suitable as a backdrop for a Marathi motivational quote. 8 seconds.")


def intro_prompt(topic, subject, std, hint=""):
    return INTRO.format(topic=topic, subject=subject, std=std, hint=hint or "the main object or situation of the lesson")


def motivation_prompt(theme):
    return MOTIVATION.format(theme=theme)


def write_prompt(out_dir, text):
    p = os.path.join(out_dir, "flow_prompt.txt")
    if not os.path.exists(p):
        with open(p, "w", encoding="utf-8") as f:
            f.write(text.strip() + "\n\nSave the clip as intro.mp4 (or bg.mp4 for Shorts background) in this folder and remake the video.\n")
    return p


def open_flow(prompt_text):
    """Copy the prompt to the clipboard (tkinter, no extra deps) and open Flow."""
    try:
        import tkinter
        r = tkinter.Tk()
        r.withdraw()
        r.clipboard_clear()
        r.clipboard_append(prompt_text)
        r.update()
        r.destroy()
    except Exception:
        pass
    webbrowser.open(FLOW_URL)


def find_clips(out_dir, names=("intro",)):
    """User-dropped clips: <name>.mp4 for each name, then clip*.mp4 sorted."""
    out = [os.path.join(out_dir, n + ".mp4") for n in names if os.path.exists(os.path.join(out_dir, n + ".mp4"))]
    return out + sorted(glob.glob(os.path.join(out_dir, "clip*.mp4")))


def _has_audio(cfg, path):
    r = subprocess.run([cfg["ffmpeg"], "-i", path, "-f", "null", "-"], capture_output=True, text=True, encoding="utf-8", errors="replace")
    return "Audio:" in (r.stderr or "")


def normalize(cfg, src, dst, size, max_secs=None):
    """Re-encode a clip to the video's size/fps with an audio track (silent one added if missing) so it can be concatenated."""
    w, h = size
    cmd = [cfg["ffmpeg"], "-y", "-loglevel", "error", "-i", src]
    if _has_audio(cfg, src):
        cmd += ["-map", "0:v:0", "-map", "0:a:0"]
    else:
        cmd += ["-f", "lavfi", "-i", "anullsrc=r=24000:cl=mono", "-map", "0:v:0", "-map", "1:a", "-shortest"]
    if max_secs:
        cmd += ["-t", f"{max_secs:.3f}"]
    cmd += ["-vf", f"scale={w}:{h}:force_original_aspect_ratio=increase,crop={w}:{h},fps=30,format=yuv420p",
            "-c:v", "libx264", "-preset", "veryfast", "-crf", "20", "-c:a", "aac", "-b:a", "160k", "-ar", "24000", "-ac", "1", dst]
    subprocess.run(cmd, check=True)
    return dst


def prepend(cfg, clips, video, size, log=print):
    """Put the clips in front of `video`. Clips are re-encoded to the video's codec settings; the main video is
    stream-copied (no re-encode), so this takes seconds. Returns total seconds added."""
    from ytstudio.tts import duration
    d = os.path.dirname(video)
    parts, added = [], 0.0
    for i, c in enumerate(clips):
        n = os.path.join(d, f"_veo_{i:02d}.mp4")
        normalize(cfg, c, n, size, max_secs=12)
        parts.append(n)
        added += duration(cfg, n)
    main = os.path.join(d, "_veo_main.mp4")
    os.replace(video, main)
    lst = os.path.join(d, "_veo.txt")
    with open(lst, "w", encoding="utf-8") as f:
        for p in parts + [main]:
            f.write(f"file '{os.path.abspath(p)}'\n")
    try:
        subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", lst,
                        "-c", "copy", "-movflags", "+faststart", video], check=True)
    except subprocess.CalledProcessError:
        os.replace(main, video)
        log("[veo] could not join the clip; video left without it")
        return 0.0
    log(f"[veo] {len(clips)} clip(s) added in front ({added:.1f} s)")
    return added


def background(cfg, bg_clip, video, size, log=print):
    """Loop bg_clip under `video`: the darkened clip shows through the slide's gradient (slide at 55 % opacity,
    text stays readable because the clip is dimmed)."""
    w, h = size
    out = video + ".bg.mp4"
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-stream_loop", "-1", "-i", bg_clip, "-i", video,
                    "-filter_complex",
                    f"[0:v]scale={w}:{h}:force_original_aspect_ratio=increase,crop={w}:{h},fps=30,eq=brightness=-0.3:saturation=0.8[bg];"
                    f"[1:v]format=rgba,colorchannelmixer=aa=0.55[fg];[bg][fg]overlay=shortest=1,format=yuv420p[v]",
                    "-map", "[v]", "-map", "1:a", "-c:v", "libx264", "-preset", "veryfast", "-crf", "20", "-c:a", "copy",
                    "-movflags", "+faststart", out], check=True)
    os.replace(out, video)
    log("[veo] background clip applied")


def generate(cfg, prompt_text, out_mp4, log=print, aspect="16:9"):
    """Paid Veo API. Returns out_mp4 or None when disabled / failed."""
    veo = cfg.get("veo") or {}
    keys = cfg.get("keys", {}).get("gemini") or []
    if not veo.get("enabled") or not keys:
        return None
    model = veo.get("model", "veo-3.0-fast-generate-001")
    key = keys[0]
    hdr = {"x-goog-api-key": key}
    try:
        r = requests.post(f"{API}/models/{model}:predictLongRunning", headers=hdr, timeout=60,
                          json={"instances": [{"prompt": prompt_text}],
                                "parameters": {"aspectRatio": aspect, "durationSeconds": 8}})
        r.raise_for_status()
        op = r.json()["name"]
        for _ in range(60):
            time.sleep(10)
            s = requests.get(f"{API}/{op}", headers=hdr, timeout=60)
            s.raise_for_status()
            j = s.json()
            if j.get("done"):
                uri = j["response"]["generateVideoResponse"]["generatedSamples"][0]["video"]["uri"]
                v = requests.get(uri, headers=hdr, timeout=300)
                v.raise_for_status()
                with open(out_mp4, "wb") as f:
                    f.write(v.content)
                log(f"[veo] clip generated: {os.path.basename(out_mp4)}")
                return out_mp4
        log("[veo] timed out waiting for the clip")
    except (requests.RequestException, KeyError, IndexError) as e:
        log(f"[veo] failed ({str(e)[:100]}); continuing without clip")
    return None
