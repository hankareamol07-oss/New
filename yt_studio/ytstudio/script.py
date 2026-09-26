"""Turn a chapter (questions + optional text) into a video script + SEO metadata using the LLM."""
import json
import os
import random
import re

from .config import ROOT
from .data import SUBJECT_MR
from .llm import chat_json

LANG_NAME = {"mr": "Marathi (मराठी)", "hi": "Hindi (हिंदी)", "en": "English"}

SYSTEM = """You write scripts for a YouTube education channel for Maharashtra State Board students (std 1-8).
Output STRICT JSON only, no markdown. All narration ("say" fields) must be in the textbook language given,
spoken-style, simple, warm classroom tone. Keep slide text short (max ~220 characters per line block).
Never invent a wrong answer: reuse the given expected answer; if a given answer is empty, work out a correct one.
For Maths give the working as an ordered list of short steps (one operation per step).
Do not use emojis in narration. Do not read option labels as brackets.
Numbers in narration: write them as digits with the unit (e.g. "37 अंश सेल्सिअस", "100 सेंटीमीटर"), never spelled-out or hyphenated words.
Never put timestamps (0:30 etc.) in the description; we add them. Marathi/Hindi must be natural and grammatical (no transliteration mistakes)."""

PROMPT = """STYLE GUIDE:
{style}

VIDEO CONTEXT
- Class: std {std}
- Subject: {subject} ({subject_mr})
- Language of textbook and narration: {lang_name}
- Chapter: {chapter}  (use this chapter number/name in the title; do not take chapter numbers from the OCR text)
- Channel: {channel} ({tagline})
- Video kind: {kind}

CHAPTER TEXT (may be OCR, may be empty):
<<<
{text}
>>>

QUESTIONS TO COVER (in this order; keep numbering; may fix obvious OCR typos):
{questions}

Return JSON with exactly this shape:
{{
 "title": "<= 95 chars, follows style guide title pattern, includes std, subject, chapter, स्वाध्याय/प्रश्नोत्तरे, Maharashtra Board",
 "description": "5-10 lines: 2-line chapter summary, then what the video covers, then 'या व्हिडिओमध्ये:' followed by one line per question (we add timestamps), then 12-15 keywords comma separated (Marathi + English transliteration)",
 "tags": ["12-20 short tags mixing Marathi, Hindi/English transliteration e.g. 'std 6 science', '6vi vidyan swadhyay', 'Maharashtra board class 6'"],
 "hashtags": ["#... 4-6 items"],
 "playlist": "std {std} {subject} ({lang})",
 "thumbnail": {{"headline": "<= 5 words, chapter name", "sub": "<= 6 words e.g. संपूर्ण स्वाध्याय | उत्तरे", "badge": "इ. {std} वी {subject_mr}"}},
 "intro": {{"say": "20-30 s welcome + what students will learn", "slide_title": "...", "points": ["3-4 short bullets"]}},
 "segments": [
   {{"no": 1, "qtype": "...", "instruction": "short heading e.g. रिकाम्या जागा भरा",
     "question": "slide text of the question (keep ____ blanks)", "options": ["only for mcq/odd_one"],
     "pairs": [["A","1"]] , "answer": "final answer text for the slide",
     "steps": ["only for maths/solve: ordered working steps"],
     "say_q": "narration reading the question naturally",
     "say_a": "narration: state the answer exactly as in 'answer' (with unit/complete sentence), then one short sentence why — like a teacher"}}
 ],
 "summary": {{"say": "20-30 s recap", "points": ["3-5 key learnings"]}},
 "outro": {{"say": "call to action: like, subscribe, comment doubts, next chapter"}},
 "short": {{"segment_no": <the most interesting question number>, "hook": "<= 8 words hook text for a 9:16 Short", "title": "<= 60 chars Short title with #Shorts"}}
}}"""


def _fmt_questions(qs):
    lines = []
    for i, q in enumerate(qs, 1):
        d = {"no": i, "qtype": q.get("qtype"), "instruction": q.get("instruction", ""), "text": q.get("text", "")}
        if q.get("options"):
            d["options"] = q["options"]
        if q.get("pairs"):
            d["pairs"] = q["pairs"]
        if q.get("answer"):
            d["answer"] = q["answer"]
        lines.append(json.dumps(d, ensure_ascii=False))
    return "\n".join(lines)


