# Project A — स्वाध्याय / सरावसंच videos (yt_swadhyay)

Videos **only** of the textbook exercise questions of the 2026-27 books, std 1, 2, 3, 4, 6 (`stds` in config.json).
One video per **exercise set**: language/science/social chapters → the chapter स्वाध्याय; Maths → every **सरावसंच 1.1, 1.2 …** separately.
No typed/AI questions, no उपक्रम/कृती/प्रकल्प activities. Every question of the set is covered, in textbook order.

Uses the `yt_studio` engine (LLM script → slides → TTS → MP4/Short → YouTube) with **its own** config, data and `swadhyay.db`.

```
cd yt_swadhyay
copy config.example.json config.json      # keys, ffmpeg path, tts_backend, youtube.enabled
python build_units.py                     # data/units.json + swadhyay.db from ../exam_paper/data (book bank) — already shipped
python auto.py --list                     # pending units
python auto.py --unit b35_c01_ex --no-upload
python auto.py                            # next auto.per_run units, upload/schedule to YouTube
```

`client_secret.json` / `youtube_token.json` live in `../yt_studio` (same channel for both projects).

## Data
* `data/units.json` – units with `topic_key = "std|तचन विषय|घटक क्र."` (same key as `ep_book_chapters.tachan_seq` and `hpc_topic_map`), book/chapter/pages, official Balbharati book id.
* `swadhyay.db` (sqlite) – `units` (status pending/done/failed, tries, out_dir, youtube_url, short_url) and `questions` (textbook order).
  Re-run `build_units.py` after a bank update; status of finished units is kept.

## GUI / EXE (Windows)
* `START_GUI.bat` – window: इयत्ता → विषय → पाठ → स्वाध्याय/सरावसंच, "YouTube upload" tick, ▶.
* `BUILD_EXE.bat` – builds `YTSwadhyay.exe` with PyInstaller (run once on your PC). Keep the exe next to `config.json`,
  `swadhyay.db`, `data\`; the sibling `..\yt_studio` engine folder must stay.

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
