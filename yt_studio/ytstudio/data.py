"""Chapter/question sources: the module's JSON banks (book + typed), or a PDF/text file for a new-syllabus chapter."""
import json
import os
import re

SUBJECT_MR = {
    "Marathi": "मराठी", "Hindi": "हिंदी", "English": "इंग्रजी", "Maths": "गणित",
    "Science": "विज्ञान", "EVS": "परिसर अभ्यास", "Geography": "भूगोल",
    "History": "इतिहास", "Civics": "नागरिकशास्त्र", "Social_Science": "सामाजिक शास्त्रे",
}


class Bank:
    def __init__(self, data_dir):
        self.data_dir = data_dir
        self.books = {}
        self.questions = []
        for fn, source in (("book_questions.json", "book"), ("typed_questions.json", "typed")):
            p = os.path.join(data_dir, fn)
            if not os.path.exists(p):
                continue
            with open(p, encoding="utf-8") as f:
                d = json.load(f)
            books = d.get("books")
            for b in (books if isinstance(books, list) else []):
                bk = self.books.setdefault(b["book_id"], dict(b))
                if "chapters" in b and b["chapters"] and isinstance(b["chapters"][0], dict):
                    bk["chapters"] = b["chapters"]
            for q in d.get("questions", []):
                q = dict(q)
                q.setdefault("source", source)
                self.questions.append(q)
        self.books = {k: v for k, v in self.books.items() if v.get("chapters")}

    def standards(self):
        return sorted({b["std"] for b in self.books.values()})

    def subjects(self, std):
        return sorted({(b["subject"], b["lang"]) for b in self.books.values() if b["std"] == std})

    def book_list(self, std=None, subject=None, lang=None):
        out = []
        for b in sorted(self.books.values(), key=lambda x: (x["std"], x["subject"], x["lang"])):
            if std and b["std"] != std:
                continue
            if subject and b["subject"] != subject:
                continue
            if lang and b["lang"] != lang:
                continue
            out.append(b)
        return out

    def chapters(self, book_id):
        return self.books[book_id].get("chapters", [])

    def chapter_questions(self, book_id, chapter_no, source="both"):
        qs = [q for q in self.questions if q["book_id"] == book_id and q.get("chapter_no") == chapter_no]
        if source != "both":
            qs = [q for q in qs if q.get("source") == source]
        return qs

    def ocr_text(self, book, chapter, cap=20000):
        """Chapter text from the OCR folder if it is available next to the data (books/ocr/<file>/NNN.txt)."""
        base = os.path.splitext(book.get("file", ""))[0]
        for root in (os.path.join(self.data_dir, "ocr"), os.path.expanduser("~/books/ocr")):
            d = os.path.join(root, base)
            if not os.path.isdir(d):
                continue
            parts = []
            for p in range(int(chapter.get("start_page", 0)), int(chapter.get("end_page", 0)) + 1):
                fp = os.path.join(d, f"{p:03d}.txt")
                if os.path.exists(fp):
                    with open(fp, encoding="utf-8", errors="ignore") as f:
                        parts.append(f.read())
            txt = "\n".join(parts)
            return txt[:cap]
        return ""


def chapter_from_file(path, std, subject, lang, title=None, cap=40000, no=0):
    """Build a chapter dict from a PDF or text file (new-syllabus chapters not yet in the bank)."""
    ext = os.path.splitext(path)[1].lower()
    if ext == ".pdf":
        import fitz  # PyMuPDF
        doc = fitz.open(path)
        text = "\n".join(page.get_text() for page in doc)
    else:
        with open(path, encoding="utf-8", errors="ignore") as f:
            text = f.read()
    text = re.sub(r"\n{3,}", "\n\n", text)
    return {
        "book": {"book_id": 0, "std": std, "subject": subject, "lang": lang, "title": os.path.basename(path)},
        "chapter": {"no": no, "title": title or os.path.splitext(os.path.basename(path))[0]},
        "questions": [],
        "text": text[:cap],
    }