PREFERRED = ["fill_blank", "one_word", "true_false", "mcq", "one_sentence", "solve", "match",
             "reason", "short_answer", "define", "difference", "odd_one", "explain", "descriptive"]


def pick_questions(qs, n, seed=None):
    """Balanced pick: spread over qtypes, textbook (book) items first, skip figure-only/draw items."""
    rnd = random.Random(seed)
    qs = [q for q in qs if q.get("text") and q.get("qtype") not in ("draw",) and not q.get("needs_figure")]
    by_type = {}
    for q in qs:
        by_type.setdefault(q.get("qtype"), []).append(q)
    for lst in by_type.values():
        lst.sort(key=lambda q: (q.get("source") != "book", rnd.random()))
    out = []
    while len(out) < n and any(by_type.values()):
        for t in PREFERRED + [t for t in by_type if t not in PREFERRED]:
            if by_type.get(t):
                out.append(by_type[t].pop(0))
                if len(out) >= n:
                    break
    order = {t: i for i, t in enumerate(PREFERRED)}
    out.sort(key=lambda q: order.get(q.get("qtype"), 99))
    return out


_CH_NO = re.compile(r"(पाठ|प्रकरण|Chapter|Lesson|Ch\.?|अध्याय)\s*(क्र\.?\s*)?(\d+|[०-९]+)", re.I)


def _fix_chapter_no(script, chapter, log):
    """The LLM sometimes copies a chapter number from the OCR text; force the selected one."""
    no = str(chapter.get("no") or "").strip()
    if not no:
        return
    if isinstance(script.get("thumbnail"), dict):
        for k, v in script["thumbnail"].items():
            if isinstance(v, str):
                script["thumbnail"][k] = _CH_NO.sub(lambda m: f"{m.group(1)} {no}" if m.group(3) != no else m.group(0), v)
    for key in ("title", "description", "thumbnail_text", "hashtags"):
        v = script.get(key)
        if isinstance(v, str):
            fixed = _CH_NO.sub(lambda m: f"{m.group(1)} {no}" if m.group(3) != no else m.group(0), v)
            if fixed != v:
                log(f"[script] fixed chapter number in {key}")
                script[key] = fixed
        elif isinstance(v, list):
            script[key] = [_CH_NO.sub(lambda m: f"{m.group(1)}{no}" if m.group(3) != no else m.group(0), x) if isinstance(x, str) else x for x in v]


def generate(cfg, book, chapter, questions, text="", kind="स्वाध्याय (textbook exercise) solutions", log=print):
    with open(os.path.join(ROOT, "style", "reference_style.md"), encoding="utf-8") as f:
        style = f.read()
    user = PROMPT.format(
        style=style, std=book["std"], subject=book["subject"], subject_mr=SUBJECT_MR.get(book["subject"], book["subject"]),
        lang=book["lang"], lang_name=LANG_NAME.get(book["lang"], book["lang"]), chapter=(f"{chapter['no']}. " if chapter.get("no") else "") + chapter["title"],
        channel=cfg["channel_name"], tagline=cfg["channel_tagline"], kind=kind,
        text=text[:18000] if text else "(not available)", questions=_fmt_questions(questions),
    )
    log(f"[script] asking LLM for {len(questions)} questions ...")
    script, model = chat_json(cfg, SYSTEM, user, max_tokens=12000, log=log)
    script["model"] = model
    _fix_chapter_no(script, chapter, log)
    script.setdefault("segments", [])
    for i, seg in enumerate(script["segments"], 1):
        seg.setdefault("no", i)
        src = questions[i - 1] if i - 1 < len(questions) else {}
        seg.setdefault("qtype", src.get("qtype", ""))
        seg["bq_id"] = src.get("id")
        seg["source"] = src.get("source", "")
        if not seg.get("answer") and src.get("answer"):
            seg["answer"] = src["answer"]
        if not seg.get("say_q"):
            seg["say_q"] = seg.get("question", "")
        if not seg.get("say_a"):
            seg["say_a"] = seg.get("answer", "")
    return script
