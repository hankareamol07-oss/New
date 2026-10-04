"""Step – chapter notes (टिपणे) per teaching part, from the clean textbook text: key points, definitions, formulas/examples,
important dates/names, common mistakes and a 3-5 line summary – in the book's language. Saved in unit_notes, exported to
Word/Excel by office.py. Resumable per part."""
import json
from concurrent.futures import ThreadPoolExecutor

LANG = {"mr": "Marathi", "hi": "Hindi", "en": "English"}
SYS = "You are an experienced Maharashtra State Board (Balbharati) teacher writing revision notes. Answer only with the requested JSON."
PROMPT = """Standard {std}, subject {subject}, chapter "{title}", topic "{heading}". Write concise revision notes for a std {std} pupil
in {lang} (numbers, symbols, formulas and technical terms exactly as in the textbook). Use ONLY the textbook text below; do not add
facts that are not there. {detail}
Return JSON: {{"summary": "3-5 lines", "points": ["key point", ...  6-12 items],
 "definitions": [{{"term": "...", "meaning": "..."}}], "formulas_examples": ["formula or worked example as in the text", ...],
 "names_dates": ["important person / place / date / value with one-line note"], "mistakes": ["common mistake and the correct idea"],
 "remember": ["1-3 memory tricks / one-line takeaways"]}}
Leave a list empty when the topic has nothing of that kind.

TOPIC TEXT:
<<<
{text}
>>>"""
DETAIL = {"short": "Keep it brief (points only, max 6).", "normal": "", "detailed": "Be thorough: cover every sub-point, example and figure caption."}


def build_notes(client, store, unit, workers=4, detail="normal", log=print):
    ps = [p for p in store.parts(unit["unit_id"]) if len((p["text"] or "").split()) >= 25]
    done = {n["part_no"] for n in store.notes(unit["unit_id"])}
    todo = [p for p in ps if p["part_no"] not in done]

    def one(p):
        js, model = client.chat_json(SYS, PROMPT.format(std=unit["std"], subject=unit["subject"], title=unit["chapter_title"], heading=p["heading"],
                                                        lang=LANG.get(unit["lang"], "Marathi"), detail=DETAIL.get(detail, ""), text=p["text"][:12000]))
        if not isinstance(js, dict) or not js.get("points"):
            raise ValueError("notes: empty")
        store.save_notes(unit["unit_id"], p["part_no"], js, model)
        return p["part_no"]

    n = 0
    with ThreadPoolExecutor(workers) as ex:
        for f in [ex.submit(one, p) for p in todo]:
            try:
                f.result(); n += 1
            except Exception as e:  # noqa: BLE001
                if "cost cap" in str(e):
                    raise
                log(f"  [notes] ERR {unit['unit_id']}: {str(e)[:120]}")
    return n, len(ps)
