"""Step 5 – question bank per chapter, from the clean parts (same recipe as the exam-paper rebuild):
  * स्वाध्याय: the textbook's own exercise part converted to a clean item list (qtype, options/pairs, model answer, marks)
    – or, when the book prints no exercise, a Balbharati-style set generated from the text (flagged generated)
  * 10-15 MCQs per substantial topic part, grounded in the text, 4 options, explanation
Every item: Bloom's level (remember/understand/apply/analyze/evaluate/create); parts with figures get 2-3 picture questions."""
import os, re
from concurrent.futures import ThreadPoolExecutor

BLOOM = ["remember", "understand", "apply", "analyze", "evaluate", "create"]
ALIAS = {"knowledge": "remember", "recall": "remember", "comprehension": "understand", "application": "apply", "analysis": "analyze",
         "analyse": "analyze", "evaluation": "evaluate", "synthesis": "create", "creating": "create", "remembering": "remember",
         "understanding": "understand", "applying": "apply", "analyzing": "analyze", "evaluating": "evaluate"}
QTYPES = {"fill_blank", "true_false", "match", "mcq", "one_word", "one_sentence", "short_answer", "descriptive", "solve", "draw", "reason",
          "activity", "odd_one", "define", "difference", "vocabulary", "grammar", "explain", "label_diagram", "observe"}
EXER = re.compile(r"स्वाध्याय|Exercise|अभ्यास|सराव", re.I)
LANG = {"mr": "Marathi", "hi": "Hindi", "en": "English"}
SYS = "You are an experienced Maharashtra State Board (Balbharati) teacher. Answer only with the requested JSON."
BLOOM_RULE = """Tag every item with "bloom": one of remember, understand, apply, analyze, evaluate, create (Bloom's taxonomy);
spread the items over the levels: ~30% remember, ~30% understand, ~20% apply, ~20% analyze/evaluate/create (only what the text allows)."""
FIG_RULE = """{nf} textbook figure(s) of this topic are attached as images, numbered F1..F{nf} (details: {figs}).
Make {nq} of the questions picture-based (identify / read the picture, label, compare, infer from the figure); for those set
"fig": <figure number 1..{nf}> and word the question so it refers to the picture ("In the given figure ..." / "दिलेल्या आकृतीत ...");
never write the figure number (F1, F2) in the question text, options or answer. Other items: "fig": null."""
MCQ = """Standard {std}, subject {subject}, chapter "{title}", topic "{heading}". Language of the questions: {lang}
(keep numbers, symbols and formulas as printed; technical terms as in the textbook).
Below is the exact textbook text of this topic. Write {n} multiple-choice questions that test understanding of THIS topic only
(facts, definitions, examples, small calculations for maths, meanings for poems/prose). Every question must be answerable
from the text (or the attached figure); do not invent facts. 4 options each, exactly one correct, plausible distractors, no "all of the above".
Suitable for a std {std} pupil. {bloom}
{fig}
Return JSON: {{"mcqs": [{{"q": "...", "options": ["...","...","...","..."], "answer": <0-3>, "explain": "one line, in {lang}", "bloom": "...", "fig": null}}]}}

TOPIC TEXT:
<<<
{text}
>>>"""
EX = """Standard {std}, subject {subject}, chapter "{title}". Below is the textbook's own exercise section (स्वाध्याय / Exercise),
transcribed from the printed book. Convert it into a clean question list for a printed test paper, in the book's language ({lang}).
Rules: keep the textbook's wording; one item per question (split "अ) ... आ) ..." sub-items into separate items, keep their
instruction); blanks as ______; for match-the-pairs give "pairs" as [[left,right],...] in the correct matched order; for MCQ
give "options"; supply the model "answer" for every item (short, from the chapter; for descriptive questions 2-4 lines);
qtype one of: {qtypes}. Drop page numbers and OCR garbage, nothing else.
Never output plain statements/instructions from the lesson as questions. {bloom}
{fig}
Return JSON: {{"exercises": [{{"instruction": "...", "qtype": "...", "text": "...", "options": [], "pairs": [], "answer": "...", "marks": <1-5>, "bloom": "...", "fig": null}}]}}

EXERCISE TEXT:
<<<
{text}
>>>"""
EXGEN = """Standard {std}, subject {subject}, chapter "{title}". This lesson has no printed exercise section. From the exact textbook text
below, write a Balbharati-style स्वाध्याय of 12-15 questions for a printed test paper, in the book's language ({lang}): mix of fill_blank
(blanks as ______), true_false, match (pairs [[left,right],...], 4-5 pairs), one_word / one_sentence, short_answer, 1-2 descriptive or reason,
and for maths: solve items with numbers from the lesson. Every question must be answerable from the text; do not invent facts.
Supply the model "answer" (short; 2-4 lines for descriptive) and "marks" 1-5. qtype one of: {qtypes}. {bloom}
{fig}
Return JSON: {{"exercises": [{{"instruction": "...", "qtype": "...", "text": "...", "options": [], "pairs": [], "answer": "...", "marks": <1-5>, "bloom": "...", "fig": null}}]}}

LESSON TEXT:
<<<
{text}
>>>"""


def norm_bloom(b):
    b = ALIAS.get(str(b or "").strip().lower(), str(b or "").strip().lower())
    return b if b in BLOOM else None


