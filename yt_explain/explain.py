"""Project B engine: topic (NotebookLM source pack) -> explanation script -> slides -> narration -> MP4 -> YouTube.

Script shape (explain_script.json):
  {title, description, tags, hashtags, thumbnail, intro{say,points},
   sections:[{heading, points[], say}],                  # the explanation, 6-12 sections
   checks:[{question, answer, say_q, say_a}],            # 3-5 quick questions at the end
   summary{points, say}, outro{say},
   poem:{lyrics_lines[], style_prompt, recite_say}}      # only for is_poem units
Poems: the LLM also returns a musical style prompt (for Suno/Udio/NotebookLM audio). If <out_dir>/song.mp3 exists
it is used as the audio for the recitation slides; otherwise the poem is recited by TTS.
"""
import json
import os

from ytstudio import youtube
from ytstudio.config import ROOT
from ytstudio.data import SUBJECT_MR
from ytstudio.llm import chat_json
from ytstudio.script import LANG_NAME, _fix_chapter_no
from ytstudio.slides import Renderer
from ytstudio.tts import duration, speak
from ytstudio.video import _concat, _sec

SYSTEM = """You are an expert Maharashtra State Board teacher making a full explanation video of ONE topic for std 1-8.
Output STRICT JSON only. All narration ("say") is in the textbook language, spoken, warm, simple, examples from daily life,
one idea per section, 40-80 spoken words per section. Slide text short (bullets <= 90 chars). Never contradict the textbook text.
Numbers as digits with units. No emojis. Marathi/Hindi must be natural and grammatical."""

PROMPT = """STYLE GUIDE:
{style}

TOPIC: std {std} · {subject} ({subject_mr}) · घटक {seq}: {topic}
Language: {lang_name}. Channel: {channel} ({tagline}).
Learning outcome (from the annual plan): {lo}
Suggested classroom activity: {act}
{poem_note}

TEXTBOOK CHAPTER TEXT (OCR, may be noisy):
<<<
{text}
>>>

TEXTBOOK EXERCISE QUESTIONS (use them for the checks at the end, with correct answers):
{questions}

Return JSON exactly:
{{
 "title": "<= 95 chars: इ. {std} वी {subject_mr} | {topic} | संपूर्ण स्पष्टीकरण | Maharashtra Board",
 "description": "6-10 lines summary + 'या व्हिडिओमध्ये:' one line per section + 12-15 keywords",
 "tags": ["12-20 tags"], "hashtags": ["#... 4-6"], "playlist": "std {std} {subject} explained",
 "thumbnail": {{"headline": "<= 5 words topic name", "sub": "संपूर्ण स्पष्टीकरण", "badge": "इ. {std} वी {subject_mr}"}},
 "intro": {{"say": "20-30 s: what the topic is and why it matters", "slide_title": "{topic}", "points": ["3-4 bullets"]}},
 "sections": [ {{"heading": "...", "points": ["2-4 bullets"], "say": "40-80 words explanation with an example"}} ],
 "checks": [ {{"question": "...", "answer": "...", "say_q": "...", "say_a": "answer + one-line reason"}} ],
 "summary": {{"say": "20-30 s recap", "points": ["3-5 bullets"]}},
 "outro": {{"say": "like, subscribe, comment doubts, next topic"}},
 "poem": {poem_json}
}}"""

POEM_NOTE = ("THIS TOPIC IS A POEM/SONG: first explain poet, theme and difficult words, then stanza-by-stanza meaning; "
             "add 'poem': lyrics_lines = exact lines of the poem from the text (clean OCR), "
             "style_prompt = an English prompt for a music generator (genre, tempo, mood, instruments, child chorus, language) to sing these lyrics, "
             "recite_say = the poem read rhythmically for TTS.")
POEM_JSON = '{"lyrics_lines": ["..."], "style_prompt": "...", "recite_say": "..."}'


def generate(cfg, u, meta, text, questions, log=print):
    style = open(os.path.join(ROOT, "style", "reference_style.md"), encoding="utf-8").read()
    is_poem = bool(meta.get("is_poem"))
    qtxt = "\n".join(json.dumps({"q": q["text"], "type": q.get("qtype"), "answer": q.get("answer", "")}, ensure_ascii=False) for q in questions[:25]) or "(none)"
    user = PROMPT.format(style=style, std=u["std"], subject=u["subject"], subject_mr=SUBJECT_MR.get(u["subject"], meta.get("tachan_subject", u["subject"])),
                         seq=meta.get("tachan_seq"), topic=u["title"], lang_name=LANG_NAME.get(u["lang"], u["lang"]),
                         channel=cfg["channel_name"], tagline=cfg["channel_tagline"], lo=meta.get("learning_outcome", ""), act=meta.get("activity", ""),
                         poem_note=POEM_NOTE if is_poem else "", text=(text or "(no textbook text - explain the learning outcome with activities)")[:22000],
                         questions=qtxt, poem_json=POEM_JSON if is_poem else "null")
    log(f"[explain] asking LLM ({'poem' if is_poem else 'topic'}) ...")
    script, model = chat_json(cfg, SYSTEM, user, max_tokens=14000, log=log)
    script["model"] = model
    _fix_chapter_no(script, {"no": meta.get("tachan_seq")}, log)
    script.setdefault("sections", [])
    script.setdefault("checks", [])
    for i, c in enumerate(script["checks"], 1):
        c.setdefault("no", i)
        c.setdefault("qtype", "one_sentence")
        c.setdefault("say_q", c.get("question", ""))
        c.setdefault("say_a", c.get("answer", ""))
    return script


