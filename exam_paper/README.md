# Exam Paper Generator module (core PHP + Bootstrap 5)

Drop-in module for a core-PHP school management system. It ships a question bank
for **Std 1–8** (regular, scholarship, Olympiad, NMMS and foundation variants,
English / Semi-English / Marathi medium) scraped from downloadpapers.com and lets a
school build, save and print branded question papers with its own **name, address
and logo**.

## Contents

| Path | Purpose |
|---|---|
| `db/schema.sql` | MySQL/MariaDB tables (`ep_*` prefix, utf8mb4) |
| `data/hierarchy.json` | classes → subjects → chapters → paper templates |
| `data/questions/*.json` | ~34,000 questions (MCQ / fill-in-blanks / descriptive, with answers) |
| `data/images_index.json`, `data/images/*.png` | question / answer images (served via `image.php`) |
| `import_data.php` | CLI importer for the JSON data |
| `index.php` | list of saved papers (print, answer key, edit, delete) |
| `create_paper.php` + `assets/create_paper.js` | paper builder |
| `competitive_paper.php` + `assets/competitive_paper.js` | Scholarship / Navodaya / NMMS / topic-wise paper builder |
| `includes/patterns.php` | exam patterns (sections, question counts, marks per question, time) |
| `paper_view.php` | printable A4 paper / answer key with school branding |
| `question_bank.php` | browse & search the question bank |
| `settings.php` | school name, address, logo, footer, watermark |
| `api.php` | JSON endpoints used by the builder |
| `scraper/` | Python scripts used to build the dataset (optional, for refreshing data) |

## Install

1. Copy the `exam_paper/` folder into your application's web root
   (e.g. `/var/www/school/exam_paper`).
2. Create the tables:
   ```bash
   mysql -u root -p your_db < exam_paper/db/schema.sql
   ```
3. Point the module at your database – either edit `config.php` or set env vars
   `EP_DB_HOST`, `EP_DB_NAME`, `EP_DB_USER`, `EP_DB_PASS`. To reuse the host
   application's connection, replace the body of `ep_db()` in `config.php` with
   `return $yourExistingPdo;`.
4. Import the question bank (one time, ~1 minute):
   ```bash
   cd exam_paper && php import_data.php
   ```
5. Make `uploads/` writable by the web server (school logo is stored there).
6. Open `exam_paper/settings.php`, enter the school name / address and upload the logo.

## Integrating with the host application

* **Authentication** – every page `require_once`s `includes/functions.php`, which
  loads `config.php`. Add your session/login check at the top of `config.php`
  (e.g. `require_once __DIR__ . '/../includes/auth.php'; require_login();`) and
  it applies to all module pages and `api.php`.
* **Menu** – link to `exam_paper/index.php` from your admin sidebar.
* **Layout** – `includes/header.php` / `footer.php` are self-contained Bootstrap
  5 pages; swap them for your own layout partials if you want the module inside
  your existing shell.
* **Multi-school** – `ep_settings` holds one school. For a multi-tenant system add
  a `school_id` column to `ep_settings` and `ep_papers` and filter by the
  logged-in school in `ep_settings()` / `ep_paper()`.

## Using the module

1. **Create Paper** → choose class, subject and chapters.
2. Pick a **ready template** (the official paper patterns per subject, e.g.
   "8th Science 40 Marks") or add custom sections (type, question-number pattern,
   marks, count).
3. **Auto-fill** picks random questions from the selected chapters; swap, browse
   or remove individual questions.
4. **Save & Preview** opens the A4 paper with the school header/logo; use the
   browser's *Print → Save as PDF*. Toggle **Answer key** for the solutions.

## Competitive exams (Scholarship / Navodaya / NMMS / topic-wise)

**Scholarship / Navodaya** builds MCQ-only papers in the competitive-exam layout
(sections with continuous question numbers, equal marks per question such as 1.25,
per-question marks in the margin, optional logo watermark, and a compact answer
grid on the answer key). Patterns shipped in `includes/patterns.php`:

| Pattern | Sections | Marks |
|---|---|---|
| 5th Navodaya (JNVST) – chapterwise practice | मानसिक क्षमता 20 + अंकगणित 10 + भाषा 10 | 40 Q × 1.25 = 50, 1:30 Hrs |
| 5th Navodaya (JNVST) – full paper | 40 + 20 + 20 | 80 Q × 1.25 = 100, 2:00 Hrs |
| 4th / 7th Scholarship – Paper 1 & 2 | प्रथम भाषा + गणित / तृतीय भाषा + बुद्धिमत्ता | 75 Q × 2 = 150 |
| 8th NMMS – MAT / SAT | 90 Q | 90 |
| Topic-wise practice | one section, any topic | configurable |

For every section you choose the **source class, subject and topics/chapters**
from the question bank (each section can use a different class/subject), set the
question count and marks per question, then *Auto-fill*. Questions can be
swapped, browsed or removed individually, exactly like the regular builder.
Add more patterns by appending to `ep_exam_patterns()`.

> **Data note:** downloadpapers.com lists Scholarship / Navodaya / NMMS classes
> with their chapters and topics, but its question endpoints returned **no
> questions** for those classes for the accounts we had. The competitive builder
> therefore draws MCQs from the populated class 1–8 subjects (e.g. 5th गणित for
> JNVST mental ability / arithmetic, 5th मराठी बालभारती for भाषा). If the source
> later adds competitive questions, re-run the scraper + importer and they will
> appear as selectable sources automatically.

### Upgrading an existing install

If `ep_papers` was created from an older `schema.sql`, run:

