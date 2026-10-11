"""AI story writer: theme / moral / textbook text -> story JSON (scenes, cast, lines) for render.py."""
import os
import sys

from . import paths

ACTIONS = ["talk", "explain", "point", "wave", "greet", "think", "idle"]
LANG_NAME = {"mr": "Marathi (Devanagari)", "hi": "Hindi (Devanagari)", "en": "simple Indian English"}

SYSTEM = """You write short animated story videos for school children (std 1-8).
Return ONLY JSON:
{"title": str,
 "cast": {"<id>": {"name": str, "character": "<character id from the list>", "gender": "male"|"female"}},
 "scenes": [{"background": "<background id from the list>", "camera": "slow-zoom"|"pan"|"static",
             "characters": [{"id": "<cast id>", "position": "left"|"center"|"right", "enter": null|"left"|"right", "exit": null|"left"|"right"}],
             "lines": [{"speaker": "narrator"|"<cast id>", "text": str, "action": one of ACTIONS}]}]}
Rules: 2-3 characters, every speaker must be in that scene's characters; at most 3 characters per scene, each at a different position;
narrator opens the story and the last line states the moral ("तात्पर्य:" in Marathi, "सीख:" in Hindi, "Moral:" in English);
short natural spoken sentences (max ~18 words each), age-appropriate, no violence."""


def _llm():
    try:
        from ytstudio import llm
    except ImportError:
        sys.path.insert(0, os.path.join(os.path.dirname(paths.HERE), "yt_studio"))
        from ytstudio import llm
    return llm


def generate(cfg, theme, lang="mr", moral="", text="", scenes=2, lines=8, characters=(), backgrounds=(), log=print):
    """characters: [(id, label, gender)], backgrounds: [id]."""
    from . import config
    llm = _llm()
    c = config.llm_cfg(cfg)
    if not c["llm_order"]:
        raise RuntimeError("No AI key: open Settings and add a Gemini / NVIDIA / Groq key (or a GCP key file).")
    chars = "\n".join(f"- {i}: {lab} ({g})" for i, lab, g in characters)
    user = (f"Language: {LANG_NAME.get(lang, lang)}\nTheme / title: {theme}\nMoral: {moral or '(choose a fitting one)'}\n"
            f"Scenes: {scenes}, total lines about {lines}\nACTIONS: {', '.join(ACTIONS)}\n"
            f"Characters available:\n{chars}\nBackgrounds available: {', '.join(backgrounds)}\n"
            + (f"Base the story on this textbook text:\n{text[:6000]}\n" if text.strip() else ""))
    story, model = llm.chat_json(c, SYSTEM, user, max_tokens=4096, log=log)
    log(f"story by {model}")
    return fix(story, lang, characters, backgrounds)


def fix(story, lang, characters, backgrounds):
    """Make AI output safe to render: known characters/backgrounds/actions, speakers present in scene."""
    ids = {i: g for i, _, g in characters}
    by_g = {g: [i for i, _, gg in characters if gg == g] for g in ("male", "female")}
    used = set()
    cast = {}
    for k, c in (story.get("cast") or {}).items():
        ch = c.get("character")
        g = c.get("gender") if c.get("gender") in ("male", "female") else ids.get(ch, "female")
        if ch not in ids or ch in used:
            ch = next((i for i in by_g.get(g, []) if i not in used), None) or next(i for i in ids if i not in used)
        used.add(ch)
        cast[k] = {"name": c.get("name") or k, "character": ch, "gender": g}
    out = {"title": story.get("title", ""), "lang": lang, "cast": cast, "scenes": []}
    pos = ["left", "right", "center"]
    for sc in story.get("scenes") or []:
        bg = sc.get("background") if sc.get("background") in backgrounds else (backgrounds[0] if backgrounds else "village")
        chars, seen = [], set()
        for c in sc.get("characters") or []:
            if c.get("id") in cast and c["id"] not in seen and len(chars) < 3:
                seen.add(c["id"])
                p = c.get("position") if c.get("position") in pos else pos[len(chars)]
                chars.append({"id": c["id"], "position": p, "enter": c.get("enter") if c.get("enter") in ("left", "right") else None,
                              "exit": c.get("exit") if c.get("exit") in ("left", "right") else None})
        lines = []
        for ln in sc.get("lines") or []:
            sp = ln.get("speaker", "narrator")
            if sp != "narrator" and sp not in cast:
                sp = "narrator"
            if sp != "narrator" and sp not in seen and len(chars) < 3:
                seen.add(sp)
                chars.append({"id": sp, "position": next(p for p in pos if p not in [c["position"] for c in chars])})
            if sp != "narrator" and sp not in seen:
                sp = "narrator"
            if (ln.get("text") or "").strip():
                lines.append({"speaker": sp, "text": ln["text"].strip(),
                              "action": ln.get("action") if ln.get("action") in ACTIONS else "talk"})
        if lines:
            out["scenes"].append({"background": bg, "camera": sc.get("camera", "slow-zoom") if sc.get("camera") in ("slow-zoom", "pan", "static") else "slow-zoom",
                                  "transition": "fade", "characters": chars, "lines": lines})
    return out
