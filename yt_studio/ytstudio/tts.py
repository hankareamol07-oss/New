"""Narration: edge-tts (free Microsoft neural voices, mr/hi/en-IN), Gemini TTS, or ElevenLabs (cloned voice,
several API keys rotated when one runs out of credits). Writes mp3/wav per segment."""
import asyncio
import base64
import json
import os
import re
import subprocess
import time
import traceback

import requests

from . import llm


def clean(text):
    text = "" if text is None else str(text)
    text = re.sub(r"_{2,}", " रिकामी जागा " if re.search(r"[\u0900-\u097F]", text) else " blank ", text)
    text = re.sub(r"[*#`>]+", "", text)
    return re.sub(r"\s+", " ", text).strip()


def _edge(text, out, voice, rate):
    import edge_tts

    async def run():
        com = edge_tts.Communicate(text, voice, rate=rate)
        await com.save(out)

    asyncio.run(run())


_GEMINI_DAY_DEAD = set()   # keys whose free daily TTS quota is used up (no point retrying until tomorrow)


class GeminiQuota(Exception):
    pass


def _gemini(cfg, text, out, log=print):
    """gemini_tts_voice may be one name or a list: first voice is used, later ones only if it fails."""
    v = cfg["gemini_tts_voice"]
    voices = v if isinstance(v, list) else [v]
    live = [k for k in llm._keys(cfg, "gemini") if k not in _GEMINI_DAY_DEAD]
    if not live:
        raise GeminiQuota("gemini TTS daily quota used up for all keys - try again tomorrow")
    for voice in voices:
        body = {
            "contents": [{"parts": [{"text": text}]}],
            "generationConfig": {
                "responseModalities": ["AUDIO"],
                "speechConfig": {"voiceConfig": {"prebuiltVoiceConfig": {"voiceName": voice}}},
            },
        }
        for attempt in range(4):
            for key in live:
                url = f"https://generativelanguage.googleapis.com/v1beta/models/{cfg['gemini_tts_model']}:generateContent"
                r = requests.post(url, json=body, headers={"x-goog-api-key": key}, timeout=300)
                if r.status_code == 200 or not llm._quota(r.status_code):
                    break
                if "per_day" in r.text or "PerDay" in r.text:
                    _GEMINI_DAY_DEAD.add(key)
                    log(f"  [tts] gemini key ...{key[-4:]}: daily TTS quota used up")
                llm._rotate("gemini")
            live = [k for k in live if k not in _GEMINI_DAY_DEAD]
            if not live:
                raise GeminiQuota("gemini TTS daily quota used up for all keys - try again tomorrow")
            if r.status_code != 429:
                break
            m = re.search(r'"retryDelay":\s*"(\d+)', r.text or "")
            wait = min(int(m.group(1)) + 2 if m else 20 * (attempt + 1), 90)
            log(f"  [tts] gemini rate limit, waiting {wait}s")
            time.sleep(wait)
        if r.status_code == 200:
            break
    r.raise_for_status()
    part = r.json()["candidates"][0]["content"]["parts"][0]["inlineData"]
    pcm = base64.b64decode(part["data"])
    raw = out + ".pcm"
    with open(raw, "wb") as f:
        f.write(pcm)
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "s16le", "-ar", "24000", "-ac", "1", "-i", raw, out], check=True)
    os.remove(raw)


def _sarvam(cfg, text, out, lang):
    """Sarvam AI Bulbul TTS (Indic-native; mr-IN/hi-IN/en-IN). Long text is split at sentence ends (2500-char limit) and concatenated."""
    sv = cfg["sarvam"]
    keys = llm._keys(cfg, "sarvam")
    lang_code = {"mr": "mr-IN", "hi": "hi-IN", "en": "en-IN"}.get(lang, "mr-IN")
    chunks, cur = [], ""
    for s in re.split(r"(?<=[।.!?])\s+", text):
        if len(cur) + len(s) + 1 > 2400 and cur:
            chunks.append(cur)
            cur = ""
        cur = (cur + " " + s).strip()
    chunks.append(cur or ".")
    parts = []
    for i, chunk in enumerate(chunks):
        body = {"text": chunk, "language_code": lang_code, "speaker": sv["speaker"], "model": sv["model"],
                "pace": sv["pace"], "speech_sample_rate": 24000, "output_audio_codec": "wav"}
        for key in keys:
            r = requests.post("https://api.sarvam.ai/text-to-speech", json=body,
                              headers={"api-subscription-key": key, "Content-Type": "application/json"}, timeout=120)
            if r.status_code == 200 or not llm._quota(r.status_code):
                break
            llm._rotate("sarvam")
        if r.status_code != 200:
            raise RuntimeError(f"sarvam HTTP {r.status_code}: {r.text[:120]}")
        p = f"{out}.{i}.wav"
        with open(p, "wb") as f:
            f.write(base64.b64decode(r.json()["audios"][0]))
        parts.append(p)
    lst = out + ".txt"
    with open(lst, "w", encoding="utf-8") as f:
        for p in parts:
            f.write(f"file '{os.path.abspath(p)}'\n")
    subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-f", "concat", "-safe", "0", "-i", lst, out], check=True)
    for p in parts + [lst]:
        os.remove(p)


