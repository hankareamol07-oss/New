"""Narration: edge-tts (free Microsoft neural voices, mr/hi/en-IN), Gemini TTS, or ElevenLabs (cloned voice,
several API keys rotated when one runs out of credits). Writes mp3/wav per segment."""
import asyncio
import base64
import difflib
import json
import os
import re
import subprocess
import time
import traceback
import unicodedata

import requests

from . import llm, vertex


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
    live = [k for k in (llm._keys(cfg, "gemini") if cfg["keys"].get("gemini") else []) if k not in _GEMINI_DAY_DEAD]
    if not live and not vertex.enabled(cfg):
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
            r = None
            for key, url, h in vertex.endpoints(cfg, cfg["gemini_tts_model"], live):
                if url is None:
                    continue
                r = requests.post(url, json=body, headers=h, timeout=300)
                if r.status_code == 200 or not llm._quota(r.status_code):
                    break
                if key == "vertex":
                    log(f"  [tts] vertex HTTP {r.status_code}: {r.text[:100]}")
                    continue
                if "per_day" in r.text or "PerDay" in r.text:
                    _GEMINI_DAY_DEAD.add(key)
                    log(f"  [tts] gemini key ...{key[-4:]}: daily TTS quota used up")
                llm._rotate("gemini")
            live = [k for k in live if k not in _GEMINI_DAY_DEAD]
            if r is None or (not live and not vertex.enabled(cfg)):
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
    if cfg["tts_backend"] != "gemini" or not (cfg["keys"].get("gemini") or vertex.enabled(cfg)) or len(pairs) < 2:
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


ALIGN_MODEL = "gemini-3.5-transcribe"


def _norm(text):
    """Letters, marks and digits only (no spaces/punctuation), lower-cased, so transcript and script compare
    despite punctuation and word breaks."""
    return "".join(c for c in text.lower() if unicodedata.category(c)[0] in "LMN")


def _transcribe_words(cfg, full, log):
    """Gemini's transcription model returns every spoken word with start/end offsets (one request for up to 30 min
    of audio). Returns [(word, start, end)] or None on HTTP failure."""
    with open(full, "rb") as f:
        audio = base64.b64encode(f.read()).decode()
    body = {"contents": [{"role": "user", "parts": [{"inlineData": {"mimeType": "audio/mp3", "data": audio}}]}],
            "generationConfig": {"audioTranscriptionConfig": {"wordTimestamp": True}}}
    r = None
    for attempt in range(4):
        keys = llm._keys(cfg, "gemini") if cfg["keys"].get("gemini") else []
        for key, url, h in vertex.endpoints(cfg, ALIGN_MODEL, keys):
            if url is None:
                continue
            r = requests.post(url, json=body, headers=h, timeout=300)
            if r.status_code == 200 or not llm._quota(r.status_code):
                break
            if key != "vertex":
                llm._rotate("gemini")
        if r is not None and r.status_code in (429, 500, 503) and attempt < 3:
            log(f"  [tts] transcribe model HTTP {r.status_code}, retrying in 20s")
            time.sleep(20)
            continue
        break
    if r is None or r.status_code != 200:
        log(f"  [tts] transcribe model HTTP {r.status_code if r is not None else '?'}")
        return None
    words = []
    for part in r.json()["candidates"][0]["content"]["parts"]:
        for w in part.get("audioTranscription", {}).get("words", []):
            words.append((w["word"], float(w["startOffset"].rstrip("s")), float(w["endOffset"].rstrip("s"))))
    return words or None


def _align_words(texts, words, log):
    """Match the script to the timed transcript (character-level, so mis-heard words and different word breaks
    still match around them) and return, per part boundary, (end of last word of part i, start of first word of
    part i+1); None for a boundary that could not be anchored within its last/first ~25 characters."""
    exp, starts, pos = "", [], 0
    for t in texts:
        starts.append(pos)
        n = _norm(t)
        exp += n
        pos += len(n)
    tr, owner = "", []
    for i, (w, _, _) in enumerate(words):
        n = _norm(w)
        tr += n
        owner += [i] * len(n)
    sm = difflib.SequenceMatcher(None, exp, tr, autojunk=False)
    blocks = [m for m in sm.get_matching_blocks() if m.size >= 3]
    matched = sum(m.size for m in blocks)
    log(f"  [tts] transcript matches {100 * matched // max(1, len(exp))}% of the script")
    if matched < 0.5 * len(exp):
        return None
    out = []
    for p in starts[1:]:
        before = [m for m in blocks if m.a < p]
        after = [m for m in blocks if m.a + m.size > p]
        if not before or not after:
            out.append(None)
            continue
        mb, ma = before[-1], after[0]
        end_a = min(p, mb.a + mb.size) - 1            # last script char of part i that is matched
        start_a = max(p, ma.a)                          # first script char of part i+1 that is matched
        if p - end_a > 25 or start_a - p > 25:
            out.append(None)
            continue
        wi_end = owner[mb.b + (end_a - mb.a)]
        wi_start = owner[ma.b + (start_a - ma.a)]
        if wi_start <= wi_end:                          # boundary falls inside one transcribed word: split it
            t = words[wi_end][1] + (words[wi_end][2] - words[wi_end][1]) * 0.5
            out.append((t, t))
        else:
            out.append((words[wi_end][2], words[wi_start][1]))
    return out


def _cuts_from_words(bounds, inner, lead, tail, weights):
    """bounds = [(end_prev, start_next) | None] per boundary. Cut in the pause between the two words (snapped to a
    detected silence inside it), None entries interpolated from text length between the neighbouring anchors."""
    n = len(bounds)
    anchors = [lead] + [None] * n + [tail]
    for i, b in enumerate(bounds):
        if b:
            e, s = b
            gap = [(x, y) for x, y in inner if x >= e - 0.15 and y <= s + 0.15]
            if gap:
                x, y = max(gap, key=lambda g: g[1] - g[0])
                anchors[i + 1] = max(x, y - 0.25) if y - x > 0.5 else (x + y) / 2
            else:
                anchors[i + 1] = e + (s - e) * 0.6
    i = 0
    while i < len(anchors):
        if anchors[i] is None:
            j = i
            while anchors[j] is None:
                j += 1
            k = i - 1
            span = anchors[j] - anchors[k]
            w = weights[k:j]
            acc = 0
            for m in range(i, j):
                acc += w[m - k - 1]
                anchors[m] = anchors[k] + span * acc / sum(w)
            i = j
        i += 1
    cuts = anchors[1:-1]
    return cuts if cuts == sorted(cuts) else None


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
    cuts, how = None, "word timestamps"
    try:
        words = _transcribe_words(cfg, full, log)
        bounds = _align_words(texts, words, log) if words else None
        if bounds:
            missing = sum(1 for b in bounds if b is None)
            if missing:
                log(f"  [tts] {missing}/{len(bounds)} boundaries not heard clearly; estimated from text length")
            cuts = _cuts_from_words(bounds, inner, lead, tail, weights)
    except (requests.RequestException, llm.LLMError, KeyError, ValueError, json.JSONDecodeError) as e:
        log(f"  [tts] transcribe failed ({str(e)[:80]}); using pause heuristics")
        cuts = None
    if cuts and not _plausible(cuts, lead, tail, weights):
        log("  [tts] word timestamps implausible; using pause heuristics")
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
    if backend == "gemini" and (cfg["keys"].get("gemini") or vertex.enabled(cfg)):
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
