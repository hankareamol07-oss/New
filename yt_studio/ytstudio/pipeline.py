"""End-to-end: chapter -> script -> slides -> narration -> MP4/Short/thumbnail -> (Descript) -> (YouTube/n8n)."""
import json
import os
import re
import time

from . import descript, script as scriptmod, video, youtube
from .data import Bank, chapter_from_file
from .slides import Renderer


def _slug(s):
    s = re.sub(r"[^\w\u0900-\u097F]+", "_", str(s)).strip("_")
    return s[:50] or "chapter"


def run(cfg, book, chapter, questions, text="", out_dir=None, log=print, seed=None, upload=None, use_descript=None):
    t0 = time.time()
    out_dir = out_dir or os.path.join(cfg["out_dir"], f"std{book['std']}_{book['subject']}_{book['lang']}_{chapter['no']:02d}_{_slug(chapter['title'])}")
    os.makedirs(out_dir, exist_ok=True)
    log(f"[run] {out_dir}")

    picked = scriptmod.pick_questions(questions, cfg["questions_per_video"], seed)
    if not picked and not text:
        raise RuntimeError("no questions and no chapter text for this chapter")
    script_path = os.path.join(out_dir, "script.json")
    if os.path.exists(script_path):
        with open(script_path, encoding="utf-8") as f:
            script = json.load(f)
        log("[script] reusing existing script.json (delete it to regenerate)")
    else:
        kind = "स्वाध्याय (textbook exercise) solutions" if picked else "chapter explanation with 8-12 new practice questions you create from the text"
        script = scriptmod.generate(cfg, book, chapter, picked, text, kind=kind, log=log)
        with open(script_path, "w", encoding="utf-8") as f:
            json.dump(script, f, ensure_ascii=False, indent=1)

    r = Renderer(cfg, book, chapter)
    thumb = os.path.join(out_dir, "thumbnail.png")
    r.thumbnail(script.get("thumbnail", {})).save(thumb)
    mp4, short = video.build(cfg, script, r, book, out_dir, log)

    meta = {
        "title": script["title"], "description": script["description"], "tags": script.get("tags", []),
        "hashtags": script.get("hashtags", []), "playlist": script.get("playlist"),
        "chapters_text": script.get("chapters_text", ""), "short_title": (script.get("short") or {}).get("title", script["title"][:50] + " #Shorts"),
        "lang": book["lang"], "std": book["std"], "subject": book["subject"], "chapter": chapter["title"],
        "duration_sec": script.get("duration_sec"), "video": mp4, "short": short, "thumbnail": thumb,
    }
    with open(script_path, "w", encoding="utf-8") as f:
        json.dump(script, f, ensure_ascii=False, indent=1)

    use_descript = cfg["descript"]["enabled"] if use_descript is None else use_descript
    if use_descript and cfg["keys"].get("descript"):
        try:
            meta["descript"] = descript.polish(cfg, mp4, book, chapter, out_dir, log)
            meta["video"] = meta["descript"]["file"]
        except Exception as e:  # network / API — keep the local render
            log(f"[descript] FAILED, using local render: {e}")
            meta["descript_error"] = str(e)

    upload = cfg["youtube"]["enabled"] if upload is None else upload
    if upload:
        meta["youtube_url"] = youtube.upload(cfg, meta["video"], meta, thumb, log=log)
        if short:
            meta["short_url"] = youtube.upload(cfg, short, meta, None, is_short=True, log=log)
    youtube.notify_n8n(cfg, meta, log)

    with open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf-8") as f:
        json.dump(meta, f, ensure_ascii=False, indent=1)
    with open(os.path.join(out_dir, "youtube_description.txt"), "w", encoding="utf-8") as f:
        f.write(meta["title"] + "\n\n" + meta["description"] + "\n\n⏱ Timestamps:\n" + meta["chapters_text"] + "\n\n" + " ".join(meta["hashtags"]) + "\n\nTags: " + ", ".join(meta["tags"]))
    log(f"[run] done in {time.time() - t0:.0f}s -> {meta['video']}")
    return meta


def run_bank(cfg, book_id, chapter_no, source="both", **kw):
    bank = Bank(cfg["data_dir"])
    book = bank.books[book_id]
    chapter = next(c for c in bank.chapters(book_id) if c["no"] == chapter_no)
    qs = bank.chapter_questions(book_id, chapter_no, source)
    text = bank.ocr_text(book, chapter)
    return run(cfg, book, chapter, qs, text, **kw)


def run_file(cfg, path, std, subject, lang, title=None, no=0, **kw):
    ch = chapter_from_file(path, std, subject, lang, title, no=no)
    return run(cfg, ch["book"], ch["chapter"], ch["questions"], ch["text"], **kw)
