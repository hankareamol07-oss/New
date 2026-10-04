"""Exports: (a) exam_paper data files – book_questions.json (chapters + स्वाध्याय items) and topic_packs.json (topic MCQ quiz),
with figure crops copied to data/book_figures/<book_id>/<page>_<100+k>.jpg so the paper/homework/quiz pages show them;
(b) a plain JSON/CSV dump of every table. The agent's SQLite file itself is explain.db-compatible for the video tool."""
import csv, json, os, re
from PIL import Image

FIG = re.compile(r"(\d{3})_(\d+)\.png$")


def _figure(workdir, out_data, book_id, fig_file):
    if not fig_file:
        return None
    m = FIG.search(fig_file)
    src = os.path.join(workdir, fig_file)
    if not m or not os.path.exists(src):
        return None
    rel = f"{book_id}/{m.group(1)}_{100 + int(m.group(2))}.jpg"
    dst = os.path.join(out_data, "book_figures", rel)
    if not os.path.exists(dst):
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        Image.open(src).convert("RGB").save(dst, "JPEG", quality=85)
    return rel


def _answer(v):
    if v is None:
        return None
    try:
        j = json.loads(v)
        if isinstance(j, (list, dict)):
            return ", ".join(map(str, j)) if isinstance(j, list) else "; ".join(f"{k}: {x}" for k, x in j.items())
    except (ValueError, TypeError):
        pass
    return str(v)


def export_exam_paper(store, workdir, out_data, book_ids=None, log=print):
    """Write <out_data>/book_questions.json + topic_packs.json (merging into existing files when present)."""
    os.makedirs(out_data, exist_ok=True)
    bq_path, tp_path = os.path.join(out_data, "book_questions.json"), os.path.join(out_data, "topic_packs.json")
    bq = json.load(open(bq_path, encoding="utf-8")) if os.path.exists(bq_path) else {"books": [], "questions": []}
    packs = {(p["book_id"], p["chapter_no"]): p for p in (json.load(open(tp_path, encoding="utf-8")) if os.path.exists(tp_path) else [])}
    books = {b["book_id"]: b for b in bq["books"]}
    nid = max([q["id"] for q in bq["questions"]] + [500000]) + 1
    touched = set()
    for b in store.books():
        if book_ids and b["book_id"] not in book_ids:
            continue
        units = store.units(book_id=b["book_id"])
        books[b["book_id"]] = {"book_id": b["book_id"], "std": b["std"], "subject": b["subject"], "lang": b["lang"], "medium": b["medium"] or "",
                               "file": os.path.basename(b["pdf"]), "pages": b["n_pages"],
                               "chapters": [{"no": u["chapter_no"], "title": u["chapter_title"], "pages": [u["first_page"], u["last_page"]]} for u in units]}
        for u in units:
            key = (b["book_id"], u["chapter_no"])
            ex = store.questions(u["unit_id"], "ex")
            if ex:
                touched.add(key)
                gen = any(q["source"] == "generated" for q in ex)
                for i, q in enumerate(ex, 1):
                    fi = _figure(workdir, out_data, b["book_id"], q["fig_file"])
                    bq["questions"].append(dict(id=nid, book_id=b["book_id"], std=b["std"], subject=b["subject"], lang=b["lang"], chapter_no=u["chapter_no"],
                                                chapter=u["chapter_title"], page=u["last_page"], block="AI स्वाध्याय" if gen else "", instruction=(q["instruction"] or "")[:400],
                                                qtype=q["qtype"], text=q["text"], item_no=i, needs_figure=bool(fi), page_image=fi,
                                                options=json.loads(q["options_json"]) if q["options_json"] else None,
                                                pairs=json.loads(q["pairs_json"]) if q["pairs_json"] else None, answer=_answer(q["answer"]),
                                                marks=q["marks"] or 1, ai_cleaned=True, figure_image=fi, model=q["model"], bloom=q["bloom"]))
                    nid += 1
            mcq = store.questions(u["unit_id"], "mcq")
            if mcq:
                heads = {p["part_no"]: p["heading"] for p in store.parts(u["unit_id"])}
                quiz = []
                for q in mcq:
                    d = dict(q=q["text"], options=json.loads(q["options_json"]), answer=int(q["answer"]), explain=q["explain"] or "", topic=heads.get(q["part_no"], ""), part=q["part_no"])
                    if q["bloom"]:
                        d["bloom"] = q["bloom"]
                    fi = _figure(workdir, out_data, b["book_id"], q["fig_file"])
                    if fi:
                        d["figure_image"] = fi
                    quiz.append(d)
                old = packs.get(key, {})
                packs[key] = dict(book_id=b["book_id"], chapter_no=u["chapter_no"], std=b["std"], subject=b["subject"], lang=b["lang"], chapter=u["chapter_title"],
                                  notes=old.get("notes", []), quiz=quiz, model=mcq[0]["model"], source="pdf_agent")
    bq["questions"] = [q for q in bq["questions"] if (q["book_id"], q["chapter_no"]) not in touched or q["id"] >= 500000]
    bq["books"] = sorted(books.values(), key=lambda x: (x["std"], x["subject"]))
    json.dump(bq, open(bq_path, "w", encoding="utf-8"), ensure_ascii=False, indent=0)
    json.dump(sorted(packs.values(), key=lambda p: (p["book_id"], p["chapter_no"])), open(tp_path, "w", encoding="utf-8"), ensure_ascii=False, indent=0)
    log(f"  [export] {len(bq['questions'])} स्वाध्याय items, {len(packs)} topic packs -> {out_data}  (then run exam_paper/import_books.php)")
    return bq_path, tp_path


def dump_tables(store, out_dir, log=print):
    os.makedirs(out_dir, exist_ok=True)
    for t in ("books", "units", "unit_text", "unit_figures", "unit_notes", "questions"):
        rows = [dict(r) for r in store.db.execute(f"SELECT * FROM {t}")]
        json.dump(rows, open(os.path.join(out_dir, f"{t}.json"), "w", encoding="utf-8"), ensure_ascii=False, indent=0)
        if rows:
            with open(os.path.join(out_dir, f"{t}.csv"), "w", encoding="utf-8-sig", newline="") as f:
                w = csv.DictWriter(f, fieldnames=rows[0].keys())
                w.writeheader()
                w.writerows(rows)
    log(f"  [export] tables dumped to {out_dir}")
