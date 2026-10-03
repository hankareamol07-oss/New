"""Step 3 – parts: the LLM cleans a chapter's OCR text (page breaks, hyphenation, stray captions) and splits it into
4-12 teaching parts (heading + textbook wording, no paraphrase). The exercise section becomes its own part headed
'स्वाध्याय' / 'Exercise'. Figures ([चित्र: ...] markers) are attached to the part they appear in (figures.py)."""
import re

SYS = "You are an experienced Maharashtra State Board teacher preparing lesson material. Output only JSON."
PROMPT = """Standard {std}, subject {subject}, chapter "{title}" ({lang}). Below is the OCR text of the whole chapter (page by page).
Clean it (join words split across lines/pages, remove page numbers/running headers/OCR garbage/[चित्र: ...] markers) WITHOUT changing,
summarising or translating the textbook sentences, and split it into {lo}-{hi} parts in textbook order, each a coherent topic
(e.g. introduction story, a concept, an activity box, a poem's stanzas, 'आपण काय शिकलो'). Put the complete exercise section
(स्वाध्याय / Exercise / अभ्यास / सराव, all its questions) as the LAST part with heading exactly "{ex_head}".
Return JSON: {{"parts": [{{"heading": "short heading in {lang}", "text": "full cleaned textbook text of this part"}}]}}

CHAPTER TEXT:
<<<
{text}
>>>"""
EX_HEAD = {"mr": "स्वाध्याय", "hi": "अभ्यास", "en": "Exercise"}


def build_parts(client, store, unit, log=print):
    text = store.unit_text(unit["unit_id"])
    if len(text) < 200:
        log(f"  [parts] {unit['unit_id']}: chapter text too short ({len(text)} chars) – skipped")
        return []
    lo, hi = (3, 6) if len(text) < 3000 else (5, 9) if len(text) < 9000 else (7, 12)
    text = text[:60000]
    js, model = client.chat_json(SYS, PROMPT.format(std=unit["std"], subject=unit["subject"], title=unit["chapter_title"], lang=unit["lang"],
                                                     lo=lo, hi=hi, ex_head=EX_HEAD.get(unit["lang"], "Exercise"), text=text), max_tokens=32768)
    parts = [p for p in js.get("parts", []) if isinstance(p, dict) and len(str(p.get("text", "")).strip()) > 40]
    if len(parts) < 2:
        raise ValueError(f"parts: got {len(parts)}")
    # sanity: parts must mostly be textbook words (guard against summaries)
    src = set(re.findall(r"\w+", text))
    kept = sum(1 for p in parts for w in re.findall(r"\w+", p["text"]) if w in src)
    total = sum(len(re.findall(r"\w+", p["text"])) for p in parts) or 1
    if kept / total < 0.7:
        raise ValueError(f"parts: only {kept / total:.0%} of words come from the textbook text")
    store.save_parts(unit["unit_id"], parts)
    return parts
