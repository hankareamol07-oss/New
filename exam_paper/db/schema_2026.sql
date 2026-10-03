-- 2026-27 syllabus alignment: book provenance (official eBalbharati 2026 edition vs legacy PDF) and
-- the link of every textbook chapter to the तचन (annual plan) topic of tachan_bank, so paper / homework / quiz /
-- YouTube / HPC all use one current topic list.  Run after schema_packs.sql, then: php import_books.php
SET NAMES utf8mb4;

ALTER TABLE ep_books
  ADD COLUMN IF NOT EXISTS source VARCHAR(30) NOT NULL DEFAULT 'legacy_pdf',   -- ebalbharati_2026 | legacy_pdf
  ADD COLUMN IF NOT EXISTS edition SMALLINT DEFAULT NULL,                      -- 2026 for the new-syllabus books
  ADD COLUMN IF NOT EXISTS official_id VARCHAR(12) DEFAULT NULL,               -- books.ebalbharati.in/pdfs/<official_id>.pdf
  ADD COLUMN IF NOT EXISTS tachan_subject VARCHAR(60) DEFAULT NULL;            -- tachan_bank.subject this book's chapters follow

ALTER TABLE ep_book_chapters
  ADD COLUMN IF NOT EXISTS tachan_seq SMALLINT DEFAULT NULL,                   -- position of the topic in tachan_bank (std, tachan_subject)
  ADD COLUMN IF NOT EXISTS old_chapter_no SMALLINT DEFAULT NULL;               -- chapter_no in the pre-2026 chapter list (NULL = new / renamed topic)
