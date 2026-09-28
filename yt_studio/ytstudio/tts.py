"""Narration: edge-tts (free Microsoft neural voices, mr/hi/en-IN), Gemini TTS, or ElevenLabs (cloned voice,
several API keys rotated when one runs out of credits). Writes mp3/wav per segment."""
import asyncio
import base64
import os
import re
import subprocess

import requests

from . import llm


def clean(text):
    text = re.sub(r"_{2,}", " रिकामी जागा " if re.search(r"[\u0900-\u097F]", text) else " blank ", text)
    text = re.sub(r"[*#`>]+", "", text)
    return re.sub(r"\s+", " ", text).strip()


def _edge(text, out, voice, rate):
    import edge_tts

    async def run():
        com = edge_tts.Communicate(text, voice, rate=rate)
        await com.save(out)

    asyncio.run(run())


def _gemini(cfg, text, out):
    body = {
        "contents": [{"parts": [{"text": text}]}],
        "generationConfig": {
            "responseModalities": ["AUDIO"],
            "speechConfig": {"voiceConfig": {"prebuiltVoiceConfig": {"voiceName": cfg["gemini_tts_voice"]}}},
        },
    }
    for key in llm._keys(cfg, "gemini"):
        url = f"https://generativelanguage.googleapis.com/v1beta/models/{cfg['gemini_tts_model']}:generateContent?key={key}"
        r = requests.post(url, json=body, timeout=300)
        if r.status_code == 200 or not llm._quota(r.status_code):
            break
        llm._rotate("gemini")
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


def speak(cfg, text, out, lang, log=print):
    text = clean(text)
    if not text:
        text = "."
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
            _gemini(cfg, text, out)
            return out
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
    d, base = os.path.split(cfg["ffmpeg"])
    ffprobe = os.path.join(d, base.replace("ffmpeg", "ffprobe"))
    r = subprocess.run([ffprobe, "-v", "error", "-show_entries", "format=duration", "-of", "csv=p=0", path],
                       capture_output=True, text=True, check=True)
    return float(r.stdout.strip())