def build_video(cfg, script, r, u, out_dir, log=print):
    lang = u["lang"]
    sl, au = os.path.join(out_dir, "slides"), os.path.join(out_dir, "audio")
    os.makedirs(sl, exist_ok=True)
    os.makedirs(au, exist_ok=True)
    items, chapters, t = [], [], 0.0

    def add(img, name, say=None, extra=0.0, audio_file=None):
        nonlocal t
        png = os.path.join(sl, name + ".png")
        img.save(png)
        aud, secs = None, extra
        if audio_file and os.path.exists(audio_file):
            aud = audio_file
            secs += duration(cfg, aud)
        elif say:
            aud = os.path.join(au, name + ".mp3")
            speak(cfg, say, aud, lang, log)
            secs += duration(cfg, aud)
        secs = max(secs, 2.0)
        items.append((png, aud, secs))
        start = t
        t += secs
        return start

    add(r.title(script), "00_title", None, extra=2.5)
    intro = script.get("intro", {})
    chapters.append((0, intro.get("slide_title") or u["title"]))
    add(r.points(intro.get("slide_title", u["title"]), intro.get("points", []), badge="या व्हिडिओमध्ये" if lang != "en" else "In this video"), "01_intro", intro.get("say"), 0.8)
    poem = script.get("poem") if isinstance(script.get("poem"), dict) else None
    if poem and poem.get("lyrics_lines"):
        song = os.path.join(out_dir, "song.mp3")
        lines = poem["lyrics_lines"]
        st = None
        for i in range(0, len(lines), 6):
            s = add(r.points(u["title"], lines[i:i + 6], badge="कविता" if lang != "en" else "Poem"), f"05_poem_{i // 6:02d}",
                    poem.get("recite_say") if i == 0 and not os.path.exists(song) else None, 0.6,
                    audio_file=song if i == 0 else None)
            st = s if st is None else st
        chapters.append((st, "कविता" if lang != "en" else "Poem"))
    for i, sec in enumerate(script["sections"], 1):
        log(f"[video] section {i}: {sec.get('heading', '')[:40]}")
        st = add(r.points(sec.get("heading", ""), sec.get("points", []), badge=f"भाग {i}" if lang != "en" else f"Part {i}"), f"{i + 10:02d}_sec", sec.get("say"), 0.8)
        chapters.append((st, sec.get("heading", f"भाग {i}")))
    for c in script["checks"]:
        n = int(c["no"])
        st = add(r.question(c, False), f"{n + 60:02d}a_q", c.get("say_q"), 1.5)
        chapters.append((st, f"प्रश्न {n}" if lang != "en" else f"Question {n}"))
        add(r.question(c, True), f"{n + 60:02d}b_a", c.get("say_a"), 1.0)
    summ = script.get("summary", {})
    st = add(r.points("सारांश" if lang != "en" else "Summary", summ.get("points", []), badge="लक्षात ठेवा" if lang != "en" else "Remember"), "98_summary", summ.get("say"), 0.8)
    chapters.append((st, "सारांश" if lang != "en" else "Summary"))
    add(r.title(script), "99_outro", script.get("outro", {}).get("say"), 1.5)
    mp4 = os.path.join(out_dir, "video.mp4")
    log("[video] encoding ...")
    _concat(cfg, items, os.path.join(out_dir, "narration.wav"), mp4, (1920, 1080))
    script["chapters_text"] = "\n".join(f"{_sec(s)} {title}" for s, title in chapters)
    script["duration_sec"] = round(t, 1)
    return mp4


def run(cfg, u, meta, text, questions, out_dir, upload=None, log=print):
    os.makedirs(out_dir, exist_ok=True)
    sp = os.path.join(out_dir, "explain_script.json")
    if os.path.exists(sp):
        script = json.load(open(sp, encoding="utf-8"))
        log("[explain] reusing explain_script.json")
    else:
        script = generate(cfg, u, meta, text, questions, log)
        json.dump(script, open(sp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    if isinstance(script.get("poem"), dict) and script["poem"].get("style_prompt"):
        with open(os.path.join(out_dir, "song_prompt.txt"), "w", encoding="utf-8") as f:
            f.write("STYLE:\n" + script["poem"]["style_prompt"] + "\n\nLYRICS:\n" + "\n".join(script["poem"].get("lyrics_lines", [])) +
                    "\n\n(generate with Suno/Udio/NotebookLM audio, save as song.mp3 in this folder, delete video.mp4 and re-run to mix it in)\n")
    book = {"std": u["std"], "subject": u["subject"], "lang": u["lang"]}
    chapter = {"no": meta.get("tachan_seq"), "title": u["title"]}
    r = Renderer(cfg, book, chapter)
    thumb = os.path.join(out_dir, "thumbnail.png")
    r.thumbnail(script.get("thumbnail", {})).save(thumb)
    mp4 = build_video(cfg, script, r, u, out_dir, log)
    json.dump(script, open(sp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    meta_out = {"title": script["title"], "description": script["description"], "tags": script.get("tags", []), "hashtags": script.get("hashtags", []),
                "playlist": script.get("playlist"), "chapters_text": script.get("chapters_text", ""), "lang": u["lang"], "std": u["std"],
                "subject": u["subject"], "chapter": u["title"], "topic_key": u["topic_key"], "duration_sec": script.get("duration_sec"),
                "video": mp4, "thumbnail": thumb, "out_dir": out_dir}
    upload = cfg["youtube"]["enabled"] if upload is None else upload
    if upload:
        meta_out["youtube_url"] = youtube.upload(cfg, mp4, meta_out, thumb, log=log)
    json.dump(meta_out, open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    with open(os.path.join(out_dir, "youtube_description.txt"), "w", encoding="utf-8") as f:
        f.write(meta_out["title"] + "\n\n" + meta_out["description"] + "\n\n⏱ Timestamps:\n" + meta_out["chapters_text"] + "\n\n" + " ".join(meta_out["hashtags"]))
    return meta_out
