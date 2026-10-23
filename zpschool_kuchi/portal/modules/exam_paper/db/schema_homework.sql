-- Daily homework + topic quiz tables (run after schema.sql and schema_books.sql)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ep_quizzes (
  quiz_id INT AUTO_INCREMENT PRIMARY KEY,
  code CHAR(8) NOT NULL UNIQUE,              -- short public code used in the quiz link / QR
  title VARCHAR(255) NOT NULL,
  standard TINYINT NOT NULL,
  subject VARCHAR(80) NOT NULL,
  medium VARCHAR(20) DEFAULT '',
  topic VARCHAR(255) DEFAULT '',
  time_limit INT DEFAULT 0,                  -- minutes, 0 = none
  show_answers TINYINT(1) DEFAULT 1,         -- show correct answers to student after submit
  questions_json LONGTEXT NOT NULL,          -- [{kind:mcq|text, text, options[], answer, image_url}]
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (standard, subject)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_quiz_attempts (
  attempt_id INT AUTO_INCREMENT PRIMARY KEY,
  quiz_id INT NOT NULL,
  student_name VARCHAR(120) NOT NULL,
  roll_no VARCHAR(20) DEFAULT '',
  division VARCHAR(20) DEFAULT '',
  answers_json TEXT NOT NULL,
  score DECIMAL(6,2) NOT NULL DEFAULT 0,
  total INT NOT NULL DEFAULT 0,
  submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (quiz_id, submitted_at),
  CONSTRAINT fk_ep_attempt_quiz FOREIGN KEY (quiz_id) REFERENCES ep_quizzes(quiz_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_homework (
  hw_id INT AUTO_INCREMENT PRIMARY KEY,
  hw_date DATE NOT NULL,
  standard TINYINT NOT NULL,
  division VARCHAR(20) DEFAULT '',
  std_label VARCHAR(50) DEFAULT '',
  subject VARCHAR(80) NOT NULL,
  medium VARCHAR(20) DEFAULT '',
  chapter_id INT DEFAULT NULL,               -- ep_book_chapters.chapter_id (textbook chapter)
  topic VARCHAR(255) DEFAULT '',             -- chapter / topic printed on the sheet
  teacher VARCHAR(120) DEFAULT '',
  title VARCHAR(255) NOT NULL,
  note TEXT,                                 -- message to parents / students
  items_json LONGTEXT NOT NULL,              -- [{text, bq_id, page_image, show_image}]
  quiz_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (hw_date, standard),
  CONSTRAINT fk_ep_hw_quiz FOREIGN KEY (quiz_id) REFERENCES ep_quizzes(quiz_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Absolute URL of this module as reachable by students' phones (used for the quiz link + QR), e.g. https://school.example.com/exam_paper
INSERT IGNORE INTO ep_settings (setting_key, setting_value) VALUES ('public_base_url', '');
