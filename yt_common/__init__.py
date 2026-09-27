"""Shared by the two YouTube projects (yt_swadhyay, yt_explain): canonical 2026-27 topic identity + sqlite state.

Topic key = "std|tachan_subject|seq"  (same key as exam_paper ep_book_chapters.tachan_seq and hpc_topic_map).
"""
import json
import os
import re
import sqlite3
import sys
from collections import OrderedDict

HERE = os.path.dirname(os.path.abspath(__file__))
REPO = os.path.dirname(HERE)
YT_STUDIO = os.path.join(REPO, "yt_studio")
if YT_STUDIO not in sys.path:
    sys.path.insert(0, YT_STUDIO)

TACHAN_SQL = os.environ.get("TACHAN_SQL", os.path.expanduser("~/tachan/pkg/sql/tachan_bank_2026.sql"))
BOOK_QUESTIONS = os.environ.get("BOOK_QUESTIONS", os.path.expanduser("~/books/book_questions.json"))
OCR_DIR = os.environ.get("OCR_DIR", os.path.expanduser("~/books/ocr"))

SUBJECT_TACHAN = {  # ep subject -> tachan subject names (std dependent variants)
    "Marathi": ["मराठी"], "Hindi": ["हिंदी"], "English": ["इंग्लिश", "इंग्रजी"], "Maths": ["गणित"],
    "Science": ["सामान्य विज्ञान", "विज्ञान"], "EVS": ["परिसर अभ्यास", "परिसर अभ्यास भाग 1", "परिसर अभ्यास भाग 2", "परिसर अभ्यास 1", "परिसर अभ्यास 2"],
    "Geography": ["भूगोल"], "History": ["इतिहास", "इतिहास व नागरिकशास्त्र"], "Civics": ["नागरिकशास्त्र"],
    "History & Civics": ["इतिहास व नागरिकशास्त्र", "इतिहास"], "Social_Science": ["इतिहास व नागरिकशास्त्र", "भूगोल"],
}


def topic_key(std, subject, seq):
    return f"{int(std)}|{subject}|{int(seq)}"


def load_tachan(path=TACHAN_SQL):
    """{(std, subject): OrderedDict(topic -> {seq, lo, act})} from the tachan_bank INSERT rows."""
    if not os.path.exists(path):
        return {}
    s = open(path, encoding="utf-8").read()
    rx = re.compile(r"\((\d+),'((?:[^'\\]|\\.)*)',(\d+),(\d+),(\d+),'((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)','((?:[^'\\]|\\.)*)'(?:,'((?:[^'\\]|\\.)*)')?(?:,'((?:[^'\\]|\\.)*)')?(?:,(\d+))?")
    un = lambda x: (x or "").replace("\\'", "'").replace("\\n", " ").strip()
    d = OrderedDict()
    for m in rx.finditer(s):
        std, sub, dn, mon, day, topic, act, ev, mat, lo, hw, status, seq = m.groups()
        lst = d.setdefault((int(std), un(sub)), OrderedDict())
        topic = un(topic)
        if topic not in lst:
            lst[topic] = {"seq": len(lst) + 1, "lo": un(lo), "act": un(act)}  # seq = topic order in the plan (matches ep_book_chapters.tachan_seq / hpc_topic_map)
        elif not lst[topic]["lo"] and un(lo):
            lst[topic]["lo"] = un(lo)
    return d


def load_books(path=BOOK_QUESTIONS):
    d = json.load(open(path, encoding="utf-8"))
    return d["books"], d["questions"]


def canonical(book, chapter, tachan):
    """Canonical topic for a book chapter: (key, tachan_subject, seq, topic) using tachan_seq / book.tachan_subject."""
    subs = [book.get("tachan_subject")] if book.get("tachan_subject") else SUBJECT_TACHAN.get(book["subject"], [])
    for sub in subs:
        t = tachan.get((book["std"], sub))
        if not t:
            continue
        seq = chapter.get("tachan_seq")
        if seq:
            for name, v in t.items():
                if v["seq"] == seq:
                    return topic_key(book["std"], sub, seq), sub, seq, name, v
        n = _norm(chapter["title"])
        for name, v in t.items():
            if n and (_norm(name) == n or (len(n) > 6 and n in _norm(name))):
                return topic_key(book["std"], sub, v["seq"]), sub, v["seq"], name, v
    return None


def _norm(t):
    t = re.sub(r"^\s*[0-9०-९]+[\.\)]\s*", "", t or "")
    t = re.sub(r"\(.*?\)", "", t)
    return re.sub(r"[^\w]+", "", t.lower())


