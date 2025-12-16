-- EduQuest Database Schema
-- Run this SQL in phpMyAdmin to create the database and tables

-- Create database
CREATE DATABASE IF NOT EXISTS `eduquest_db`;
USE `eduquest_db`;

-- Announcements table
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `author` VARCHAR(255) NOT NULL,
  `author_role` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_category` (`category`),
  INDEX `idx_author` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discussions table
CREATE TABLE IF NOT EXISTS `discussions` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `author` VARCHAR(255) NOT NULL,
  `author_role` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_author` (`author`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discussion Replies table
CREATE TABLE IF NOT EXISTS `discussion_replies` (
  `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `discussion_id` INT NOT NULL,
  `author` VARCHAR(255) NOT NULL,
  `author_role` VARCHAR(50) NOT NULL,
  `content` LONGTEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`discussion_id`) REFERENCES `discussions`(`id`) ON DELETE CASCADE,
  INDEX `idx_discussion_id` (`discussion_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
