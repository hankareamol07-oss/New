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