def ocr_text(book, chapter, cap=30000, ocr_dir=OCR_DIR):
    d = os.path.join(ocr_dir, str(book.get("official_id") or os.path.splitext(book.get("file", ""))[0]))
    if not os.path.isdir(d):
        d = os.path.join(ocr_dir, os.path.splitext(book.get("file", ""))[0])
    parts = []
    off = 1 if book.get("official_id") and os.path.isdir(os.path.join(ocr_dir, str(book["official_id"]))) else 0
    for p in range(int(chapter.get("start_page", 0)), int(chapter.get("end_page", 0)) + 1):
        fp = os.path.join(d, f"{p - off:03d}.txt")
        if os.path.exists(fp):
            parts.append(open(fp, encoding="utf-8", errors="ignore").read())
    return "\n".join(parts)[:cap]


class State:
    """sqlite project DB: one row per video unit; status pending/done/failed + youtube urls."""

    def __init__(self, path):
        self.db = sqlite3.connect(path)
        self.db.row_factory = sqlite3.Row
        self.db.executescript("""
        CREATE TABLE IF NOT EXISTS units (
          unit_id TEXT PRIMARY KEY, topic_key TEXT, std INT, subject TEXT, lang TEXT, book_id INT, chapter_no INT,
          chapter_title TEXT, block TEXT, title TEXT, n_questions INT, meta_json TEXT,
          status TEXT DEFAULT 'pending', tries INT DEFAULT 0, error TEXT, out_dir TEXT,
          youtube_url TEXT, short_url TEXT, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
        CREATE TABLE IF NOT EXISTS questions (
          id INTEGER PRIMARY KEY AUTOINCREMENT, unit_id TEXT, ord INT, bq_id INT, qtype TEXT, instruction TEXT,
          text TEXT, options_json TEXT, pairs_json TEXT, answer TEXT, page INT);
        CREATE INDEX IF NOT EXISTS ix_q_unit ON questions(unit_id, ord);
        CREATE INDEX IF NOT EXISTS ix_u_topic ON units(topic_key);
        """)

    def upsert_unit(self, u, questions):
        self.db.execute("INSERT OR IGNORE INTO units(unit_id) VALUES (?)", (u["unit_id"],))
        self.db.execute("""UPDATE units SET topic_key=?, std=?, subject=?, lang=?, book_id=?, chapter_no=?, chapter_title=?,
                           block=?, title=?, n_questions=?, meta_json=? WHERE unit_id=?""",
                        (u.get("topic_key"), u["std"], u["subject"], u["lang"], u["book_id"], u["chapter_no"], u["chapter_title"],
                         u.get("block", ""), u["title"], len(questions), json.dumps(u.get("meta", {}), ensure_ascii=False), u["unit_id"]))
        self.db.execute("DELETE FROM questions WHERE unit_id=?", (u["unit_id"],))
        for i, q in enumerate(questions, 1):
            self.db.execute("INSERT INTO questions(unit_id, ord, bq_id, qtype, instruction, text, options_json, pairs_json, answer, page) VALUES (?,?,?,?,?,?,?,?,?,?)",
                            (u["unit_id"], i, q.get("id"), q.get("qtype"), q.get("instruction", ""), q.get("text", ""),
                             json.dumps(q.get("options") or [], ensure_ascii=False), json.dumps(q.get("pairs") or [], ensure_ascii=False),
                             q.get("answer", ""), q.get("page")))

    def pending(self, limit, stds=None, max_tries=2):
        sql = "SELECT * FROM units WHERE status!='done' AND tries<?"
        args = [max_tries]
        if stds:
            sql += f" AND std IN ({','.join('?' * len(stds))})"
            args += list(stds)
        sql += " ORDER BY std, subject, book_id, chapter_no, block LIMIT ?"
        return [dict(r) for r in self.db.execute(sql, args + [limit])]

    def questions_of(self, unit_id):
        out = []
        for r in self.db.execute("SELECT * FROM questions WHERE unit_id=? ORDER BY ord", (unit_id,)):
            out.append({"id": r["bq_id"], "qtype": r["qtype"], "instruction": r["instruction"], "text": r["text"],
                        "options": json.loads(r["options_json"] or "[]"), "pairs": json.loads(r["pairs_json"] or "[]"),
                        "answer": r["answer"], "source": "book", "page": r["page"]})
        return out

    def mark(self, unit_id, status, error=None, meta=None):
        self.db.execute("UPDATE units SET status=?, tries=tries+1, error=?, out_dir=?, youtube_url=?, short_url=?, updated_at=CURRENT_TIMESTAMP WHERE unit_id=?",
                        (status, error, (meta or {}).get("out_dir"), (meta or {}).get("youtube_url"), (meta or {}).get("short_url"), unit_id))
        self.db.commit()

    def commit(self):
        self.db.commit()
