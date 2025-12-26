-- Add Missing Tables to Existing eduquest_db Database
-- Run this SQL in phpMyAdmin to add only the new tables
-- Make sure you're using the eduquest_db database first!

USE `eduquest_db`;

-- Users table
CREATE TABLE IF NOT EXISTS `users` (
  `username` VARCHAR(255) NOT NULL PRIMARY KEY,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
  `name` VARCHAR(255) DEFAULT '',
  `email` VARCHAR(255) DEFAULT '',
  `academic` VARCHAR(255) DEFAULT '',
  `avatar` VARCHAR(255) DEFAULT '',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pending Users table (for registration approval)
CREATE TABLE IF NOT EXISTS `pending_users` (
  `email` VARCHAR(255) NOT NULL PRIMARY KEY,
  `username` VARCHAR(255) NOT NULL UNIQUE,
  `name` VARCHAR(255) NOT NULL,
  `role` ENUM('student','teacher') NOT NULL DEFAULT 'student',
  `password` VARCHAR(255) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_role` (`role`),
  INDEX `idx_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assignments table
CREATE TABLE IF NOT EXISTS `assignments` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `deadline` DATETIME NOT NULL,
  `max_score` INT DEFAULT 100,
  `rubric` TEXT,
  `course_code` VARCHAR(50) DEFAULT '',
  `files` JSON DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_deadline` (`deadline`),
  INDEX `idx_course_code` (`course_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notes table
CREATE TABLE IF NOT EXISTS `notes` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `topic` VARCHAR(255) DEFAULT '',
  `content` TEXT,
  `course_code` VARCHAR(50) DEFAULT '',
  `attachment_name` VARCHAR(255) DEFAULT '',
  `attachment_stored` VARCHAR(255) DEFAULT '',
  `file_size` INT DEFAULT 0,
  `version` INT DEFAULT 1,
  `downloads` INT DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'shared',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_course_code` (`course_code`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Submissions table
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `assignment_id` INT NOT NULL,
  `student_name` VARCHAR(255) NOT NULL,
  `file_name` VARCHAR(255) DEFAULT '',
  `stored_name` VARCHAR(255) DEFAULT '',
  `score` INT DEFAULT NULL,
  `feedback` TEXT,
  `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_assignment_id` (`assignment_id`),
  INDEX `idx_student_name` (`student_name`),
  INDEX `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Files table
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `file_name` VARCHAR(255) DEFAULT '',
  `stored_name` VARCHAR(255) DEFAULT '',
  `file_size` INT DEFAULT 0,
  `file_type` VARCHAR(100) DEFAULT '',
  `uploaded_by` VARCHAR(255) DEFAULT '',
  `course_code` VARCHAR(50) DEFAULT '',
  `downloads` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_uploaded_by` (`uploaded_by`),
  INDEX `idx_course_code` (`course_code`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Content Statuses table
CREATE TABLE IF NOT EXISTS `content_statuses` (
  `content_key` VARCHAR(255) NOT NULL PRIMARY KEY,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `flag_reason` TEXT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