_EL_DEAD = set()


def _elevenlabs(cfg, text, out, log):
    el = cfg["elevenlabs"]
    keys = [k for k in el["api_keys"] if k and k not in _EL_DEAD]
    if not keys or not el["voice_id"]:
        raise RuntimeError("elevenlabs: no usable api key / voice_id")
    url = f"https://api.elevenlabs.io/v1/text-to-speech/{el['voice_id']}?output_format=mp3_44100_128"
    body = {
        "text": text,
        "model_id": el["model"],
        "voice_settings": {"stability": el["stability"], "similarity_boost": el["similarity_boost"]},
    }
    for key in keys:
        r = requests.post(url, json=body, headers={"xi-api-key": key, "accept": "audio/mpeg"}, timeout=300)
        if r.status_code in (401, 402, 429) or (r.status_code == 400 and "quota" in r.text.lower()):
            log(f"  [tts] elevenlabs key ...{key[-4:]} exhausted/rejected ({r.status_code}), trying next key")
            _EL_DEAD.add(key)
            continue
        r.raise_for_status()
        with open(out, "wb") as f:
            f.write(r.content)
        return
    raise RuntimeError("elevenlabs: all api keys exhausted")


def _silences(cfg, path):
    r = subprocess.run([cfg["ffmpeg"], "-i", path, "-af", "silencedetect=noise=-38dB:d=0.3", "-f", "null", "-"],
                       capture_output=True, text=True, encoding="utf-8", errors="replace")
    err = r.stderr or ""
    starts = [float(x) for x in re.findall(r"silence_start: ([\d.]+)", err)]
    ends = [float(x) for x in re.findall(r"silence_end: ([\d.]+)", err)]
    return list(zip(starts, ends))


def prefetch(cfg, pairs, lang, log=print):
    """Gemini free tier allows ~10 TTS requests/day, so a whole video's narration is synthesised in ONE request
    and cut into per-slide files at the pauses between parts. pairs = [(text, out_path)]; later speak() calls
    find the files ready. Any other backend: no-op (speak() handles each segment)."""
    pairs = [(clean(t), o) for t, o in pairs if isinstance(t, str) and clean(t) and not (os.path.exists(o) and os.path.getsize(o) > 0)]
    if cfg["tts_backend"] != "gemini" or not cfg["keys"].get("gemini") or len(pairs) < 2:
        return
    try:
        _prefetch_gemini(cfg, pairs, lang, log)
    except GeminiQuota as e:
        log(f"  [tts] {e}; using edge-tts for this video")
    except Exception as e:  # any split/ffmpeg problem: per-part speak() still works
        log(f"  [tts] gemini batch failed ({type(e).__name__}: {str(e)[:80]}); per-part fallback")
        log(traceback.format_exc())
        for p in [o for _, o in pairs] + [pairs[0][1] + ".full.mp3"]:
            if os.path.exists(p):
                os.remove(p)


ALIGN_MODEL = "gemini-2.5-flash"
ALIGN_PROMPT = ("The attached audio is a reading of the numbered paragraphs below, in order. For every paragraph give the "
                "timestamp at which its first word is spoken, as a string in the form MM:SS.s (minutes:seconds.tenths). "
                'Reply with JSON only: {"starts": ["00:00.0", "00:12.4", ...]} with exactly one timestamp per paragraph.\n\n')


def _secs(x):
    """'MM:SS.s' / 'H:MM:SS' / plain number -> seconds."""
    parts = [float(p) for p in str(x).strip().split(":")]
    s = 0.0
    for p in parts:
        s = s * 60 + p
    return s


