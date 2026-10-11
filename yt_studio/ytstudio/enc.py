"""H.264 encoder selection: use the laptop's GPU encoder when ffmpeg can really use it, else libx264.

config.json: "gpu": "auto" (default) | "off" | "nvenc" | "qsv" | "amf"
"""
import os
import subprocess
import tempfile

_GPU = {
    "nvenc": ["-c:v", "h264_nvenc", "-preset", "p4", "-rc", "vbr", "-cq", "23", "-b:v", "0"],
    "qsv": ["-c:v", "h264_qsv", "-global_quality", "23", "-look_ahead", "0"],
    "amf": ["-c:v", "h264_amf", "-quality", "speed", "-rc", "cqp", "-qp_i", "22", "-qp_p", "22"],
}
_CPU = ["-c:v", "libx264", "-preset", "veryfast"]
_cache = {}


def _works(ffmpeg, args):
    out = os.path.join(tempfile.gettempdir(), "_enc_probe.mp4")
    try:
        r = subprocess.run([ffmpeg, "-y", "-loglevel", "error", "-f", "lavfi", "-i", "color=c=black:s=320x240:r=30", "-t", "0.5",
                            *args, "-pix_fmt", "yuv420p", out], capture_output=True, timeout=30)
        return r.returncode == 0 and os.path.getsize(out) > 0
    except Exception:
        return False


def detect(cfg, log=print):
    """Name of the usable GPU encoder ('nvenc'/'qsv'/'amf') or None. Probed once per process."""
    mode = str(cfg.get("gpu", "auto") or "auto").lower()
    ffmpeg = cfg.get("ffmpeg", "ffmpeg")
    key = (ffmpeg, mode)
    if key in _cache:
        return _cache[key]
    found = None
    if mode != "off":
        for name in ([mode] if mode in _GPU else list(_GPU)):
            if _works(ffmpeg, _GPU[name]):
                found = name
                break
    _cache[key] = found
    log(f"[encoder] GPU: {found or 'none - using CPU libx264'}")
    return found


def h264(cfg, crf=20):
    """ffmpeg video-codec args for the chosen encoder (quality roughly equal to libx264 crf 20)."""
    g = detect(cfg)
    return list(_GPU[g]) if g else _CPU + ["-crf", str(crf)]
