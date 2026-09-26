# AI सराव प्रश्नसंच — typed question set (second question source)

The assessment-paper builder can draw questions from two banks that share the
`ep_book_questions` table and the same chapter tree:

| `source` | what it is | rows |
|---|---|---|
| `book`  | स्वाध्याय / Exercise questions extracted from the textbook OCR (`data/book_questions.json`) | ~12.8k |
| `typed` | MiniShala-style practice set generated per chapter by an LLM from the chapter OCR text, every question tagged with its `qtype`, with expected answer, marks, options and match pairs (`data/typed_questions.json`) | ~20–45 per chapter |

`typed` rows are **not** textbook text: the model was asked to write new
practice questions in the book's language (Marathi / Hindi / English) covering
the chapter, in the question forms used by the public MiniShala-style
आकारिक/संकलित papers. They are marked `ai_cleaned=1`, `block` =
"सराव प्रश्नसंच (AI)" / "Practice set (AI)", and `model` records the provider.

## Question types (`qtype`)

`fill_blank`, `true_false`, `match`, `mcq`, `odd_one`, `one_word`,
`one_sentence`, `short_answer`, `reason`, `descriptive` (all subjects);
`solve`, `draw`, `define`, `difference`, `explain` (Maths / Science / EVS /
Geography / History / Civics); `vocabulary`, `grammar` (language subjects).
The answer-space resolver (`includes/answer_space.php`) already knows all of
these, so a typed question prints with the right lines / blank / box in the
उत्तर-लेखन format.

Per-question fields: `instruction` (section heading in the book's language),
`text` (fill-blank contains `______`), `options_json` (mcq / odd_one, ≥3),
`pairs_json` (match, ≥3 `[left, right]` pairs), `answer` (expected answer /
labels for `draw` / key for `match`), `marks` (suggested 1–5).

## Schema

`db/schema_books.sql` — `ep_book_questions` gained

```sql
source VARCHAR(10) NOT NULL DEFAULT 'book',   -- 'book' | 'typed'
marks  TINYINT DEFAULT NULL,
model  VARCHAR(80) DEFAULT NULL,
INDEX (chapter_id, source)
```

`import_books.php` adds these columns on an existing install (ALTER … ADD,
ignored when present) and then imports both JSON files; rerunnable. Typed ids
are `1000000 + book_id*10000 + chapter_no*100 + n`, so they never collide with
textbook `bq_id`s and a re-import replaces the same rows.

## Builder / API

* `assessment_paper.php` — **Question source** select (`#qSource`):
  empty = both banks, `book` = स्वाध्याय only, `typed` = AI set only.
  Chapter and subject labels show the count for the chosen source
  (`n` textbook, `nt` typed, from `book_tree`).
* `api.php?action=book_random|book_browse` accept `source`; `book_tree`
  returns `n` and `nt` per chapter / book / subject.
* Items picked from the typed set carry `source`, `answer`, `options`, `pairs`
  into `paper_json`; the paper's `meta.source` remembers the selector.
* `paper_view.php?id=N&key=1` (teacher key) prints **उत्तर :** / **Ans.** under
  every question that has an answer (typed set, or textbook rows whose answer
  was AI-filled) and the (१)–(ब) mapping under match tables. Options of
  mcq / odd_one print as an `(अ) … (ब) …` line when they are not already in the
  question text.

## Regenerating the data

`books/typed_questions.py` (outside the repo; needs `gemini_packs.py` and API
keys in env) writes one JSON per chapter to `books/typed/`; `books/merge_typed.py`
normalises qtypes / instructions / pairs and writes `data/typed_questions.json`.
Chapters whose OCR text was too short or whose output failed validation are
skipped and simply have `nt = 0` in the tree (the textbook bank still works).

Review note: content is machine-generated from OCR text — teachers should
read a typed question before printing it; the builder shows an **AI** badge and
a key icon with the expected answer on every such item.