def _align_llm(cfg, full, texts, log):
    """Ask a Gemini text model (separate, much larger free quota than TTS) to time-stamp where each paragraph
    starts in the audio. Returns the n-1 boundary times or None."""
    with open(full, "rb") as f:
        audio = base64.b64encode(f.read()).decode()
    numbered = "\n\n".join(f"{i + 1}. {t}" for i, t in enumerate(texts))
    body = {
        "contents": [{"role": "user", "parts": [{"text": ALIGN_PROMPT + numbered},
                                                {"inlineData": {"mimeType": "audio/mp3", "data": audio}}]}],
        "generationConfig": {"temperature": 0, "responseMimeType": "application/json"},
    }
    for attempt in range(4):
        for key in llm._keys(cfg, "gemini"):
            r = requests.post(f"https://generativelanguage.googleapis.com/v1beta/models/{ALIGN_MODEL}:generateContent",
                              json=body, headers={"x-goog-api-key": key}, timeout=300)
            if r.status_code == 200 or not llm._quota(r.status_code):
                break
            llm._rotate("gemini")
        if r.status_code in (429, 500, 503) and attempt < 3:  # busy / overloaded: wait and retry
            log(f"  [tts] align model HTTP {r.status_code}, retrying in 20s")
            time.sleep(20)
            continue
        break
    if r.status_code != 200:
        log(f"  [tts] align model HTTP {r.status_code}; using pause heuristics")
        return None
    starts = llm._extract_json(r.json()["candidates"][0]["content"]["parts"][0]["text"])["starts"]
    starts = [_secs(x) for x in starts]
    if len(starts) != len(texts) or starts != sorted(starts):
        log(f"  [tts] align model returned {len(starts)} times for {len(texts)} parts; using pause heuristics")
        return None
    return starts[1:]


def _snap(cuts, inner, prev_gap=0.5):
    """cuts = first-word times from the model (accurate to ~0.4 s). Cut just before the end of the pause that
    precedes that word: the pause whose end is nearest to the estimate (within 0.8 s), else 0.3 s before it."""
    out, prev = [], -1.0
    for c in cuts:
        cands = [(abs(b - c), max(a, b - 0.25)) for a, b in inner if a > prev + prev_gap and abs(b - c) <= 0.8]
        cut = min(cands)[1] if cands else max(c - 0.3, prev + prev_gap)
        out.append(cut)
        prev = cut
    return out


def _cut_in(a, b):
    return a + min(0.45, (b - a) / 2)


