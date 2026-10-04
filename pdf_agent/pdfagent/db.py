"""SQLite store for everything the agent builds, plus exports.

Tables (compatible with yt_explain/explain.db so the video tool can read the same file):
  books(book_id, std, subject, lang, medium, pdf, n_pages, title)
  pages(book_id, page, md, heading)                       -- OCR text per PDF page ('## ' line = chapter start)
  units(unit_id, book_id, std, subject, lang, chapter_no, chapter_title, title, first_page, last_page, meta_json, ...)
  unit_text(unit_id, part_no, heading, text, selected)    -- clean teaching parts
  unit_figures(unit_id, fig_no, page, file, caption, near, kind, part_no)
  questions(id, unit_id, part_no, kind, qtype, instruction, text, options_json, pairs_json, answer, explain, marks, bloom, fig_file, source, model)
"""
import json, os, sqlite3

SCHEMA = """
CREATE TABLE IF NOT EXISTS books(book_id INTEGER PRIMARY KEY, std INT, subject TEXT, lang TEXT, medium TEXT, pdf TEXT, n_pages INT, title TEXT);
CREATE TABLE IF NOT EXISTS pages(book_id INT, page INT, md TEXT, heading TEXT, PRIMARY KEY(book_id, page));
CREATE TABLE IF NOT EXISTS units(unit_id TEXT PRIMARY KEY, topic_key TEXT, std INT, subject TEXT, lang TEXT, book_id INT, chapter_no INT,
  chapter_title TEXT, block TEXT, title TEXT, n_questions INT DEFAULT 0, meta_json TEXT, status TEXT DEFAULT 'pending', tries INT DEFAULT 0,
  error TEXT, out_dir TEXT, youtube_url TEXT, short_url TEXT, updated_at TEXT, first_page INT, last_page INT);
CREATE TABLE IF NOT EXISTS unit_text(unit_id TEXT, part_no INT, heading TEXT, text TEXT, selected INT DEFAULT 1, PRIMARY KEY(unit_id, part_no));
CREATE TABLE IF NOT EXISTS unit_figures(unit_id TEXT, fig_no INT, page INT, file TEXT, caption TEXT, near TEXT, kind TEXT, part_no INT, PRIMARY KEY(unit_id, fig_no));
CREATE TABLE IF NOT EXISTS questions(id INTEGER PRIMARY KEY AUTOINCREMENT, unit_id TEXT, part_no INT, kind TEXT, qtype TEXT, instruction TEXT, text TEXT,
  options_json TEXT, pairs_json TEXT, answer TEXT, explain TEXT, marks INT, bloom TEXT, fig_file TEXT, source TEXT, model TEXT);
CREATE INDEX IF NOT EXISTS q_unit ON questions(unit_id, kind);
CREATE TABLE IF NOT EXISTS unit_notes(unit_id TEXT, part_no INT, notes_json TEXT, model TEXT, PRIMARY KEY(unit_id, part_no));
CREATE TABLE IF NOT EXISTS paper_items(id INTEGER PRIMARY KEY AUTOINCREMENT, book_id INT, page INT, no TEXT, section TEXT, passage_id TEXT, passage TEXT,
  passage_file TEXT, text TEXT, q_file TEXT, options_json TEXT, option_files_json TEXT, answer TEXT, solution TEXT, marks INT, model TEXT);
CREATE INDEX IF NOT EXISTS pi_book ON paper_items(book_id, page);
CREATE TABLE IF NOT EXISTS jobs(key TEXT PRIMARY KEY, status TEXT, detail TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
"""


