# प्रथम सत्र 2026-27 papers (TechGuruji-branded)

Generates class-wise / subject-wise संकलित मूल्यमापन papers (PDF + DOCX) from the textbook bank
(`ep_books / ep_book_chapters / ep_book_questions`, i.e. `exam_paper/db/full_dump.sql`) in the
शिक्षकमित्र-style layout: boxed header, word-bank fill-blanks, चूक/बरोबर, MCQ, picture questions
(figures from `exam_paper/data/book_figures`), जोड्या लावा, answer lines.

* `gen2.py <std> <subject> <medium> <marks> out.pdf` – one paper (PDF + .docx), first-half chapters = प्रथम सत्र
* `batch2.py` – the full set (std 1, 3, 4, 6, 7) + `papers_2026-27_import.sql`
* Paths at the top of `gen_paper.py` / `gen2.py` (DB = sqlite copy of full_dump, FONT = Mukta, FIG = book_figures) must be adjusted locally.
* Import `exam_paper/db/papers_2026-27_import.sql` into database `school` → 27 papers appear as assessment papers (paper_view_assessment.php).
