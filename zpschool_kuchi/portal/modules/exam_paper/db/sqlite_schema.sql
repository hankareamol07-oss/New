-- SQLite Schema for Exam Paper Generator Module (data/exam_paper.sqlite)

CREATE TABLE IF NOT EXISTS ep_standards (
  standard_id INTEGER PRIMARY KEY,
  name TEXT NOT NULL,
  medium TEXT NOT NULL,
  board TEXT DEFAULT NULL,
  sort_order INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS ep_subjects (
  subject_id INTEGER PRIMARY KEY,
  standard_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  FOREIGN KEY (standard_id) REFERENCES ep_standards(standard_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_sub_std ON ep_subjects (standard_id);

CREATE TABLE IF NOT EXISTS ep_chapters (
  chapter_id INTEGER PRIMARY KEY,
  subject_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  sort_order INTEGER DEFAULT 0,
  FOREIGN KEY (subject_id) REFERENCES ep_subjects(subject_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_ch_sub ON ep_chapters (subject_id);

CREATE TABLE IF NOT EXISTS ep_templates (
  template_id INTEGER PRIMARY KEY,
  subject_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  total_marks INTEGER NOT NULL,
  description TEXT DEFAULT NULL,
  FOREIGN KEY (subject_id) REFERENCES ep_subjects(subject_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_tpl_sub ON ep_templates (subject_id);

CREATE TABLE IF NOT EXISTS ep_template_questions (
  tq_id INTEGER PRIMARY KEY,
  template_id INTEGER NOT NULL,
  question_title TEXT NOT NULL,
  marks INTEGER NOT NULL,
  question_type TEXT NOT NULL,
  question_number TEXT NOT NULL,
  no_of_questions INTEGER DEFAULT 1,
  sort_order INTEGER DEFAULT 0,
  FOREIGN KEY (template_id) REFERENCES ep_templates(template_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_tq_tpl ON ep_template_questions (template_id);

CREATE TABLE IF NOT EXISTS ep_questions (
  question_id INTEGER PRIMARY KEY,
  chapter_id INTEGER NOT NULL,
  question_type TEXT NOT NULL,
  question_number TEXT NOT NULL,
  marks INTEGER DEFAULT 1,
  difficulty INTEGER DEFAULT 2,
  source TEXT DEFAULT NULL,
  passage TEXT,
  markup TEXT,
  markup2 TEXT,
  markup3 TEXT,
  answer_markup TEXT,
  answer_markup2 TEXT,
  has_image INTEGER DEFAULT 0,
  has_image2 INTEGER DEFAULT 0,
  has_answer_image INTEGER DEFAULT 0,
  sub_questions TEXT NULL,
  FOREIGN KEY (chapter_id) REFERENCES ep_chapters(chapter_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_ep_pick ON ep_questions (chapter_id, question_type, question_number);

CREATE TABLE IF NOT EXISTS ep_settings (
  setting_key TEXT PRIMARY KEY,
  setting_value TEXT
);

CREATE TABLE IF NOT EXISTS ep_papers (
  paper_id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  paper_type TEXT NOT NULL DEFAULT 'regular',
  exam_name TEXT DEFAULT NULL,
  std_label TEXT DEFAULT NULL,
  standard_id INTEGER DEFAULT NULL,
  subject_id INTEGER DEFAULT NULL,
  template_id INTEGER DEFAULT NULL,
  exam_date TEXT DEFAULT NULL,
  duration TEXT DEFAULT NULL,
  total_marks NUMERIC DEFAULT 0,
  instructions TEXT,
  paper_json TEXT NOT NULL,
  school_id INTEGER DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_papers_std_sub ON ep_papers (standard_id, subject_id);
CREATE INDEX IF NOT EXISTS idx_papers_school ON ep_papers (school_id);

CREATE TABLE IF NOT EXISTS ep_books (
  book_id INTEGER PRIMARY KEY,
  standard INTEGER NOT NULL,
  subject TEXT NOT NULL,
  medium TEXT NOT NULL,
  title TEXT NOT NULL,
  file TEXT NOT NULL,
  pages INTEGER DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_books_std_sub ON ep_books (standard, subject);

CREATE TABLE IF NOT EXISTS ep_book_chapters (
  chapter_id INTEGER PRIMARY KEY AUTOINCREMENT,
  book_id INTEGER NOT NULL,
  chapter_no INTEGER NOT NULL,
  title TEXT NOT NULL,
  start_page INTEGER DEFAULT NULL,
  end_page INTEGER DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_bch_bk ON ep_book_chapters (book_id, chapter_no);

CREATE TABLE IF NOT EXISTS ep_book_questions (
  bq_id INTEGER PRIMARY KEY,
  book_id INTEGER NOT NULL,
  chapter_id INTEGER DEFAULT NULL,
  standard INTEGER NOT NULL,
  subject TEXT NOT NULL,
  lang TEXT NOT NULL,
  page INTEGER NOT NULL,
  block TEXT DEFAULT '',
  instruction TEXT DEFAULT '',
  qtype TEXT NOT NULL,
  text TEXT NOT NULL,
  item_no INTEGER DEFAULT NULL,
  needs_figure INTEGER DEFAULT 0,
  page_image TEXT DEFAULT NULL,
  options_json TEXT DEFAULT NULL,
  answer TEXT DEFAULT NULL,
  ai_cleaned INTEGER NOT NULL DEFAULT 0,
  figure_image TEXT DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_bq_std_sub ON ep_book_questions (standard, subject);
CREATE INDEX IF NOT EXISTS idx_bq_ch_qtype ON ep_book_questions (chapter_id, qtype);

CREATE TABLE IF NOT EXISTS ep_topic_packs (
  chapter_id INTEGER PRIMARY KEY,
  book_id INTEGER NOT NULL,
  standard INTEGER NOT NULL,
  subject TEXT NOT NULL,
  lang TEXT NOT NULL,
  title TEXT NOT NULL,
  notes_json TEXT NOT NULL,
  quiz_json TEXT NOT NULL,
  model TEXT DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_tp_book ON ep_topic_packs (book_id);
CREATE INDEX IF NOT EXISTS idx_tp_std_sub ON ep_topic_packs (standard, subject);

CREATE TABLE IF NOT EXISTS ep_paper_models (
  model_id INTEGER PRIMARY KEY AUTOINCREMENT,
  exam_type TEXT NOT NULL,
  test_no INTEGER NOT NULL,
  standard INTEGER DEFAULT NULL,
  subject TEXT NOT NULL,
  total_marks INTEGER DEFAULT NULL,
  pages INTEGER DEFAULT 0,
  source_file TEXT DEFAULT NULL,
  sections_json TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_pm_type ON ep_paper_models (exam_type, standard, subject);

CREATE TABLE IF NOT EXISTS ep_quizzes (
  quiz_id INTEGER PRIMARY KEY AUTOINCREMENT,
  code TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  standard INTEGER NOT NULL,
  subject TEXT NOT NULL,
  medium TEXT DEFAULT '',
  topic TEXT DEFAULT '',
  time_limit INTEGER DEFAULT 0,
  show_answers INTEGER DEFAULT 1,
  questions_json TEXT NOT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_qz_std_sub ON ep_quizzes (standard, subject);

CREATE TABLE IF NOT EXISTS ep_quiz_attempts (
  attempt_id INTEGER PRIMARY KEY AUTOINCREMENT,
  quiz_id INTEGER NOT NULL,
  student_name TEXT NOT NULL,
  roll_no TEXT DEFAULT '',
  division TEXT DEFAULT '',
  answers_json TEXT NOT NULL,
  score NUMERIC NOT NULL DEFAULT 0,
  total INTEGER NOT NULL DEFAULT 0,
  submitted_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES ep_quizzes(quiz_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_qa_quiz ON ep_quiz_attempts (quiz_id, submitted_at);

CREATE TABLE IF NOT EXISTS ep_homework (
  hw_id INTEGER PRIMARY KEY AUTOINCREMENT,
  hw_date TEXT NOT NULL,
  standard INTEGER NOT NULL,
  division TEXT DEFAULT '',
  std_label TEXT DEFAULT '',
  subject TEXT NOT NULL,
  medium TEXT DEFAULT '',
  chapter_id INTEGER DEFAULT NULL,
  topic TEXT DEFAULT '',
  teacher TEXT DEFAULT '',
  title TEXT NOT NULL,
  note TEXT,
  items_json TEXT NOT NULL,
  quiz_id INTEGER DEFAULT NULL,
  notes_json TEXT DEFAULT NULL,
  created_at TEXT DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (quiz_id) REFERENCES ep_quizzes(quiz_id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_hw_date_std ON ep_homework (hw_date, standard);

INSERT OR IGNORE INTO ep_settings (setting_key, setting_value) VALUES
  ('school_name', 'माझी शाळा'),
  ('school_address', 'महाराष्ट्र राज्य'),
  ('school_logo', ''),
  ('paper_footer', 'All the Best • शुभेच्छा'),
  ('watermark_text', ''),
  ('watermark_logo', '0'),
  ('public_base_url', ''),
  ('default_paper_format', 'lines');