class Store:
    def __init__(self, path):
        self.path = path
        self.db = sqlite3.connect(path, check_same_thread=False)
        self.db.row_factory = sqlite3.Row
        self.db.executescript(SCHEMA)

    # ---- books / pages ----
    def add_book(self, std, subject, lang, medium, pdf, n_pages, title=None, book_id=None):
        cur = self.db.execute("INSERT OR REPLACE INTO books(book_id,std,subject,lang,medium,pdf,n_pages,title) VALUES(?,?,?,?,?,?,?,?)",
                              (book_id, std, subject, lang, medium, pdf, n_pages, title or os.path.basename(pdf)))
        self.db.commit()
        return book_id or cur.lastrowid

    def book(self, book_id):
        return self.db.execute("SELECT * FROM books WHERE book_id=?", (book_id,)).fetchone()

    def books(self):
        return self.db.execute("SELECT * FROM books ORDER BY std, subject").fetchall()

    def has_page(self, book_id, page):
        return self.db.execute("SELECT 1 FROM pages WHERE book_id=? AND page=?", (book_id, page)).fetchone() is not None

    def save_page(self, book_id, page, md):
        heading = next((l[3:].strip() for l in md.splitlines() if l.startswith("## ")), None)
        self.db.execute("INSERT OR REPLACE INTO pages(book_id,page,md,heading) VALUES(?,?,?,?)", (book_id, page, md, heading))
        self.db.commit()

    def pages(self, book_id):
        return self.db.execute("SELECT page, md, heading FROM pages WHERE book_id=? ORDER BY page", (book_id,)).fetchall()

    # ---- units / parts / figures ----
    def save_unit(self, unit_id, book_id, std, subject, lang, chapter_no, title, first, last, meta=None):
        self.db.execute("""INSERT OR REPLACE INTO units(unit_id,topic_key,std,subject,lang,book_id,chapter_no,chapter_title,title,meta_json,first_page,last_page,status)
                           VALUES(?,?,?,?,?,?,?,?,?,?,?,?,'pending')""",
                        (unit_id, unit_id, std, subject, lang, book_id, chapter_no, title, f"{chapter_no}. {title}", json.dumps(meta or {}, ensure_ascii=False), first, last))
        self.db.commit()

    def units(self, book_id=None, std=None):
        q, p = "SELECT * FROM units WHERE 1=1", []
        if book_id:
            q += " AND book_id=?"; p.append(book_id)
        if std:
            q += " AND std=?"; p.append(std)
        return self.db.execute(q + " ORDER BY std, subject, chapter_no", p).fetchall()

    def unit_text(self, unit_id):
        return " \n\n".join(r["md"] for r in self.db.execute(
            "SELECT p.md FROM pages p JOIN units u ON u.book_id=p.book_id WHERE u.unit_id=? AND p.page BETWEEN u.first_page AND u.last_page ORDER BY p.page", (unit_id,)))

    def parts(self, unit_id):
        return self.db.execute("SELECT * FROM unit_text WHERE unit_id=? ORDER BY part_no", (unit_id,)).fetchall()

    def save_parts(self, unit_id, parts):
        self.db.execute("DELETE FROM unit_text WHERE unit_id=?", (unit_id,))
        self.db.executemany("INSERT INTO unit_text(unit_id,part_no,heading,text,selected) VALUES(?,?,?,?,1)",
                            [(unit_id, i + 1, p["heading"], p["text"]) for i, p in enumerate(parts)])
        self.db.commit()

    def figures(self, unit_id, part_no=None):
        q, p = "SELECT * FROM unit_figures WHERE unit_id=?", [unit_id]
        if part_no is not None:
            q += " AND part_no=?"; p.append(part_no)
        return self.db.execute(q + " ORDER BY fig_no", p).fetchall()

    def save_figures(self, unit_id, figs):
        self.db.execute("DELETE FROM unit_figures WHERE unit_id=?", (unit_id,))
        self.db.executemany("INSERT INTO unit_figures VALUES(?,?,?,?,?,?,?,?)",
                            [(unit_id, i + 1, f["page"], f["file"], f.get("caption", ""), f.get("near", ""), f.get("kind", "drawing"), f.get("part_no")) for i, f in enumerate(figs)])
        self.db.commit()

    # ---- notes ----
    def notes(self, unit_id):
        return self.db.execute("SELECT * FROM unit_notes WHERE unit_id=? ORDER BY part_no", (unit_id,)).fetchall()

    def save_notes(self, unit_id, part_no, notes, model=None):
        self.db.execute("INSERT OR REPLACE INTO unit_notes VALUES(?,?,?,?)", (unit_id, part_no, json.dumps(notes, ensure_ascii=False), model))
        self.db.commit()

    # ---- competitive papers ----
    def save_paper_items(self, book_id, page, rows):
        self.db.execute("DELETE FROM paper_items WHERE book_id=? AND page=?", (book_id, page))
        self.db.executemany("""INSERT INTO paper_items(book_id,page,no,section,passage_id,passage,passage_file,text,q_file,options_json,option_files_json,answer,solution,marks,model)
                               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
                            [(book_id, page, r["no"], r["section"], r["passage_id"], r["passage"], r["passage_file"], r["text"], r["q_file"],
                              json.dumps(r["options"], ensure_ascii=False), json.dumps(r["option_files"]), r["answer"], r["solution"], r["marks"], r["model"]) for r in rows])
        self.db.commit()

    def apply_answer_key(self, book_id, keys):
        n = 0
        for no, a in keys.items():
            n += self.db.execute("UPDATE paper_items SET answer=? WHERE book_id=? AND no=? AND (answer IS NULL OR answer='')", (a, book_id, no)).rowcount
        self.db.commit()
        return n

    def paper_items(self, book_id):
        return self.db.execute("SELECT * FROM paper_items WHERE book_id=? ORDER BY page, id", (book_id,)).fetchall()

    # ---- questions ----
    def save_questions(self, unit_id, kind, items, part_no=None, model=None, source="agent"):
        if part_no is None:
            self.db.execute("DELETE FROM questions WHERE unit_id=? AND kind=?", (unit_id, kind))
        else:
            self.db.execute("DELETE FROM questions WHERE unit_id=? AND kind=? AND part_no=?", (unit_id, kind, part_no))
        self.db.executemany("""INSERT INTO questions(unit_id,part_no,kind,qtype,instruction,text,options_json,pairs_json,answer,explain,marks,bloom,fig_file,source,model)
                               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)""",
                            [(unit_id, part_no, kind, q.get("qtype", "mcq" if kind == "mcq" else "descriptive"), q.get("instruction", ""), q.get("q") or q.get("text"),
                              json.dumps(q.get("options") or [], ensure_ascii=False) if q.get("options") else None,
                              json.dumps(q.get("pairs"), ensure_ascii=False) if q.get("pairs") else None,
                              json.dumps(q["answer"], ensure_ascii=False) if isinstance(q.get("answer"), (list, dict)) else (str(q["answer"]) if q.get("answer") is not None else None),
                              q.get("explain"), q.get("marks"), q.get("bloom"), q.get("fig_file"), source, model) for q in items])
        self.db.commit()

    def questions(self, unit_id, kind=None):
        q, p = "SELECT * FROM questions WHERE unit_id=?", [unit_id]
        if kind:
            q += " AND kind=?"; p.append(kind)
        return self.db.execute(q + " ORDER BY part_no, id", p).fetchall()

    def count(self, table, **where):
        w = " AND ".join(f"{k}=?" for k in where) or "1=1"
        return self.db.execute(f"SELECT COUNT(*) FROM {table} WHERE {w}", list(where.values())).fetchone()[0]

    # ---- jobs (resume) ----
    def job_done(self, key):
        r = self.db.execute("SELECT status FROM jobs WHERE key=?", (key,)).fetchone()
        return r is not None and r["status"] == "done"

    def job(self, key, status, detail=""):
        self.db.execute("INSERT OR REPLACE INTO jobs(key,status,detail,updated_at) VALUES(?,?,?,CURRENT_TIMESTAMP)", (key, status, detail[:500]))
        self.db.commit()
