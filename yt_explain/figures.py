"""Textbook figures per chapter: books/figs/<book>/<page>_<k>.png (found by books/vertex_figs.py) are copied into the
chapter's source pack (sources/<chapter>/figures/) and listed in explain.db unit_figures, each linked to the text part
whose words it sits next to, so the script/slides can show the textbook picture where the text refers to it."""
import json, os, re, shutil, sys

HERE = os.path.dirname(os.path.abspath(__file__))
FIGS = os.path.expanduser("~/books/figs")
BOOKS = os.path.expanduser("~/books/book_questions.json")


def ensure_table(db):
    db.executescript("""
    CREATE TABLE IF NOT EXISTS unit_figures (
      unit_id TEXT, fig_no INT, page INT, file TEXT, caption TEXT, near TEXT, kind TEXT, part_no INT,
      PRIMARY KEY (unit_id, fig_no));
    """)


def get(st, unit_id):
    ensure_table(st.db)
    return [dict(r) for r in st.db.execute("SELECT fig_no, page, file, caption, near, kind, part_no FROM unit_figures WHERE unit_id=? ORDER BY fig_no", (unit_id,))]


def path(meta, fig):
    return os.path.join(HERE, meta.get("sources_dir", ""), "figures", fig["file"])


def _norm(s):
    return re.sub(r"[^\w\u0900-\u097f]+", " ", s or "").strip().lower()


def assign_parts(st, unit_id, parts):
    """Link every figure to the part containing its 'near' words (else caption words); unlinked figures keep part_no NULL."""
    ensure_table(st.db)
    texts = [(p["part_no"], _norm(p.get("heading", "") + " " + p.get("text", ""))) for p in parts]
    for f in get(st, unit_id):
        part = None
        for key in (f.get("near"), f.get("caption")):
            words = _norm(key).split()
            for n in (6, 4, 3):
                if len(words) < n:
                    continue
                probe = " ".join(words[:n])
                hits = [pn for pn, t in texts if probe in t]
                if hits:
                    part = hits[0]
                    break
            if part is not None:
                break
        st.db.execute("UPDATE unit_figures SET part_no=? WHERE unit_id=? AND fig_no=?", (part, unit_id, f["fig_no"]))
    st.commit()


def for_script(st, meta, unit_id, parts):
    """Figures of the selected parts (plus unlinked ones), numbered for the script prompt: [{no, caption, part, file}]."""
    sel = {p["part_no"] for p in parts if p.get("selected", 1)} if parts else set()
    heads = {p["part_no"]: p.get("heading", "") for p in parts or []}
    out = []
    for f in get(st, unit_id):
        if parts and f.get("part_no") is not None and f["part_no"] not in sel:
            continue
        fp = path(meta, f)
        if os.path.exists(fp):
            out.append({"no": f["fig_no"], "caption": f.get("caption") or f.get("near") or "", "kind": f.get("kind", ""),
                        "part": heads.get(f.get("part_no"), ""), "file": fp})
    return out


def import_all(st, only_book=None, log=print):
    books = {b["book_id"]: b for b in json.load(open(BOOKS, encoding="utf-8"))["books"]}
    n_units = n_figs = 0
    for r in st.db.execute("SELECT unit_id, book_id, meta_json FROM units WHERE book_id IS NOT NULL").fetchall():
        unit_id, bid, mj = r[0], r[1], r[2]
        if only_book and bid != only_book or bid not in books:
            continue
        meta = json.loads(mj or "{}")
        pg = meta.get("pages")
        if not pg or not meta.get("sources_dir"):
            continue
        d = os.path.join(FIGS, os.path.splitext(books[bid]["file"])[0])
        dst = os.path.join(HERE, meta["sources_dir"], "figures")
        rows = []
        for p in range(int(pg[0]), int(pg[1]) + 1):
            jp = os.path.join(d, f"{p:03d}.json")
            if not os.path.exists(jp):
                continue
            for f in json.load(open(jp, encoding="utf-8")).get("figures", []):
                src = os.path.join(d, f["file"])
                if not os.path.exists(src):
                    continue
                os.makedirs(dst, exist_ok=True)
                shutil.copy2(src, os.path.join(dst, f["file"]))
                rows.append((p, f["file"], f.get("caption", ""), f.get("near", ""), f.get("kind", "")))
        if not rows:
            continue
        st.db.execute("DELETE FROM unit_figures WHERE unit_id=?", (unit_id,))
        for i, (p, fn, cap, near, kind) in enumerate(rows, 1):
            st.db.execute("INSERT INTO unit_figures(unit_id, fig_no, page, file, caption, near, kind) VALUES (?,?,?,?,?,?,?)",
                          (unit_id, i, p, fn, cap, near, kind))
        parts = [dict(x) for x in st.db.execute("SELECT part_no, heading, text FROM unit_text WHERE unit_id=?", (unit_id,))]
        st.commit()
        if parts:
            assign_parts(st, unit_id, parts)
        n_units += 1
        n_figs += len(rows)
    st.commit()
    log(f"[figures] {n_figs} figures for {n_units} chapters")


if __name__ == "__main__":
    import auto
    st = auto.open_state()
    ensure_table(st.db)
    import_all(st, int(sys.argv[1]) if len(sys.argv) > 1 else None)