```sql
ALTER TABLE ep_papers
  MODIFY standard_id INT NULL, MODIFY subject_id INT NULL,
  MODIFY total_marks DECIMAL(7,2) DEFAULT 0,
  ADD paper_type VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER title,
  ADD exam_name VARCHAR(150) NULL AFTER paper_type,
  ADD std_label VARCHAR(50) NULL AFTER exam_name;
INSERT IGNORE INTO ep_settings VALUES ('watermark_logo', '0');
```

## संकलित / आकारिक मूल्यमापन चाचणी (textbook exercise papers)

`assessment_paper.php` builds Summative (संकलित) / Formative (आकारिक) tests from
the **स्वाध्याय / Exercise questions of the Balbharati textbooks** (new syllabus,
std 1–8). The textbook PDFs are scanned, so every page was OCR'd
(Tesseract, Marathi + Hindi + English) and the exercise blocks below each
chapter were parsed into `data/book_questions.json`
(`books/extract_questions.py` in the working folder produced it). Each question
carries class, subject, medium, chapter, textbook page, question type
(`fill_blank`, `true_false`, `match`, `one_word`, `one_sentence`,
`short_answer`, `reason`, `difference`, `solve`, `draw`, `grammar`, …) and — when
the exercise refers to a picture/figure — a rendered page image
(`data/book_pages/<book_id>/<page>.jpg`, served via `book_page.php?f=…`).

Install / refresh:

```bash
mysql -u root -p school < db/schema_books.sql
php import_books.php          # ep_books, ep_book_chapters, ep_book_questions, ep_paper_models
```

Workflow: pick test type & number (संकलित १ defaults to the first half of the
chapters, संकलित २ to the second half), class, textbook subject and chapters →
**Load layout** (standard layout per subject, or the section layout of a real
paper parsed from the minishala.com sample papers, stored in `ep_paper_models`)
→ **Auto-fill** every section with matching exercise questions → edit / swap /
browse (🔀, Browse, Own question) → **Save & Preview** → print / PDF with the
school header. `book_bank.php` (Textbook Bank) lets you browse and search all
extracted exercise questions.

> OCR caveat: question text is OCR output from scanned books, so a few
> characters/words may be wrong. Every question is editable in the builder
> before saving. Chapter titles come from the book's अनुक्रमणिका page.

## Daily homework + topic quiz (दैनिक गृहपाठ)

```bash
mysql -u root -p school < db/schema_homework.sql   # ep_homework, ep_quizzes, ep_quiz_attempts
```

Then in **School Settings** fill *Public URL of this module* (e.g.
`https://www.yourschool.in/exam_paper`) — it is the address printed in the quiz
link / QR code, so it must be reachable from students' phones.

`homework.php` (Daily Homework) → **New homework**: pick date, class, division,
textbook subject and today's chapter (आजचा टाचण विषय); add homework items by
typing, **5 from textbook स्वाध्याय** (random exercise questions of that chapter)
or **Browse textbook**; add a note for parents. Section 4 builds the **topic
quiz (10–15 questions)**: **Auto-fill MCQs** pulls auto-scorable MCQs from the
downloadpapers question bank — the bank chapter best matching the textbook
chapter title is pre-selected (★), change the source if the match is wrong.
Questions can also be added by hand (MCQ or typed answer) or taken from the
textbook exercise (you type the expected answer). **Save & open PDF** opens the
A4 sheet (`homework_view.php`): school header, items (optionally with the
textbook figure page), note, and at the bottom a boxed **quiz link + QR code**
(`quiz.php?c=CODE`). Print / Save as PDF and share it on WhatsApp etc.; the
toolbar also has *Copy quiz link*.

Students open the link on a phone, enter name / roll / division, answer within
the optional time limit and immediately see their score (and correct answers,
if enabled). Teachers see attempts, average and question-wise correct % in
`quiz_results.php` (CSV export), linked from the homework list.

**Answer key:** each homework item has an optional *Answer / उत्तर* field (not
printed on the student sheet). `homework_key.php?id=…` (**Key** button in the
list, *Answer key* link on the sheet toolbar) prints the teacher copy with the
homework answers, the correct option of every quiz MCQ and the expected typed
answers.

**Lesson names:** `book_chapters.php?book_id=…` (*edit lesson names* beside the
chapter drop-down, or from the Textbook Bank) lets you rename lessons / fix
page ranges of any textbook; textbook questions are re-linked to lessons by
page. `data/book_questions.json` ships with the official contents lists of the
Balbharati books (std 1–8 Maths, Science, EVS, History-Civics, Geography,
English) applied over the OCR result.

> Classes 1–4 have no MCQ bank on downloadpapers, so for them build the quiz
> from textbook questions / manual MCQs. Tachan integration: the chapter
> drop-down is the same textbook chapter list the tachan DB was built from; to
> pre-select today's tachan chapter automatically, import the tachan data and
> link it on `chapter_id`.

## Question images

Questions whose source had an image are flagged (`has_image`, `has_image2`,
`has_answer_image`) and rendered through `image.php?id=<question_id>[&n=2|a]`
in the builder, the printable paper and the question bank. Images are stored as
`data/images/<question_id>.png`, `<question_id>_2.png` and `<question_id>_a.png`.

## Refreshing the dataset

```bash
pip install requests openpyxl
cd exam_paper/scraper
export DP_USER=<institute mobile no> DP_PASS=<password>
export DP_INSTITUTES_XLSX="path/to/institutes*.xlsx"   # optional: extra accounts to unlock more subjects
python3 hierarchy.py     # -> hierarchy.json
python3 questions.py     # -> questions/*.json
python3 images.py        # -> images/ and images_index.json
```
Copy the outputs into `exam_paper/data/` and re-run `php import_data.php`
(it upserts, so re-running is safe).
