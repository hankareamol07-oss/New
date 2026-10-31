"""Text-to-speech with per-line cache. edge-tts (free, no key) by default."""
import asyncio
import hashlib
import os
import subprocess

from . import paths

VOICES = {   # language -> [(voice id, label)]
    "mr": [("mr-IN-AarohiNeural", "आरोही (स्त्री)"), ("mr-IN-ManoharNeural", "मनोहर (पुरुष)")],
    "hi": [("hi-IN-SwaraNeural", "स्वरा (स्त्री)"), ("hi-IN-MadhurNeural", "मधुर (पुरुष)")],
    "en": [("en-IN-NeerjaNeural", "Neerja (female)"), ("en-IN-NeerjaExpressiveNeural", "Neerja expressive (female)"),
           ("en-IN-PrabhatNeural", "Prabhat (male)")],
}
# extra "characters" from one voice: pitch/rate shifts give child / elder / second male-female variants
STYLES = {
    "normal": dict(rate="-4%", pitch="+0Hz"),
    "child": dict(rate="+2%", pitch="+45Hz"),
    "elder": dict(rate="-14%", pitch="-18Hz"),
    "deep": dict(rate="-8%", pitch="-30Hz"),
    "bright": dict(rate="+0%", pitch="+18Hz"),
    "narrator": dict(rate="-8%", pitch="-4Hz"),
}


def default_voice(lang, gender="female"):
    vs = VOICES.get(lang, VOICES["en"])
    want = "पुरुष" if gender == "male" else "स्त्री"
    for v, lab in vs:
        if want in lab or (gender == "male" and "male)" in lab and "female" not in lab) or (gender != "male" and "female" in lab):
            return v
    return vs[0][0]


def voice_gender(voice):
    for vs in VOICES.values():
        for v, lab in vs:
            if v == voice:
                return "male" if ("पुरुष" in lab or "(male)" in lab) else "female"
    return "male" if any(k in voice for k in ("Manohar", "Madhur", "Prabhat")) else "female"


VARIANTS = {"female": ["normal", "bright", "child", "elder"], "male": ["normal", "deep", "elder", "child"]}


def auto_cast(lang, cast, narrator=None):
    """Give every speaker without a chosen voice one that matches its gender. Speakers of the same gender
    get the next voice of that gender, then pitch/rate variants, so no two sound alike.
    -> {speaker id: (voice, style)} including "narrator"."""
    pools = {g: [v for v, _ in VOICES.get(lang, VOICES["en"]) if voice_gender(v) == g] for g in ("female", "male")}
    used = set()
    out = {}
    nar = narrator or {}
    nv = nar.get("voice") or (pools["female"] or pools["male"])[0]
    out["narrator"] = (nv, nar.get("style", "narrator"))
    used.add(out["narrator"])
    for cid, c in cast.items():
        if c.get("voice"):
            out[cid] = (c["voice"], c.get("style", "normal"))
            used.add(out[cid])
    for cid, c in cast.items():
        if cid in out:
            continue
        g = "male" if c.get("gender") == "male" else "female"
        pool = pools[g] or pools["female"] or pools["male"]
        pick = None
        for st in ([c["style"]] if c.get("style") else VARIANTS[g]):
            for v in pool:
                if (v, st) not in used:
                    pick = (v, st)
                    break
            if pick:
                break
        out[cid] = pick or (pool[0], c.get("style", "normal"))
        used.add(out[cid])
    return out


def _key(voice, style, text):
    return hashlib.sha1(f"{voice}|{style}|{text}".encode("utf8")).hexdigest()[:16]


def speak(text, voice, style="normal", log=print):
    """-> path of a cached mp3 for this line (re-renders reuse it)."""
    os.makedirs(paths.CACHE, exist_ok=True)
    out = os.path.join(paths.CACHE, _key(voice, style, text) + ".mp3")
    if os.path.exists(out) and os.path.getsize(out) > 500:
        return out
    import edge_tts
    st = STYLES.get(style, STYLES["normal"])
    tmp = out + ".part"

    async def go():
        await edge_tts.Communicate(text, voice, rate=st["rate"], pitch=st["pitch"]).save(tmp)
    for attempt in range(3):
        try:
            asyncio.run(go())
            os.replace(tmp, out)
            return out
        except Exception as e:          # network hiccup: retry
            log(f"TTS retry {attempt + 1}: {e}")
    raise RuntimeError("TTS failed: " + text[:40])


def pcm(path, rate=24000, ffmpeg="ffmpeg", trim=True):
    """Decode any audio file to mono float32 samples, trimming TTS lead/tail silence."""
    import numpy as np
    r = subprocess.run([ffmpeg, "-v", "error", "-i", path, "-f", "f32le", "-ac", "1", "-ar", str(rate), "-"],
                       capture_output=True, check=True)
    x = np.frombuffer(r.stdout, dtype=np.float32).copy()
    loud = np.flatnonzero(np.abs(x) > 0.02)
    if trim and len(loud):
        pad = int(rate * 0.06)
        x = x[max(0, loud[0] - pad):loud[-1] + pad]
    return x
