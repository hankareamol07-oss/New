# YT Studio — desktop YouTube video generator (std 1–8, Maharashtra Board)

Runs entirely on your PC. Picks a chapter from the school data bank (`exam_paper/data/*.json`, the same
स्वाध्याय + AI सराव प्रश्नसंच data the exam module uses), writes a lesson script + SEO metadata with
Gemini/NVIDIA/Groq, renders slides (Marathi/Hindi/English), narrates with a free neural TTS, assembles an
MP4 + 9:16 Short + thumbnail with ffmpeg, optionally polishes through **Descript** (Studio Sound, silence
trim, zoom, captions — uses your monthly credits) and uploads to **YouTube** (or hands off to **n8n**).

```
chapter ─► questions (book / typed / both) ─► LLM script+SEO (JSON)
        ─► slides (Pillow) ─► TTS (edge-tts) ─► ffmpeg video.mp4 + short.mp4 + thumbnail.png
        ─► [Descript polish → video_descript.mp4]  ─► [YouTube upload + playlist + schedule]  ─► [n8n webhook]
```

## 1. Install (Windows / Linux / macOS)

1. Python 3.10+ → https://www.python.org/downloads/ (tick "Add to PATH").
2. ffmpeg → https://ffmpeg.org/download.html (Windows: unzip, add `bin` folder to PATH, or set `"ffmpeg": "C:/ffmpeg/bin/ffmpeg.exe"` in config.json).
3. In this folder:
   ```
   pip install -r requirements.txt
   copy config.example.json config.json      (Linux/mac: cp)
   ```
4. Edit `config.json`:
   * `gemini_api_key` (https://aistudio.google.com/apikey), `nvidia_api_key` (https://build.nvidia.com), `groq_api_key` (https://console.groq.com) — at least one; order of use is `llm_order`.
   * `descript_api_token` — Descript → Settings → API (format `dx_bearer_…:dx_secret_…`). Set `"descript": {"enabled": true}` to use credits on every video.
   * `channel_name`, `channel_tagline`, `brand_primary/accent`, `logo` (PNG path) — branding on every slide.
   * `data_dir` — the exam module's `data` folder (contains `book_questions.json`, `typed_questions.json`, `ocr/`).

   Keys can also be given as environment variables `GEMINI_API_KEY`, `NVIDIA_API_KEY`, `GROQ_API_KEY`, `DESCRIPT_API_TOKEN`
   (they win over config.json). **Never commit config.json / client_secret.json / youtube_token.json** — they are git-ignored.

## 2. Make a video

GUI:
```
python gui.py
```
Choose इयत्ता → पुस्तक → पाठ → ▶. Log shows progress; result opens in `output/std6_Science_mr_01_मापन/`.

CLI:
```
python run.py list --std 6                      # books
python run.py list --book 37                    # chapters of a book (+ question count)
python run.py make --book 37 --chapter 1        # स्वाध्याय + typed set (default --source both)
python run.py make --book 37 --chapter 1 --source book --descript --upload
python run.py batch --std 6 --subject Science --lang mr --from 1 --to 11 --upload   # one video per chapter
```

New-syllabus chapter that is not in the bank yet (PDF or text file):
```
python run.py make-file --file "Std6_Sci_Ch1_new.pdf" --std 6 --subject Science --lang mr --title "मापन"
```
The LLM reads the chapter text and creates 8–12 practice questions itself.

Every run writes to `output/<std_subject_lang_ch_title>/`:

| file | what |
|---|---|
| `video.mp4` | 1920×1080, 30 fps, AAC narration, ~5–12 min |
| `short.mp4` | 1080×1920 Short (hook → question → answer, ≤ 60 s) |
| `thumbnail.png` | 1280×720 |
| `script.json` | full script, SEO title/description/tags/hashtags, timestamps (`chapters_text`); delete to regenerate |
| `youtube_description.txt` | paste-ready description with timestamps + hashtags + tags |
| `manifest.json` | everything above + Descript/YouTube URLs |
| `video_descript.mp4` | Descript-rendered version (when enabled) |
| `slides/`, `audio/` | intermediates |

## 3. Descript (uses your plan credits)

`"descript": {"enabled": true}` or `--descript`. Flow (official API, https://docs.descriptapi.com):
`POST /jobs/import/project_media` (direct upload) → `POST /jobs/agent` (Underlord: Studio Sound, silence trim keeping the Q→A pause, subtle zoom, optional captions) → `POST /jobs/publish` (1080p MP4 download).
The project also appears in your Descript drive (`project_url` in manifest) so you can hand-edit and re-export.

* `captions`: `"auto"` (on for Hindi/English, **off for Marathi** — Descript has no Marathi speech model, so Marathi captions come out wrong), `true`, `false`.
* `prompt`: override the Underlord instruction completely.
* Each video costs Descript credits (transcription minutes + Underlord); the local `video.mp4` is always kept as fallback.

## 4. YouTube upload

1. Google Cloud Console → new project → enable **YouTube Data API v3** → Credentials → *OAuth client ID* → type **Desktop app** → download JSON → save as `yt_studio/client_secret.json`. Add your Google account as a test user on the OAuth consent screen.
2. `"youtube": {"enabled": true, ...}` or `--upload`. First run opens the browser once; token is cached in `youtube_token.json`.
3. Settings:
   * `privacy`: `private` / `unlisted` / `public`.
   * `schedule.enabled`: uploads as private with `publishAt` = next free slot (`hour:minute`, `every_days`) — consistent daily posting; last slot remembered in `schedule_state.json`.
   * `playlist_by`: playlist "std 6 Science (mr)" is created if missing and the video added.
   * `made_for_kids`: YouTube requires you to declare this; educational content for children is usually *not* "made for kids" in the COPPA sense unless it directly targets under-13s — decide for your channel.
   * Thumbnail set automatically (needs a verified phone number on the channel). Shorts are uploaded with the `#Shorts` title.
   * Default quota (10,000 units/day) allows ~6 uploads/day.

## 5. n8n (optional)

Set `"n8n_webhook": "https://your-n8n/webhook/yt-studio"`. After every video `run.py` POSTs the manifest
(title, description, tags, hashtags, chapters_text, video/short/thumbnail paths, youtube_url if already uploaded).
Import `n8n/yt_studio_upload.json`: Webhook → (not uploaded yet?) → Read MP4 → YouTube node → Telegram notify.
Replace the credential placeholders. Use this instead of §4 if you prefer to keep OAuth in n8n; n8n must be able to read the path (same machine or shared folder).

## 6. Style / SEO

`style/reference_style.md` is sent to the LLM with every request: one-question-per-slide flow (reference: Sai Coaching Classes style chapter-wise videos), NotebookLM-like overview → questions → recap, title pattern `इयत्ता 6 वी | विज्ञान | मापन | स्वाध्याय | Maharashtra Board`, mixed Marathi + transliterated tags, 4–6 hashtags, Shorts hook. Edit it to change the channel's voice.
Nothing here can guarantee views or monetisation — it gives consistent, searchable, correctly-packaged uploads; YPP (1,000 subs + 4,000 watch-hours or 10 M Shorts views) still depends on the audience.

## 7. Troubleshooting

* `LLMError: all providers failed` — check keys / daily quota; Gemini free tier resets daily.
* Boxes instead of letters — fonts are in `assets/fonts/` (Noto Sans + Noto Sans Devanagari); don't move the folder.
* `edge-tts` needs internet; if Microsoft changes voices run `edge-tts --list-voices` and update `voices`.
* Descript 400 "language" — only `hi`/`en` (+ European languages) are accepted; Marathi is mapped to `hi`.
