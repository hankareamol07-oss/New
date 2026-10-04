"""The agent: a natural-language instruction -> a plan of tool steps (made by the LLM from the tool list below) -> runs the steps
with the user's APIs, resumable, logging progress and cost. The plan is shown before running (GUI/CLI can ask for confirmation)."""
import glob, json, os, re, threading
from . import ocr, chapters, parts, figures, bank, export, notes, office
from .db import Store
from .llm import Client

TOOLS = {
    "add_books": "Register PDF files. args: {files: [paths or glob], std: int, subject: str, lang: mr|hi|en, medium: str}. One book per PDF; std/subject may be guessed from the file name when not given.",
    "ocr": "Transcribe the pages of the given books with Gemini vision (resumable). args: {books: [book_id] or 'all', pages: [first,last] optional}",
    "chapters": "Detect chapters/lessons from the OCR headings (optionally reconciled with a chapter list the user gave). args: {books, toc: [\"1. title\", ...] optional}",
    "parts": "Clean each chapter's text and split it into teaching parts (unit_text). args: {books or units}",
    "figures": "Find and crop pictures/diagrams/maps/tables on the chapter pages and link them to parts. args: {books or units}",
    "bank": "Build the question bank per chapter: textbook स्वाध्याय items + 10-15 Bloom-tagged MCQs per topic (+ picture questions). args: {books or units, mcq_min:int, mcq_max:int, figures: bool}",
    "notes": "Write revision notes (टिपणे) per topic part: summary, key points, definitions, formulas/examples, names & dates, common mistakes. args: {books or units, detail: short|normal|detailed}",
    "export_excel": "Excel quiz workbook per chapter (+ one per book): MCQ sheet with question, options A-D, answer key, solution, Bloom, page, figure; स्वाध्याय sheet with answers; Answer key; Notes. args: {books or units, out_dir: path}",
    "export_notes": "Word (.docx) notes file per chapter from the notes step, with textbook figures inline. args: {books or units, out_dir: path}",
    "export_exam_paper": "Write exam_paper data files (book_questions.json, topic_packs.json, figure crops). args: {out_dir: path}",
    "dump": "Dump all tables to JSON/CSV. args: {out_dir: path}",
    "stats": "Print counts of books/pages/chapters/parts/figures/questions. args: {}",
}
PLAN_SYS = "You convert a teacher's instruction into an ordered plan of tool calls. Output only JSON."
PLAN = """Tools (name: purpose + args):
{tools}

Current database: {stats}
PDF files available in the input folder: {files}

Instruction from the user:
\"\"\"{instr}\"\"\"

Return JSON {{"plan": [{{"tool": "...", "args": {{...}}, "why": "one line"}}], "notes": "anything unclear or assumptions"}}.
Rules: steps must be in dependency order (add_books -> ocr -> chapters -> parts -> figures -> bank/notes -> export_excel/export_notes/export_exam_paper); include only what the
instruction asks for, but add prerequisite steps the database still lacks; use book_ids from the database when the user refers to books
already there; if the user gives a chapter list, pass it as toc; if the user mentions a standard/subject/language for new PDFs, pass them."""