def n_for(length, lo=10, hi=15):
    return lo if length < 700 else (lo + hi) // 2 if length < 1500 else hi


def _figs(store, unit, part_no, workdir, limit):
    out = []
    for f in store.figures(unit["unit_id"], part_no):
        p = os.path.join(workdir, f["file"])
        if os.path.exists(p) and f["kind"] in ("diagram", "map", "chart", "photo", "table", "drawing"):
            out.append((p, f["file"], f["kind"], (f["caption"] or f["near"] or "")[:80]))
    out.sort(key=lambda x: {"diagram": 0, "map": 0, "chart": 1, "table": 1, "photo": 2, "drawing": 3}[x[2]])
    return out[:limit]


def _fig_rule(figs, nq):
    if not figs:
        return ""
    det = "; ".join(f"F{i + 1}: {k}" + (f' "{c}"' if c else "") for i, (_, _, k, c) in enumerate(figs))
    return FIG_RULE.format(nf=len(figs), figs=det, nq=nq)


def _finish(items, key, figs, mn):
    if not isinstance(items, list) or len(items) < mn:
        raise ValueError(f"{key}: got {0 if not isinstance(items, list) else len(items)} items")
    if key == "mcqs":
        items = [m for m in items if isinstance(m, dict) and isinstance(m.get("options"), list) and len(m["options"]) == 4
                 and isinstance(m.get("answer"), int) and 0 <= m["answer"] <= 3 and str(m.get("q") or "").strip()]
        if len(items) < mn:
            raise ValueError("mcqs: too few valid items")
    else:
        items = [e for e in items if isinstance(e, dict) and len(str(e.get("text") or "").strip()) >= 3]
        for e in items:
            if e.get("qtype") not in QTYPES:
                e["qtype"] = "descriptive"
    for it in items:
        it["bloom"] = norm_bloom(it.get("bloom"))
        f = it.pop("fig", None)
        if isinstance(f, int) and 1 <= f <= len(figs):
            it["fig_file"] = figs[f - 1][1]
    return items


def build_bank(client, store, unit, workdir, mcq_range=(10, 15), with_figures=True, workers=4, log=print):
    """Generate exercises + topic MCQs for one unit (skips jobs already done). Returns (n_ex, n_mcq)."""
    parts = store.parts(unit["unit_id"])
    if not parts:
        log(f"  [bank] {unit['unit_id']}: no parts yet – run parts first")
        return 0, 0
    ctx = dict(std=unit["std"], subject=unit["subject"], title=unit["chapter_title"], lang=LANG.get(unit["lang"], unit["lang"]), bloom=BLOOM_RULE, qtypes=", ".join(sorted(QTYPES)))
    jobs, pend, has_ex = [], "", False
    for p in parts:
        if EXER.search(p["heading"] or ""):
            has_ex = True
            figs = _figs(store, unit, p["part_no"], workdir, 2) if with_figures else []
            jobs.append(("ex", p["part_no"], EX.format(text=p["text"], fig=_fig_rule(figs, 1), **ctx), figs, 3, False))
            continue
        text = (pend + "\n\n" + p["text"]).strip() if pend else p["text"]
        if len(text) < 350:
            pend = text
            continue
        pend = ""
        figs = _figs(store, unit, p["part_no"], workdir, 3) if with_figures else []
        jobs.append(("mcq", p["part_no"], MCQ.format(heading=p["heading"], n=n_for(len(text), *mcq_range), text=text, fig=_fig_rule(figs, min(3, max(2, len(figs)))), **ctx), figs, 8, False))
    if pend:
        jobs.append(("mcq", parts[-1]["part_no"], MCQ.format(heading=parts[-1]["heading"], n=mcq_range[0], text=pend, fig="", **ctx), [], 6, False))
    if not has_ex:
        body = "\n\n".join(f"## {p['heading']}\n{p['text']}" for p in parts)[:14000]
        figs = []
        if with_figures:
            for p in parts:
                figs += _figs(store, unit, p["part_no"], workdir, 1)
        figs = figs[:2]
        jobs.append(("ex", None, EXGEN.format(text=body, fig=_fig_rule(figs, 2 if figs else 0), **ctx), figs, 8, True))
    n = {"ex": 0, "mcq": 0}

    def run(j):
        kind, pn, prompt, figs, mn, gen = j
        key = f"bank:{unit['unit_id']}:{kind}:{pn}"
        if store.job_done(key):
            n[kind] += store.count("questions", unit_id=unit["unit_id"], kind=kind) if kind == "ex" else len([1 for q in store.questions(unit["unit_id"], "mcq") if q["part_no"] == pn])
            return
        try:
            js, model = client.chat_json(SYS, prompt, [f[0] for f in figs])
            k = "mcqs" if kind == "mcq" else "exercises"
            items = _finish(js.get(k) if isinstance(js, dict) else None, k, figs, mn)
            store.save_questions(unit["unit_id"], kind, items, part_no=pn, model=model, source="generated" if gen else "textbook" if kind == "ex" else "topic")
            store.job(key, "done")
            n[kind] += len(items)
        except Exception as e:  # noqa: BLE001
            store.job(key, "error", str(e))
            log(f"  [bank] ERR {key}: {str(e)[:120]}")
            if "cost cap" in str(e):
                raise

    with ThreadPoolExecutor(workers) as ex:
        list(ex.map(run, jobs))
    return n["ex"], n["mcq"]
