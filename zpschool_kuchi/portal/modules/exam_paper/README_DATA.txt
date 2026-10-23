Exam paper module data (OCR-based bank, std 1-8) — unzip into your exam_paper/ folder (overwrites db/ and data/).
1. Import SQL (MySQL):  mysql -u <user> -p <dbname> < db/full_dump.sql
                        mysql -u <user> -p <dbname> < db/topic_packs.sql
   (or: EP_DB_PASS=<pass> php import_books.php  — rebuilds ep_book_questions / ep_topic_packs from data/*.json)
2. data/book_pages/<book_id>/<page>.jpg and data/book_figures/<book_id>/<page>_<n>.jpg are used by picture questions.
Contents: 14,463 textbook स्वाध्याय items + 67,338 Bloom-tagged MCQs (4,645 picture-based), 1,274 chapters.
Code for the module: https://github.com/hankareamol07-oss/New/pull/7
