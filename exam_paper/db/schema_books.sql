-- Textbook (Balbharati) exercise-question bank + संकलित / आकारिक मूल्यमापन reference paper models.
-- Run after schema.sql, then: php import_books.php

CREATE TABLE IF NOT EXISTS ep_books (
  book_id INT PRIMARY KEY,
  standard TINYINT NOT NULL,                -- 1..8
  subject VARCHAR(80) NOT NULL,             -- Marathi / Maths / English / EVS / Science / Social Science / Hindi ...
  medium VARCHAR(20) NOT NULL,              -- Marathi | English | Hindi (language of the book)
  title VARCHAR(255) NOT NULL,
  file VARCHAR(255) NOT NULL,               -- source PDF name
  pages INT DEFAULT 0,
  INDEX (standard, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_book_chapters (
  chapter_id INT AUTO_INCREMENT PRIMARY KEY,
  book_id INT NOT NULL,
  chapter_no INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  start_page INT DEFAULT NULL,              -- PDF page numbers (1-based)
  end_page INT DEFAULT NULL,
  INDEX (book_id, chapter_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_book_questions (
  bq_id INT PRIMARY KEY,
  book_id INT NOT NULL,
  chapter_id INT DEFAULT NULL,
  standard TINYINT NOT NULL,
  subject VARCHAR(80) NOT NULL,
  lang CHAR(2) NOT NULL,                    -- mr | hi | en
  page INT NOT NULL,                        -- PDF page (1-based) the question was read from
  block VARCHAR(100) DEFAULT '',            -- exercise heading (स्वाध्याय / सराव संच ३ / Exercise ...)
  instruction VARCHAR(400) DEFAULT '',      -- e.g. "खालील प्रश्नांची एका वाक्यात उत्तरे लिहा."
  qtype VARCHAR(20) NOT NULL,               -- fill_blank | true_false | match | mcq | one_word | one_sentence | short_answer | reason | solve | ... | descriptive
  text TEXT NOT NULL,
  item_no INT DEFAULT NULL,
  needs_figure TINYINT(1) DEFAULT 0,        -- question refers to a picture/figure/table on the page
  page_image VARCHAR(80) DEFAULT NULL,      -- data/book_pages/<book_id>/<page>.jpg (only when needs_figure)
  options_json TEXT DEFAULT NULL,           -- ["A","B","C","D"] for mcq/true_false items ("left | right" rows for match)
  pairs_json TEXT DEFAULT NULL,             -- match (जोड्या लावा): [["left","correct right"], ...] in textbook order
  answer TEXT DEFAULT NULL,                 -- expected / model answer (teacher key)
  ai_cleaned TINYINT(1) NOT NULL DEFAULT 0, -- text cleaned from OCR noise by AI
  figure_image VARCHAR(80) DEFAULT NULL,    -- cropped figure data/book_figures/<book_id>/<file>.jpg
  source VARCHAR(10) NOT NULL DEFAULT 'book', -- book = textbook स्वाध्याय exercise | typed = AI-generated MiniShala-style typed set (data/typed_questions.json)
  marks TINYINT DEFAULT NULL,               -- suggested marks (typed set)
  model VARCHAR(80) DEFAULT NULL,           -- generating model for AI rows
  INDEX (standard, subject),
  INDEX (chapter_id, qtype),
  INDEX (chapter_id, source),
  FULLTEXT (text)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parsed structure of real संकलित / आकारिक चाचणी papers (used as ready formats in the assessment paper builder).
CREATE TABLE IF NOT EXISTS ep_paper_models (
  model_id INT AUTO_INCREMENT PRIMARY KEY,
  exam_type VARCHAR(10) NOT NULL,           -- sankalit | aakarik
  test_no TINYINT NOT NULL,                 -- 1 | 2
  standard TINYINT DEFAULT NULL,
  subject VARCHAR(80) NOT NULL,
  total_marks INT DEFAULT NULL,
  pages INT DEFAULT 0,
  source_file VARCHAR(255) DEFAULT NULL,
  sections_json LONGTEXT NOT NULL,          -- [{q_no, sub, instruction, marks, items:[text]}]
  INDEX (exam_type, standard, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
