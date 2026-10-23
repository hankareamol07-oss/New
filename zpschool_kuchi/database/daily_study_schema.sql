CREATE TABLE IF NOT EXISTS `daily_study` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `school_id` INT NOT NULL DEFAULT 2,
  `std` TINYINT NOT NULL,
  `section` VARCHAR(10) DEFAULT 'A',
  `study_date` DATE NOT NULL,
  `subject` VARCHAR(100) NOT NULL,
  `topic` VARCHAR(255) NOT NULL,
  `tachan_id` INT NULL,
  `content` TEXT NOT NULL,
  `instructions` TEXT NULL,
  `teacher_id` INT NULL,
  `teacher_name` VARCHAR(150) NULL,
  `is_published` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_school_std_date` (`school_id`, `std`, `study_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `student_study_submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `study_id` INT NOT NULL,
  `student_id` INT NOT NULL,
  `status` ENUM('pending', 'completed') DEFAULT 'completed',
  `completed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `remarks` TEXT NULL,
  UNIQUE KEY `uniq_study_student` (`study_id`, `student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
