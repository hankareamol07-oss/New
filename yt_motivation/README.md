# Project C — मराठी Motivation Shorts (yt_motivation)

Original Marathi-first motivational **Shorts only** (1080×1920, 35–55 s): trending-theme research → new script written by AI
(never copies a source) → big Devanagari typography slides → TTS voice → `short.mp4` + title / caption / hashtags / tags.
Own DB `motivation.db` (tables `trends`, `units`). No stick-man (typography design only).

```
cd yt_motivation
INSTALL.bat                              (python -m pip install -r ..\yt_studio\requirements.txt)
python auto.py --refresh                 # search trending Marathi motivation Shorts (yt-dlp; YouTube API fallback) -> motivation.db
python auto.py --n 2 --no-upload         # 2 Shorts, themes proposed from the trends
python auto.py --theme "परीक्षेची भीती" --no-upload
python auto.py --list
AUTO_UPLOAD.bat                          # make + upload as YouTube Shorts (needs ..\yt_studio\client_secret.json)
```

## Output (`output/<n>_<theme>/`)
`short.mp4`, `cover.png`, `caption.txt` (title, caption, hashtags – paste into YouTube / Instagram), `script.json`, `manifest.json`,
`slides/`, `audio/`. Instagram has no upload API for personal accounts: post `short.mp4` + `caption.txt` manually.

## Design
Fonts in `assets/fonts/` (OFL): Mukta ExtraBold (bold), Tiro Devanagari Marathi (classic), Baloo 2 (playful), rendered with
Pillow RAQM so matras/conjuncts shape correctly. Per-Short palette (gradient background, accent colour), key word of each
line underlined in accent, quote mark on the hook, step counter, progress bar, channel mark (`channel_name`, `channel_tagline`).

## Originality
Trend titles are research only: the LLM sees titles + frequent words and is instructed to write new text, not to copy,
translate or paraphrase any quote/speech/song/creator wording. Review each script (`script.json`) before publishing.

## GUI / EXE
`START_GUI.bat` – theme (blank = auto from trends), count, upload tick, ▶. `BUILD_EXE.bat` → `YTMotivation.exe`
(keep next to `config.json`, `motivation.db`, `assets\`; `..\yt_studio` must stay).

## Config
`config.json` (copy of `config.example.json`): API keys (`gemini_api_key`, `nvidia_api_key`, `groq_api_key`), `llm_order`,
`tts_backend` (`gemini` / `edge` / `elevenlabs`), `voices.mr`, `trends.queries`, `themes` (fixed list, optional),
`youtube.enabled/privacy/schedule`.

## Multiple API keys
`"gemini_api_key"` (also nvidia/groq) accepts one key or a list, e.g. `"gemini_api_key": ["AIza...1", "AIza...2"]` — the next key is used automatically when one returns 429 (quota).

## Natural Marathi voice (Sarvam AI Bulbul)
Free-tier, Marathi-native TTS. Get a key at https://dashboard.sarvam.ai → API Keys, then in `config.json`:
```json
"sarvam_api_key": "sk_...",
"tts_backend": "sarvam",
"sarvam": {"model": "bulbul:v3", "speaker": "shubh", "pace": 0.95}
```
Speakers: `shubh`, `aditya`, `rahul` (male); `ritu`, `priya`, `neha` (female). `pace` 0.5–2.0. Falls back to edge-tts if Sarvam fails. Delete a chapter's `audio\` folder + video.mp4 to re-voice it.
