"""Project B engine: topic (NotebookLM source pack) -> explanation script -> slides -> narration -> MP4 -> YouTube.

Script shape (explain_script.json):
  {title, description, tags, hashtags, thumbnail,
   hook{say, question},                                  # 10-15 s curiosity question / daily-life scene
   intro{say,points},                                    # what you will learn (3-4 outcomes)
   sections:[{heading, kind, steps:[{point, say}], check{question, answer, say_q, say_a}?}],
                                                         # 6-10 segments, one idea each, bullets revealed one by one
                                                         # kind: concept | example | misconception | activity
   checks:[{question, answer, say_q, say_a}],            # 3-5 retrieval questions at the end
   summary{points, say}, homework{say, task}, outro{say},
   poem:{lyrics_lines[], style_prompt, recite_say}}      # only for is_poem units
  (old scripts with sections[{points, say}] still work: one step per section)

Design follows the evidence on instructional video (Mayer 2020/2021 multimedia principles; Brame 2016; Guo et al. 2014):
  hook + guiding question, segmenting (short steps, bullets appear with the narration), signaling (current bullet highlighted),
  coherence/weeding (no music, no decoration, <= 90-char bullets), modality (spoken explanation, short on-screen text),
  personalization (conversational "तुम्ही"), embodiment (gesturing stick-man teacher, config "stickman"), worked example +
  common misconception, interleaved retrieval checks with a pause prompt, recap + transfer task, 6-9 min total.
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
from ytstudio.tts import duration, prefetch, speak
from ytstudio import veo
from ytstudio.video import _concat, _sec

import stickman
import avatar

SYSTEM = """You are an expert Maharashtra State Board teacher and instructional designer making the BEST possible explanation
video of ONE topic for std 1-8 students watching alone on YouTube. Output STRICT JSON only.
Rules (evidence-based video design):
- Narration ("say") in the textbook language, conversational, warm, addressing the student as तुम्ही/तुम/you, age-appropriate words.
- ONE idea per step: each step = one short bullet (<= 90 chars, key term first) + 25-50 spoken words that explain exactly that bullet.
- Every concept gets a concrete example from a Maharashtra child's daily life (home, school, farm, market, festival, cricket).
- Include one worked example (step by step) and one "common mistake" (सामान्य चूक) segment with the correction.
- Ask a guiding question in the hook; after every 2-3 segments add a quick check question (student pauses, answers, then hears the answer).
- No filler, no jokes about the channel, no repeating the same sentence. Never contradict the textbook text. Numbers as digits with units.
- Total spoken length 6-9 minutes (about 900-1300 words in all "say" fields). No emojis.
- Marathi must be standard Balbharati-textbook Marathi (शुद्ध मराठी): reuse the textbook's own terms; never use Hindi words
  (e.g. अनवांछित, मिट्टी, घुलणे, बिन) or Hindi grammar. Hindi must likewise be standard textbook Hindi."""

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
 "hook": {{"question": "<= 80 chars curiosity question shown on screen", "say": "10-15 s: a daily-life scene or surprising question that this topic answers; end with the question"}},
 "intro": {{"say": "15-20 s: what you will be able to do after this video", "slide_title": "{topic}", "points": ["3-4 outcome bullets ('...समजेल', '...करता येईल')"]}},
 "sections": [ {{"heading": "<= 40 chars", "kind": "concept|example|misconception|activity",
                 "steps": [ {{"point": "<= 90 chars bullet", "say": "25-50 words explaining this bullet"}}, "(2-4 steps)" ],
                 "check": {{"question": "...", "answer": "...", "say_q": "question + 'व्हिडिओ थांबवून उत्तर द्या'", "say_a": "answer + one-line reason"}}
              }}, "(6-10 sections in teaching order: meaning -> parts/rules -> worked example -> common mistake -> where it is used -> activity; check only after every 2-3 sections, otherwise check: null)" ],
 "checks": [ {{"question": "...", "answer": "...", "say_q": "...", "say_a": "answer + one-line reason"}}, "(3-5, from the textbook exercise, easy -> hard)" ],
 "summary": {{"say": "20-30 s recap in the same order as the sections", "points": ["3-5 bullets"]}},
 "homework": {{"task": "<= 90 chars: one thing to try/observe at home today", "say": "10-15 s"}},
 "outro": {{"say": "one line: comment your answer/doubt, next topic name"}},
 "short": {{"title": "<= 60 chars, ends with #Shorts", "hook": "<= 40 chars punchy question", "idea": "<= 120 chars: THE one key idea of the topic",
            "say": "30-40 s: hook, the key idea with one example, then 'watch the full video'", "check_no": "index (1-based) of the checks question to show at the end"}},
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
    def prompt(n):
        return PROMPT.format(style=style, std=u["std"], subject=u["subject"], subject_mr=SUBJECT_MR.get(u["subject"], meta.get("tachan_subject", u["subject"])),
                             seq=meta.get("tachan_seq"), topic=u["title"], lang_name=LANG_NAME.get(u["lang"], u["lang"]),
                             channel=cfg["channel_name"], tagline=cfg["channel_tagline"], lo=meta.get("learning_outcome", ""), act=meta.get("activity", ""),
                             poem_note=POEM_NOTE if is_poem else "", text=(text or "(no textbook text - explain the learning outcome with activities)")[:n],
                             questions=qtxt, poem_json=POEM_JSON if is_poem else "null")
    log(f"[explain] asking LLM ({'poem' if is_poem else 'topic'}) ...")
    script, model = chat_json(cfg, SYSTEM, prompt(22000), max_tokens=14000, log=log, shrink=prompt)
    script["model"] = model
    _fix_chapter_no(script, {"no": meta.get("tachan_seq")}, log)
    script["sections"] = [s for s in script.get("sections") or [] if isinstance(s, dict)]
    script["checks"] = [c for c in script.get("checks") or [] if isinstance(c, dict)]
    for s in script["sections"]:
        s["steps"] = [st for st in s.get("steps") or [] if isinstance(st, dict)]
        if not isinstance(s.get("check"), dict):
            s["check"] = None
    for i, c in enumerate(script["checks"], 1):
        _norm_check(c, i)
    return script


def _norm_check(c, i):
    c.setdefault("no", i)
    c.setdefault("qtype", "one_sentence")
    c.setdefault("say_q", c.get("question", ""))
    c.setdefault("say_a", c.get("answer", ""))
    return c


def _steps(sec):
    """[(points, active, say)] slides of a section: new scripts reveal one bullet per step; old {points, say} = one slide."""
    steps = [st for st in sec.get("steps") or [] if isinstance(st, dict) and st.get("point")]
    if steps:
        pts = [st["point"] for st in steps]
        return [(pts, i, st.get("say")) for i, st in enumerate(steps)]
    return [(sec.get("points") or [sec.get("heading", "")], None, sec.get("say"))]


KIND_BADGE = {"example": ("उदाहरण", "Example"), "misconception": ("सामान्य चूक", "Common mistake"), "activity": ("कृती", "Activity")}
KIND_POSE = {"example": "point", "misconception": "think", "activity": "cheer"}


def _narration_plan(script, out_dir):
    """(say, slide name) in the exact order build_video speaks them - lets the whole narration go out as one TTS request."""
    plan = []
    hook = script.get("hook") if isinstance(script.get("hook"), dict) else None
    if hook and (hook.get("say") or hook.get("question")):
        plan.append((hook.get("say"), "00b_hook"))
    plan.append((script.get("intro", {}).get("say"), "01_intro"))
    poem = script.get("poem") if isinstance(script.get("poem"), dict) else None
    if poem and poem.get("lyrics_lines") and not os.path.exists(os.path.join(out_dir, "song.mp3")):
        plan.append((poem.get("recite_say"), "05_poem_00"))
    for i, sec in enumerate(script["sections"], 1):
        for j, (_, _, say) in enumerate(_steps(sec)):
            plan.append((say, f"{i + 10:02d}_sec_{j:02d}"))
        if isinstance(sec.get("check"), dict) and sec["check"].get("question"):
            c = _norm_check(sec["check"], i)
            plan += [(c.get("say_q"), f"{i + 10:02d}_chka_q"), (c.get("say_a"), f"{i + 10:02d}_chkb_a")]
    for c in script["checks"]:
        n = int(c["no"])
        plan += [(c.get("say_q"), f"{n + 60:02d}a_q"), (c.get("say_a"), f"{n + 60:02d}b_a")]
    plan.append((script.get("summary", {}).get("say"), "98_summary"))
    hw = script.get("homework") if isinstance(script.get("homework"), dict) else None
    if hw and hw.get("task"):
        plan.append((hw.get("say"), "98b_homework"))
    plan.append((script.get("outro", {}).get("say"), "99_outro"))
    return plan


def build_video(cfg, script, r, u, out_dir, log=print):
    lang = u["lang"]
    sl, au = os.path.join(out_dir, "slides"), os.path.join(out_dir, "audio")
    os.makedirs(sl, exist_ok=True)
    os.makedirs(au, exist_ok=True)
    items, chapters, t = [], [], 0.0
    en = lang == "en"
    av = bool((cfg.get("avatar") or {}).get("enabled"))
    sm = av or bool(cfg.get("stickman", True))
    reserve = (avatar.figure_height(cfg) if av else stickman.HEIGHT) + 60 if sm else 0   # keep the bullet card clear of the figure
    if av:
        cfg = dict(cfg, avatar=dict({"color": r.theme["primary"], "accent": r.theme["accent"]}, **cfg["avatar"]))
    prefetch(cfg, [(s, os.path.join(au, n + ".mp3")) for s, n in _narration_plan(script, out_dir) if s], lang, log)

    def add(img, name, say=None, extra=0.0, audio_file=None, pose="talk"):
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
        items.append((png, aud, secs, pose))
        start = t
        t += secs
        return start

    def check(c, name, badge):
        c.setdefault("instruction", badge)
        st = add(r.question(c, False), f"{name}a_q", c.get("say_q"), 2.5, pose="think")   # pause: student answers
        add(r.question(c, True), f"{name}b_a", c.get("say_a"), 1.0, pose="cheer")
        return st

    add(r.title(script), "00_title", None, extra=2.0, pose="wave")
    hook = script.get("hook") if isinstance(script.get("hook"), dict) else None
    if hook and (hook.get("say") or hook.get("question")):
        add(r.points(hook.get("question", ""), [], badge="विचार करा" if not en else "Think", reserve_right=reserve), "00b_hook", hook.get("say"), 1.0, pose="think")
        chapters.append((0, "प्रस्तावना" if not en else "Hook"))
    intro = script.get("intro", {})
    st = add(r.points(intro.get("slide_title", u["title"]), intro.get("points", []), badge="या व्हिडिओमध्ये शिकू" if not en else "You will learn", reserve_right=reserve),
             "01_intro", intro.get("say"), 0.8, pose="point")
    chapters.append((st if chapters else 0, intro.get("slide_title") or u["title"]))
    poem = script.get("poem") if isinstance(script.get("poem"), dict) else None
    if poem and poem.get("lyrics_lines"):
        song = os.path.join(out_dir, "song.mp3")
        lines = poem["lyrics_lines"]
        st = None
        for i in range(0, len(lines), 6):
            s = add(r.points(u["title"], lines[i:i + 6], badge="कविता" if not en else "Poem", reserve_right=reserve), f"05_poem_{i // 6:02d}",
                    poem.get("recite_say") if i == 0 and not os.path.exists(song) else None, 0.6,
                    audio_file=song if i == 0 else None, pose="sway")
            st = s if st is None else st
        chapters.append((st, "कविता" if not en else "Poem"))
    for i, sec in enumerate(script["sections"], 1):
        log(f"[video] section {i}: {sec.get('heading', '')[:40]}")
        kb = KIND_BADGE.get(sec.get("kind"))
        badge = (kb[1] if en else kb[0]) if kb else (f"Part {i}" if en else f"भाग {i}")
        pose = KIND_POSE.get(sec.get("kind"), "talk" if i % 2 else "point")
        st = None
        for j, (pts, active, say) in enumerate(_steps(sec)):
            s = add(r.points(sec.get("heading", ""), pts, badge=badge, reserve_right=reserve, active=active), f"{i + 10:02d}_sec_{j:02d}", say, 0.7, pose=pose)
            st = s if st is None else st
        chapters.append((st, sec.get("heading", f"भाग {i}")))
        if isinstance(sec.get("check"), dict) and sec["check"].get("question"):
            check(_norm_check(sec["check"], i), f"{i + 10:02d}_chk", "थांबा! उत्तर द्या" if not en else "Pause! Answer")
    for c in script["checks"]:
        n = int(c["no"])
        st = check(c, f"{n + 60:02d}", f"प्रश्न {n}" if not en else f"Question {n}")
        chapters.append((st, f"प्रश्न {n}" if not en else f"Question {n}"))
    summ = script.get("summary", {})
    st = add(r.points("सारांश" if not en else "Summary", summ.get("points", []), badge="लक्षात ठेवा" if not en else "Remember", reserve_right=reserve), "98_summary", summ.get("say"), 0.8, pose="point")
    chapters.append((st, "सारांश" if not en else "Summary"))
    hw = script.get("homework") if isinstance(script.get("homework"), dict) else None
    if hw and hw.get("task"):
        st = add(r.points("घरी करून बघा" if not en else "Try at home", [hw["task"]], badge="कृती" if not en else "Task", reserve_right=reserve), "98b_homework", hw.get("say"), 0.8, pose="cheer")
        chapters.append((st, "घरी करून बघा" if not en else "Try at home"))
    add(r.title(script), "99_outro", script.get("outro", {}).get("say"), 1.0, pose="wave")
    mp4 = os.path.join(out_dir, "video.mp4")
    log("[video] encoding ...")
    if av:
        avatar.concat_clips(cfg, items, os.path.join(out_dir, "narration.wav"), mp4, (1920, 1080))
    elif sm:
        stickman.concat_clips(cfg, items, os.path.join(out_dir, "narration.wav"), mp4, (1920, 1080))
    else:
        _concat(cfg, [(p, a, s) for p, a, s, _ in items], os.path.join(out_dir, "narration.wav"), mp4, (1920, 1080))
    intro_hint = (script.get("intro") or {}).get("slide_title", "")
    veo.write_prompt(out_dir, veo.intro_prompt(u["title"], u["subject"], u["std"], intro_hint))
    clips = veo.find_clips(out_dir)
    if not clips and cfg.get("veo", {}).get("enabled"):
        gen = veo.generate(cfg, veo.intro_prompt(u["title"], u["subject"], u["std"], intro_hint), os.path.join(out_dir, "intro.mp4"), log)
        clips = [gen] if gen else []
    off = veo.prepend(cfg, clips, mp4, (1920, 1080), log) if clips else 0.0
    t += off
    script["chapters_text"] = "\n".join(f"{_sec(s + off)} {title}" for s, title in chapters)
    script["duration_sec"] = round(t, 1)
    return mp4


def build_short(cfg, script, r, u, out_dir, log=print):
    """9:16 <= 60 s Short / Reel: hook + key idea, then one check question and its answer. None if the script has no 'short'."""
    sh = script.get("short") if isinstance(script.get("short"), dict) else None
    if not sh or not (sh.get("say") or sh.get("idea")):
        return None
    lang = u["lang"]
    sl, au = os.path.join(out_dir, "slides"), os.path.join(out_dir, "audio")
    checks = script.get("checks") or []
    try:
        c = checks[int(sh.get("check_no") or 1) - 1]
    except (ValueError, IndexError, TypeError):
        c = checks[0] if checks else None
    items = []

    def add(img, name, say, extra, pose):
        png = os.path.join(sl, name + ".png")
        img.save(png)
        aud = None
        secs = extra
        if say:
            aud = os.path.join(au, name + ".mp3")
            speak(cfg, say, aud, lang, log)
            secs += duration(cfg, aud)
        items.append((png, aud, max(secs, 2.0), pose))

    idea = {"question": sh.get("idea", ""), "answer": ""}
    prefetch(cfg, [(sh.get("say"), os.path.join(au, "s0_idea.mp3"))] + ([(c.get("say_q"), os.path.join(au, "s1_q.mp3")), (c.get("say_a"), os.path.join(au, "s2_a.mp3"))] if c else []), lang, log)
    add(r.short(idea, sh.get("hook", ""), False), "s0_idea", sh.get("say"), 0.8, "point")
    if c:
        add(r.short(c, sh.get("hook", ""), False), "s1_q", c.get("say_q"), 2.0, "think")
        add(r.short(c, sh.get("hook", ""), True), "s2_a", c.get("say_a"), 1.0, "cheer")
    out = os.path.join(out_dir, "short.mp4")
    log("[video] Short / Reel")
    if (cfg.get("avatar") or {}).get("enabled"):
        cfg = dict(cfg, avatar=dict({"color": r.theme["primary"], "accent": r.theme["accent"]}, **cfg["avatar"]))
        avatar.concat_clips(cfg, items, os.path.join(out_dir, "short_narration.wav"), out, (1080, 1920))
    elif cfg.get("stickman", True):
        stickman.concat_clips(cfg, items, os.path.join(out_dir, "short_narration.wav"), out, (1080, 1920))
    else:
        _concat(cfg, [(p, a, s) for p, a, s, _ in items], os.path.join(out_dir, "short_narration.wav"), out, (1080, 1920))
    return out


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
    short = build_short(cfg, script, r, u, out_dir, log) if cfg.get("make_short") else None
    json.dump(script, open(sp, "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    sh = script.get("short") if isinstance(script.get("short"), dict) else {}
    meta_out = {"title": script["title"], "description": script["description"], "tags": script.get("tags", []), "hashtags": script.get("hashtags", []),
                "playlist": script.get("playlist"), "chapters_text": script.get("chapters_text", ""), "lang": u["lang"], "std": u["std"],
                "subject": u["subject"], "chapter": u["title"], "topic_key": u["topic_key"], "duration_sec": script.get("duration_sec"),
                "video": mp4, "short": short, "short_title": sh.get("title") or (script["title"][:50] + " #Shorts"), "thumbnail": thumb, "out_dir": out_dir}
    upload = cfg["youtube"]["enabled"] if upload is None else upload
    if upload:
        meta_out["youtube_url"] = youtube.upload(cfg, mp4, meta_out, thumb, log=log)
        if short:
            meta_out["short_url"] = youtube.upload(cfg, short, meta_out, None, is_short=True, log=log)
    json.dump(meta_out, open(os.path.join(out_dir, "manifest.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=1)
    with open(os.path.join(out_dir, "youtube_description.txt"), "w", encoding="utf-8") as f:
        f.write(meta_out["title"] + "\n\n" + meta_out["description"] + "\n\n⏱ Timestamps:\n" + meta_out["chapters_text"] + "\n\n" + " ".join(meta_out["hashtags"]))
    return meta_out
