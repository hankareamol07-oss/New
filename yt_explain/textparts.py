"""Chapter text as selectable parts (table unit_text in explain.db).

The OCR text of a chapter is noisy and unstructured, so on first use an LLM cleans it and splits it into 4-12
teaching parts (heading + textbook wording). The GUI lets the teacher tick which parts a video should cover and
edit the text; explain.generate() then writes the script from exactly those parts.
"""
import figures
import json

from ytstudio.llm import chat_json

SYSTEM = """You are a careful Maharashtra State Board textbook editor. Output STRICT JSON only."""

PROMPT = """Below is the text of one textbook chapter (std {std}, {subject}, "{title}"), transcribed from the printed
book. It may still contain a little OCR noise (broken lines, stray symbols, '[IMG]' markers, figure captions).

Task: split the chapter into its natural teaching parts (4-12 parts, in textbook order): introduction, each
sub-topic / definition / rule, worked examples, activities (कृती / करून पहा / जरा डोके चालवा), 'माहीत आहे का तुम्हांला?',
summary etc. For every part return a short heading (<= 40 chars, in the textbook language) and the text of that part
COPIED from the chapter: keep the textbook's own sentences word for word (join lines broken mid-sentence, fix an
obviously mis-read word only when the correct word is certain), keep numbers/formulas/examples/tables, drop
'[IMG]', page numbers and garbage. Do NOT summarise, paraphrase, translate or add anything that is not in the text.
Exercise questions (स्वाध्याय / Exercise / अभ्यास) go into one last part with heading "स्वाध्याय" (or "Exercise").
{poem_note}
Return JSON exactly: {{"parts": [{{"heading": "...", "text": "..."}}, ...]}}

CHAPTER TEXT:
<<<
{text}
>>>"""

POEM_NOTE = ("This chapter is a POEM: the first part must be heading 'कविता' with the full poem, one line per line "
             "(exact words, stanzas separated by a blank line); then parts for poet introduction, difficult words, "
             "and the meaning of each stanza as printed in the book, if present.")


def ensure_table(db):
    db.executescript("""
    CREATE TABLE IF NOT EXISTS unit_text (
      unit_id TEXT, part_no INT, heading TEXT, text TEXT, selected INT DEFAULT 1,
      PRIMARY KEY (unit_id, part_no));
    """)


def get_parts(st, unit_id):
    return [dict(r) for r in st.db.execute("SELECT part_no, heading, text, selected FROM unit_text WHERE unit_id=? ORDER BY part_no", (unit_id,))]


def save_parts(st, unit_id, parts):
    st.db.execute("DELETE FROM unit_text WHERE unit_id=?", (unit_id,))
    for i, p in enumerate(parts, 1):
        st.db.execute("INSERT INTO unit_text(unit_id, part_no, heading, text, selected) VALUES (?,?,?,?,?)",
                      (unit_id, i, p.get("heading", "").strip(), p.get("text", "").strip(), 1 if p.get("selected", 1) else 0))
    st.commit()


def _chunks(text, n):
    """Split a long chapter at paragraph breaks into pieces of at most n chars (one LLM call each)."""
    out, cur = [], ""
    for para in text.split("\n\n"):
        if cur and len(cur) + len(para) + 2 > n:
            out.append(cur)
            cur = ""
        cur = (cur + "\n\n" + para) if cur else para
    if cur:
        out.append(cur)
    return out or [text]


def build_parts(cfg, u, meta, text, log=print):
    """LLM: noisy OCR chapter -> [{heading, text}] (empty list when there is no textbook text)."""
    if not (text or "").strip():
        return []

    def prompt(n):
        return PROMPT.format(std=u["std"], subject=u["subject"], title=u["title"], text=text[:n],
                             poem_note=POEM_NOTE if meta.get("is_poem") else "")
    log("[text] cleaning chapter text into parts ...")
    def run(chunk):
        nonlocal text
        text = chunk
        out, _ = chat_json(cfg, SYSTEM, prompt(30000), max_tokens=12000, log=log, shrink=prompt)
        got = [p for p in out.get("parts") or [] if isinstance(p, dict) and (p.get("text") or "").strip()]
        cov = sum(len(p["text"]) for p in got) / max(len(chunk), 1)
        if len(chunk) > 1500 and cov < 0.4:
            # output was cut short (or the model summarised): redo in two smaller pieces
            halves = _chunks(chunk, len(chunk) // 2 + 1)
            if len(halves) > 1 and len(chunk) > 1500:
                log(f"[text] only {cov:.0%} of the text came back, retrying in {len(halves)} pieces")
                return [p for h in halves for p in run(h)]
            raise RuntimeError(f"parts cover only {cov:.0%} of the chapter text")
        return got

    parts = []
    for chunk in _chunks(text, 30000):
        parts += run(chunk)
    for p in parts:
        p["selected"] = 1
    return parts


def parts_or_build(cfg, st, u, meta, text, log=print):
    parts = get_parts(st, u["unit_id"])
    if not parts and text:
        parts = build_parts(cfg, u, meta, text, log)
        if parts:
            save_parts(st, u["unit_id"], parts)
            parts = get_parts(st, u["unit_id"])
            figures.assign_parts(st, u["unit_id"], parts)
    return parts


def selected_text(parts):
    """Text for the script prompt: only ticked parts, each under its heading. '' when nothing is ticked."""
    sel = [p for p in parts if p.get("selected")]
    return "\n\n".join(f"## {p['heading']}\n{p['text']}" for p in sel)


def as_json(parts):
    return json.dumps([{k: p[k] for k in ("heading", "text", "selected")} for p in parts], ensure_ascii=False, indent=1)
