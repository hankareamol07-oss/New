# उत्तर-लेखन (answer-lines) paper format — आकारिक / संकलित चाचणी

MiniShala-style test papers: every question is followed by dotted lines / blank
working space sized to the expected answer, with a student header
(नाव, शाळा, केंद्र, हजेरी क्र., दिनांक, वेळ, लेखी/तोंडी marks box).

## Using it

| Where | What |
|---|---|
| Builder (`assessment_paper.php`) | **पेपर स्वरूप**: `उत्तर-लेखन ओळींसह` (default) / `संक्षिप्त`; **तोंडी गुण**; per section: उत्तर-जागा dropdown (auto / जागा नाही / ओळी / मोकळी जागा / चौकट …) and **तोंडी** checkbox |
| Print (`paper_view.php?id=N`) | uses saved format; `&format=lines` / `&format=standard` override; `&key=1` teacher key (compact by default, add `&format=lines` for key on the lined layout) |
| Settings (`settings.php`) | **Default paper format** for papers saved before this feature (`ep_settings.default_paper_format`) |

## How the answer space is chosen (`includes/answer_space.php`)

Resolution order per section:

1. teacher override `answer_space` (e.g. `lines:4`, `blank:30`, `box:70`, `short`, `grid2`, `inline`, `none`)
2. section `qtype` → rule table (`ep_answer_space_rules()`)
3. keyword scan of the instruction (`रिकाम्या जागा`, `जोड्या`, `कारणे लिहा`, `फरक`, `सोडवा`, `आकृती`, `निबंध` … + English)
4. default: one line

Per item: a textbook question whose own `qtype` differs from the section
(fallback fill) keeps its own space; जोड्या लावा items always print as the
अ/ब गट table with no lines.

| qtype | space | std 1–2 |
|---|---|---|
| fill_blank | inline `______` (or a tail line) | – |
| match | none (pair table) | – |
| true_false, mcq | short line at right | – |
| one_word | 2 columns + short line | – |
| one_sentence | 1 line | 2 |
| short_answer | 3 lines | 2 |
| reason / explain / difference | 3 / 4 / 4 lines | – |
| descriptive | 6 lines | 4 |
| solve | 30 mm blank working space | – |
| draw | 45 mm box | – |

Line pitch 9 mm (`--pitch`), 11 mm and 13 pt type for std 1–2
(`.sheet.lines-format.std12`). The rule table is exported to
`data/answer_space_rules.json` (`ep_answer_space_export()`) for other tools.

## Data stored in `ep_papers.paper_json`

```json
{ "format": "lines", "oral_marks": 10,
  "sections": [ { "q_no": 1, "sub": "अ", "qtype": "fill_blank", "answer_space": "", "oral": false,
                  "items": [ { "bq_id": 123, "text": "…", "qtype": "fill_blank", "page_image": "", "show_image": false } ] } ] }
```

`answer_space` must match `^[a-z0-9]+(:\d+)?$`; `format` is `lines|standard`.

## Files

- `includes/answer_space.php` (new), `includes/functions.php` (require + model defaults)
- `paper_view_assessment.php` (renderer), `assets/paper_print.css` (`.ms-*`, `.ans-*` rules)
- `assessment_paper.php`, `assets/assessment_paper.js` (builder controls), `api.php` (save)
- `settings.php`, `db/schema.sql` (`default_paper_format` seed)
- `tools/seed_sample_papers.php` — creates 10 sample papers (std 1–8, Marathi/Hindi/English)

Layout reference: public sample PDFs on minishala.com (third-party teacher
material, not official SCERT). No text or branding from them is used — only
the structure (header fields, marks box, line spacing per question type).
