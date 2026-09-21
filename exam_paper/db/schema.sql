-- Exam Paper Generator module schema (MySQL / MariaDB, utf8mb4)
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS ep_standards (
  standard_id INT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  medium VARCHAR(100) DEFAULT NULL,
  board VARCHAR(50) DEFAULT NULL,
  sort_order INT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_subjects (
  subject_id INT PRIMARY KEY,
  standard_id INT NOT NULL,
  name VARCHAR(150) NOT NULL,
  INDEX (standard_id),
  CONSTRAINT fk_ep_subject_standard FOREIGN KEY (standard_id) REFERENCES ep_standards(standard_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_chapters (
  chapter_id INT PRIMARY KEY,
  subject_id INT NOT NULL,
  name VARCHAR(255) NOT NULL,
  sort_order INT DEFAULT 0,
  INDEX (subject_id),
  CONSTRAINT fk_ep_chapter_subject FOREIGN KEY (subject_id) REFERENCES ep_subjects(subject_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_templates (
  template_id INT PRIMARY KEY,
  subject_id INT NOT NULL,
  title VARCHAR(255) NOT NULL,
  total_marks INT DEFAULT 0,
  description VARCHAR(255) DEFAULT NULL,
  INDEX (subject_id),
  CONSTRAINT fk_ep_template_subject FOREIGN KEY (subject_id) REFERENCES ep_subjects(subject_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_template_questions (
  tq_id INT PRIMARY KEY,
  template_id INT NOT NULL,
  question_title VARCHAR(255) NOT NULL,
  marks INT DEFAULT 0,
  question_type VARCHAR(30) NOT NULL,
  question_number VARCHAR(10) NOT NULL,
  no_of_questions INT DEFAULT 1,
  sort_order INT DEFAULT 0,
  INDEX (template_id),
  CONSTRAINT fk_ep_tq_template FOREIGN KEY (template_id) REFERENCES ep_templates(template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_questions (
  question_id INT PRIMARY KEY,
  chapter_id INT NOT NULL,
  question_type VARCHAR(30) NOT NULL,      -- mcq | descriptive | fillinblanks
  question_number VARCHAR(10) NOT NULL,    -- 1a, 1b, 2 ... (matches template question_number)
  marks INT DEFAULT 1,
  difficulty TINYINT DEFAULT 2,
  source VARCHAR(50) DEFAULT NULL,
  passage TEXT,
  markup TEXT,
  markup2 TEXT,
  markup3 TEXT,
  answer_markup TEXT,
  answer_markup2 TEXT,
  has_image TINYINT(1) DEFAULT 0,
  has_image2 TINYINT(1) DEFAULT 0,
  has_answer_image TINYINT(1) DEFAULT 0,
  sub_questions JSON NULL,
  INDEX idx_pick (chapter_id, question_type, question_number),
  CONSTRAINT fk_ep_question_chapter FOREIGN KEY (chapter_id) REFERENCES ep_chapters(chapter_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ep_papers (
  paper_id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  paper_type VARCHAR(20) NOT NULL DEFAULT 'regular', -- regular | competitive
  exam_name VARCHAR(150) DEFAULT NULL,     -- competitive: e.g. 5th Navodaya (JNVST)
  std_label VARCHAR(50) DEFAULT NULL,      -- competitive: class label printed on paper
  standard_id INT DEFAULT NULL,            -- regular papers only
  subject_id INT DEFAULT NULL,
  template_id INT DEFAULT NULL,
  exam_date DATE DEFAULT NULL,
  duration VARCHAR(50) DEFAULT NULL,
  total_marks DECIMAL(7,2) DEFAULT 0,
  instructions TEXT,
  paper_json LONGTEXT NOT NULL,            -- sections + chosen question ids
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (standard_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO ep_settings (setting_key, setting_value) VALUES
  ('school_name', 'My School'),
  ('school_address', ''),
  ('school_logo', ''),
  ('paper_footer', 'All the Best'),
  ('watermark_text', ''),
  ('watermark_logo', '0');
