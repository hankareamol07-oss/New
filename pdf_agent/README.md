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
* `stats`

Everything is resumable: re-running the same instruction skips pages/chapters/jobs already done. Costs: ~$0.003/page OCR,
~$0.01 per topic MCQ set with gemini-2.5-flash (shown live; the run stops at `cost_cap_usd`).
Never share `config.json` / `gcp_key.json`.
