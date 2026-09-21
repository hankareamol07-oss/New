-- AI topic packs (short notes + 10-question online quiz per textbook chapter) and cleaned exercise answers.
-- Run after schema_books.sql and schema_homework.sql, then: php import_books.php
SET NAMES utf8mb4;

ALTER TABLE ep_book_questions
  ADD COLUMN IF NOT EXISTS options_json TEXT DEFAULT NULL,          -- ["A","B","C","D"] for mcq/true_false/match items
  ADD COLUMN IF NOT EXISTS answer TEXT DEFAULT NULL,                -- expected / model answer (teacher key)
  ADD COLUMN IF NOT EXISTS ai_cleaned TINYINT(1) NOT NULL DEFAULT 0,-- 1 = text cleaned from OCR noise by AI
  ADD COLUMN IF NOT EXISTS figure_image VARCHAR(80) DEFAULT NULL;   -- cropped figure (data/book_figures/<book>/<file>.jpg)

CREATE TABLE IF NOT EXISTS ep_topic_packs (
  chapter_id INT PRIMARY KEY,                -- ep_book_chapters.chapter_id
  book_id INT NOT NULL,
  standard TINYINT NOT NULL,
  subject VARCHAR(80) NOT NULL,
  lang CHAR(2) NOT NULL,
  title VARCHAR(255) NOT NULL,
  notes_json MEDIUMTEXT NOT NULL,            -- ["bullet", ...]
  quiz_json MEDIUMTEXT NOT NULL,             -- [{q, options[4], answer(0-3), explain}]
  model VARCHAR(60) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (book_id),
  INDEX (standard, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE ep_homework ADD COLUMN IF NOT EXISTS notes_json MEDIUMTEXT DEFAULT NULL;   -- short notes printed on the sheet
