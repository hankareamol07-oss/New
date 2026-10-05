# pdf_agent — PDF → textbook database agent (local, your own APIs)

Drop textbook PDFs in `input\`, type an instruction (Marathi/English), click **Plan** → **Run**.
The agent plans the steps with the LLM and runs them with *your* APIs (GCP Vertex Gemini first, Gemini keys /
NVIDIA / Groq as fallbacks), resumable, with a running cost estimate and a hard cost cap.

Steps (tools) it knows — the same recipes used to build the std 1-8 database:

| tool | what it does | output |
|---|---|---|
| add_books | register PDFs (std / subject / language) | `books` |
| ocr | Gemini-vision transcription, 3 pages per request, `## ` on chapter-start pages, `[चित्र: ...]` figure markers | `pages` |
| chapters | chapter/lesson detection from headings (+ your chapter list if given) | `units` |
| parts | clean chapter text split into 4-12 teaching parts, exercise part last | `unit_text` |
| figures | pictures/diagrams/maps/tables located by Gemini, cropped, linked to parts | `unit_figures`, `figures\<book>\` |
| bank | textbook स्वाध्याय items (or generated set when the book has none) + 10-15 Bloom-tagged MCQs per topic, picture questions | `questions` |
| notes | revision notes per topic part: summary, key points, definitions, formulas/examples, names & dates, common mistakes | `unit_notes` |
| export_excel | Excel per chapter + per book: `MCQ` (question, A-D, answer key, solution, Bloom, page, figure), `स्वाध्याय` (with answers), `Answer key`, `Notes` | `export\excel\` |
| export_notes | Word (.docx) notes per chapter with textbook figures inline | `export\notes\` |
| export_exam_paper | `book_questions.json`, `topic_packs.json`, `book_figures\` for the school-system exam-paper module | `export\exam_paper\` |
| dump | every table as JSON + CSV | `export\tables\` |

The SQLite file (`explain.db`) uses the same `units / unit_text / unit_figures` tables as the yt_explain video tool.

## Install (Windows)
1. Python 3.11+ → `pip install -r requirements.txt`
2. Copy `config.example.json` → `config.json`; set `vertex.project` and put your service-account key as `gcp_key.json`
   (or `"api_key"`), optional NVIDIA/Groq keys, `cost_cap_usd`.
3. `START_GUI.bat` (GUI) — or CLI: `python main.py "OCR all PDFs in input, detect chapters, build parts and bank" --yes`
4. `BUILD_EXE.bat` makes `dist\pdf_agent\pdf_agent.exe` (copy config.json, gcp_key.json, input\ next to it).

## Instruction examples
* `input फोल्डरमधील सर्व PDF जोडा, OCR करा, धडे शोधा, भाग व चित्रे काढा, प्रत्येक धड्याचा स्वाध्याय + 12-15 MCQ (Bloom) बनवा, exam_paper export करा`
* `Add 7_science.pdf as std 7 Science English medium; chapters: 1. The Living World 2. Plants ...; OCR pages 1-60; parts only`
* `For book 2 rebuild the bank with 10 MCQs per topic, no picture questions`
* `प्रत्येक धड्याची टिपणे बनवा आणि Word फाईल द्या; MCQ चा Excel (प्रश्न, 4 पर्याय, उत्तर, स्पष्टीकरण) बनवा`
* `stats`

Everything is resumable: re-running the same instruction skips pages/chapters/jobs already done. Costs: ~$0.003/page OCR,
~$0.01 per topic MCQ set with gemini-2.5-flash (shown live; the run stops at `cost_cap_usd`).
Never share `config.json` / `gcp_key.json`.


## Competitive / scholarship question papers (`paper` tool)
Instruction example: "Read nmms_2024.pdf as a competitive question paper (std 8): passages, questions, image options, answer key; export Excel"
(or in Marathi: "input मधील प्रश्नपत्रिका PDF paper म्हणून वाचा आणि Excel द्या").
Works on scanned PDFs too (no text layer needed – every page image goes to the vision model), any size: pages are processed and
saved one by one, so a stopped run continues where it left off. Per question the agent records the **section** (English /
बुद्धिमत्ता / मराठी / गणित … – carried over to following pages when the heading is printed only once) and a **chapter / topic**
name (e.g. Punctuation, अपूर्णांक, अक्षरमालिका), the passage / instruction block it belongs to, the 4 options (printed 1)–4) or
a)–d) are mapped to A–D), and the answer – from a printed answer table or a handwritten answer sheet at the end of the PDF.
Question figures and picture options are cropped from the page at their printed position and saved as
`figures/<book>/paper/<page>_q<no>_q.png` (question figure) and `<page>_q<no>_A.png … _D.png` (option pictures).
Excel: `export/excel/paper_<std>_<file>.xlsx` – sheets Questions (Page, Q.No, Section, Chapter / Topic, Passage, Question, A–D text
+ image file names, Answer, Solution), Answer key, Passages, Chapters (question count per section/topic); images in the folder next to it.

## Settings tab – any API
GUI → "⚙ Settings / API keys": Vertex AI (project + key file or API key), Gemini API keys, Anthropic Claude, OpenAI, OpenRouter (Kimi,
Qwen, DeepSeek, …), Mistral, Together, Groq, NVIDIA NIM, Sarvam, and a custom OpenAI-compatible URL (Ollama / LM Studio / any server).
Set key + model per provider, the provider order, cost cap, workers. "Test providers" sends one tiny request per configured provider.
Saved to `config.json` on your PC only – never put it in the zip/exe. Image steps (OCR, figures, paper) go to vision-capable
providers (Gemini, Claude, OpenAI, OpenRouter, Mistral; or custom with `custom_vision: true`); the first configured one in the order is used.