def _plausible(cuts, lead, tail, weights):
    """Every part's speech time per character must be within a sane band of the median - catches wrong cuts."""
    bounds = [lead] + cuts + [tail]
    rates = [(b - a) / max(w, 1) for a, b, w in zip(bounds, bounds[1:], weights)]
    med = sorted(rates)[len(rates) // 2]
    return all(0.45 * med <= r <= 2.2 * med for r in rates)


def _cuts_longest(inner, n, lead, tail, weights):
    """The n-1 longest pauses are the paragraph breaks (in-sentence pauses are usually shorter)."""
    longest = sorted(inner, key=lambda s: s[1] - s[0], reverse=True)[: n - 1]
    if len(longest) < n - 1 or (longest[-1][1] - longest[-1][0]) < 0.7:
        return None
    cuts = sorted(_cut_in(a, b) for a, b in longest)
    return cuts if _plausible(cuts, lead, tail, weights) else None


def _cuts_weighted(inner, lead, tail, weights):
    """Fallback: expected position from text length, snapped to the nearest pause (>= 0.6 s) inside a window."""
    speech = tail - lead
    long = [(a, b) for a, b in inner if b - a >= 0.6] or inner
    cuts, acc, prev = [], 0, lead
    for w in weights[:-1]:
        acc += w
        target = lead + speech * acc / sum(weights)
        window = 0.35 * speech * w / sum(weights) + 0.4
        cands = [(abs(_cut_in(a, b) - target), _cut_in(a, b)) for a, b in long if a > prev + 0.5 and abs(_cut_in(a, b) - target) <= window]
        cut = min(cands)[1] if cands else target
        cuts.append(cut)
        prev = cut
    return cuts if _plausible(cuts, lead, tail, weights) else None


def _prefetch_gemini(cfg, pairs, lang, log):
    texts = [t if re.search(r"[।.!?]$", t) else t + ("।" if lang != "en" else ".") for t, _ in pairs]
    whole = "\n\n".join(texts)
    full = pairs[0][1] + ".full.mp3"
    log(f"  [tts] gemini: one request for {len(pairs)} parts ({len(whole)} chars)")
    _gemini(cfg, whole, full, log)
    total = duration(cfg, full)
    if total < 0.04 * len(whole):  # Marathi/English speech is ~0.08 s per character; far less = Gemini read only part of the text
        raise RuntimeError(f"gemini read only part of the text ({total:.0f}s for {len(whole)} chars)")
    sil = _silences(cfg, full)
    lead = sil[0][1] if sil and sil[0][0] < 0.05 else 0.0
    tail = sil[-1][0] if sil and abs(sil[-1][1] - total) < 0.05 else total
    inner = [(a, b) for a, b in sil if a > lead + 0.3 and b < tail - 0.3]
    weights = [len(t) for t in texts]
    try:
        cuts = _align_llm(cfg, full, texts, log)
    except (requests.RequestException, llm.LLMError, KeyError, ValueError, json.JSONDecodeError) as e:
        log(f"  [tts] align model failed ({str(e)[:80]}); using pause heuristics")
        cuts = None
    how = "model timestamps"
    if cuts:
        cuts = _snap(cuts, inner)
        if not _plausible(cuts, lead, tail, weights):
            log("  [tts] model timestamps implausible; using pause heuristics")
            cuts = None
    if not cuts:
        cuts, how = _cuts_longest(inner, len(pairs), lead, tail, weights), "longest pauses"
    if not cuts:
        cuts, how = _cuts_weighted(inner, lead, tail, weights), "text-length estimate"
    log(f"  [tts] split by {how} ({len(inner)} pauses found)")
    if not cuts:
        raise RuntimeError("could not align parts with pauses")
    bounds = [0.0] + cuts + [total]
    for (t, o), a, b in zip(pairs, bounds, bounds[1:]):
        subprocess.run([cfg["ffmpeg"], "-y", "-loglevel", "error", "-ss", f"{a:.3f}", "-to", f"{b:.3f}", "-i", full, "-c:a", "libmp3lame", "-q:a", "3", o], check=True)
    os.remove(full)
    for _, o in pairs:
        if not (os.path.exists(o) and os.path.getsize(o) > 0):
            raise RuntimeError("split produced empty part " + os.path.basename(o))


def speak(cfg, text, out, lang, log=print):
    text = clean(text)
    if not text:
        text = "."
    if os.path.exists(out) and os.path.getsize(out) > 0:
        return out
    backend = cfg["tts_backend"]
    if backend == "elevenlabs":
        try:
            _elevenlabs(cfg, text, out, log)
            return out
        except (requests.RequestException, RuntimeError) as e:
            log(f"  [tts] elevenlabs failed ({str(e)[:80]}), falling back to edge-tts")
    if backend == "sarvam" and cfg["keys"].get("sarvam"):
        try:
            _sarvam(cfg, text, out, lang)
            return out
        except (requests.RequestException, KeyError, RuntimeError, subprocess.CalledProcessError) as e:
            log(f"  [tts] sarvam failed ({str(e)[:80]}), falling back to edge-tts")
    if backend == "gemini" and cfg["keys"].get("gemini"):
        try:
            _gemini(cfg, text, out, log)
            return out
        except GeminiQuota:
            pass
        except (requests.RequestException, KeyError, subprocess.CalledProcessError) as e:
            log(f"  [tts] gemini failed ({str(e)[:80]}), falling back to edge-tts")
    voice = cfg["voices"].get(lang, cfg["voices"]["en"])
    for attempt in range(3):
        try:
            _edge(text, out, voice, cfg["tts_rate"])
            if os.path.getsize(out) > 0:
                return out
        except Exception as e:  # edge-tts raises several network/protocol error types
            log(f"  [tts] edge attempt {attempt + 1}: {str(e)[:80]}")
    raise RuntimeError("TTS failed for: " + text[:60])


def duration(cfg, path):
    """Seconds of audio. Uses ffprobe next to the configured ffmpeg; if it is missing, parses ffmpeg's own info."""
    d, base = os.path.split(cfg["ffmpeg"])
    ffprobe = os.path.join(d, base.replace("ffmpeg", "ffprobe"))
    try:
        r = subprocess.run([ffprobe, "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", path],
                           capture_output=True, text=True, encoding="utf-8", errors="replace", check=True)
        return float(r.stdout.strip())
    except FileNotFoundError:
        pass
    r = subprocess.run([cfg["ffmpeg"], "-i", path, "-f", "null", "-"], capture_output=True, text=True, encoding="utf-8", errors="replace")
    m = re.findall(r"time=(\d+):(\d+):([\d.]+)", r.stderr or "")
    if not m:
        raise RuntimeError("cannot read duration of " + os.path.basename(path))
    h, mi, se = m[-1]
    return int(h) * 3600 + int(mi) * 60 + float(se)