class Agent:
    def __init__(self, workdir, log=print):
        self.workdir = os.path.abspath(workdir)
        os.makedirs(os.path.join(self.workdir, "input"), exist_ok=True)
        self.cfg = json.load(open(os.path.join(self.workdir, "config.json"), encoding="utf-8"))
        self.log = log
        self.client = Client(self.cfg, self.workdir, log)
        self.store = Store(os.path.join(self.workdir, self.cfg.get("db", "explain.db")))
        self.stop = threading.Event()

    # ---------- planning ----------
    def stats(self):
        s = self.store
        return {t: s.count(t) for t in ("books", "pages", "units", "unit_text", "unit_figures", "unit_notes", "questions")} | {
            "books_list": [dict(book_id=b["book_id"], std=b["std"], subject=b["subject"], lang=b["lang"], pages=b["n_pages"], title=b["title"]) for b in s.books()]}

    def input_files(self):
        return sorted(os.path.basename(f) for f in glob.glob(os.path.join(self.workdir, "input", "*.pdf")))

    def plan(self, instruction):
        tools = "\n".join(f"- {k}: {v}" for k, v in TOOLS.items())
        js, _ = self.client.chat_json(PLAN_SYS, PLAN.format(tools=tools, stats=json.dumps(self.stats(), ensure_ascii=False), files=self.input_files(), instr=instruction))
        steps = [s for s in js.get("plan", []) if s.get("tool") in TOOLS]
        return steps, js.get("notes", "")

    # ---------- running ----------
    def run(self, steps):
        for i, s in enumerate(steps, 1):
            if self.stop.is_set():
                self.log("stopped by user")
                return
            self.log(f"\n== step {i}/{len(steps)}: {s['tool']} {json.dumps(s.get('args', {}), ensure_ascii=False)[:200]}")
            getattr(self, "t_" + s["tool"])(**(s.get("args") or {}))
            self.log(f"   cost so far: ${self.client.cost.usd:.2f} (cap ${self.client.cost.cap})")
        self.log("\nDONE " + json.dumps({k: v for k, v in self.stats().items() if k != "books_list"}))

    def _books(self, books="all", units=None):
        if units:
            return [u for u in self.store.units() if u["unit_id"] in set(units)]
        bl = self.store.books() if books in (None, "all", []) else [self.store.book(int(b)) for b in books]
        return [b for b in bl if b]

    def _units(self, books="all", units=None):
        if units:
            return self._books(units=units)
        out = []
        for b in self._books(books):
            out += self.store.units(book_id=b["book_id"])
        return out

    def t_add_books(self, files, std=None, subject=None, lang=None, medium=None):
        if isinstance(files, str):
            files = [files]
        paths = []
        for f in files:
            cands = [f] if os.path.isabs(f) else [os.path.join(self.workdir, f), os.path.join(self.workdir, "input", f), os.path.join(self.workdir, "input", os.path.basename(f))]
            found = next((sorted(glob.glob(c)) for c in cands if glob.glob(c)), [])
            if not found:
                self.log(f"  [books] not found: {f}")
            paths += found
        for p in paths:
            name = os.path.basename(p)
            m = re.search(r"(?:std|class|इयत्ता)?[\s_-]*([1-8])", name, re.I)
            s = std or (int(m.group(1)) if m else None)
            lg = lang or ("en" if re.search(r"english|maths|science|semi", name, re.I) and not re.search(r"marathi|मराठी", name, re.I) else "hi" if re.search(r"hindi|हिंदी", name, re.I) else "mr")
            bid = self.store.add_book(s, subject or re.sub(r"[_\-\d.]+", " ", os.path.splitext(name)[0]).strip(), lg, medium or "", p, ocr.n_pages(p))
            self.log(f"  [books] #{bid} std {s} {subject or name} ({lg}, {ocr.n_pages(p)} pages)")

    def t_ocr(self, books="all", pages=None):
        for b in self._books(books):
            rng = range(int(pages[0]), int(pages[1]) + 1) if pages else None
            n = ocr.ocr_book(self.client, self.store, b, rng, batch=int(self.cfg.get("ocr_batch", 3)), workers=int(self.cfg.get("workers", 4)), log=self.log)
            self.log(f"  [ocr] {b['title']}: {n} new pages transcribed")

    def t_chapters(self, books="all", toc=None):
        for b in self._books(books):
            chapters.build_units(self.client, self.store, b, toc, self.log)

    def t_parts(self, books="all", units=None):
        from concurrent.futures import ThreadPoolExecutor
        us = [u for u in self._units(books, units) if not self.store.parts(u["unit_id"])]

        def one(u):
            try:
                p = parts.build_parts(self.client, self.store, u, self.log)
                self.log(f"  [parts] {u['unit_id']}: {len(p)} parts")
            except Exception as e:  # noqa: BLE001
                self.log(f"  [parts] ERR {u['unit_id']}: {str(e)[:120]}")
                if "cost cap" in str(e):
                    raise
        with ThreadPoolExecutor(int(self.cfg.get("workers", 4))) as ex:
            list(ex.map(one, us))

    def t_figures(self, books="all", units=None):
        for u in self._units(books, units):
            if self.stop.is_set():
                return
            f = figures.find_figures(self.client, self.store, u, self.workdir, self.log)
            self.log(f"  [figures] {u['unit_id']}: {len(f)} figures")

    def t_bank(self, books="all", units=None, mcq_min=10, mcq_max=15, figures=True):
        for u in self._units(books, units):
            if self.stop.is_set():
                return
            ne, nm = bank.build_bank(self.client, self.store, u, self.workdir, (int(mcq_min), int(mcq_max)), bool(figures), int(self.cfg.get("workers", 4)), self.log)
            self.log(f"  [bank] {u['unit_id']}: {ne} स्वाध्याय items, {nm} MCQs")

    def t_notes(self, books="all", units=None, detail="normal"):
        for u in self._units(books, units):
            if self.stop.is_set():
                return
            n, tot = notes.build_notes(self.client, self.store, u, int(self.cfg.get("workers", 4)), detail, self.log)
            self.log(f"  [notes] {u['unit_id']}: {n} new / {tot} parts")

    def t_export_excel(self, books="all", units=None, out_dir=None):
        office.export_excel(self.store, self.workdir, out_dir or os.path.join(self.workdir, "export", "excel"), self._units(books, units), True, self.log)

    def t_export_notes(self, books="all", units=None, out_dir=None):
        office.export_docx(self.store, self.workdir, out_dir or os.path.join(self.workdir, "export", "notes"), self._units(books, units), self.log)

    def t_export_exam_paper(self, out_dir=None, books=None):
        export.export_exam_paper(self.store, self.workdir, out_dir or os.path.join(self.workdir, "export", "exam_paper"), books, self.log)

    def t_dump(self, out_dir=None):
        export.dump_tables(self.store, out_dir or os.path.join(self.workdir, "export", "tables"), self.log)

    def t_stats(self):
        self.log(json.dumps(self.stats(), ensure_ascii=False, indent=1))
