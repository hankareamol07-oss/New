# Project B — topic explanation videos (yt_explain)

One **full explanation video per 2026-27 tachan topic**, std 1-8 (1,437 topics; 1,011 have a textbook chapter, the rest are
activity/skill topics explained from the learning outcome). Poems / songs (`is_poem`, 118 topics) get a musical treatment.

```
cd yt_explain
copy config.example.json config.json
python build_topics.py                    # explain.db + data/topics.json + sources/<std>/<subject>/<seq>_<topic>/ (NotebookLM pack)
python auto.py --list
python auto.py --unit 6_सामान्य विज्ञान_2 --no-upload
python auto.py --poems --n 1
```

## NotebookLM
`sources/<std>/<subject>/<seq>_<topic>/` holds 4 files to drag into a NotebookLM notebook for that topic:
`01_textbook.txt` (current book OCR), `02_swadhyay.md` (exercise Q/A), `03_practice.md` (typed set), `04_outcome.md` (LO + activity).
The same text is what the LLM gets to write `explain_script.json` (sections → checks → summary).

## Poems
The script also returns `poem.lyrics_lines`, `poem.style_prompt` (English music-generator prompt) and `poem.recite_say`.
`song_prompt.txt` is written next to the video; generate the song (Suno / Udio / NotebookLM audio), save it as `song.mp3` in the
output folder, delete `video.mp4` and re-run — the recitation slides then play the song instead of TTS.

## Data
* `explain.db` – `units` keyed by `topic_key = "std|तचन विषय|घटक क्र."` with status/tries/youtube_url; `questions` = exercise Q of the chapter.
* `data/topics.json` – the same list (book_id, chapter_no, pages, official_id, learning_outcome, is_poem, sources_dir).

## Video design (research-based flow)
`explain_script.json` = hook question → learning outcomes → sections (one idea per step, bullet revealed with the narration,
daily-life example, worked example, common mistake) → retrieval check after every 2-3 sections → recap → homework/transfer → outro.
6-9 min, standard Balbharati Marathi/Hindi. Config:
* `"stickman": true` – animated stick-man teacher (wave / point / talk / think / cheer / sway) on the right of every slide (`stickman.py`); `false` = static slides.
* `"make_short": true` – also renders `short.mp4` (1080×1920, ≤ 60 s: hook + key idea + answer) and uploads it as a YouTube Short; post the same file manually as an Instagram Reel.

## GUI / EXE (Windows)
* `START_GUI.bat` – window: इयत्ता → विषय → घटक, "YouTube upload" tick, ▶ → video (+Short) + optional upload.
* `BUILD_EXE.bat` – builds `YTExplain.exe` with PyInstaller (run once on your PC). Keep the exe in this folder, next to
  `config.json`, `explain.db`, `data\`, `sources\`; the sibling `..\yt_studio` engine folder must stay.

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
