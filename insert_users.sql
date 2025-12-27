-- Auto-generated SQL to insert users from JSON into database
-- Generated on: 2025-12-26 22:00:26

USE `eduquest_db`;

-- Insert users
INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`, `academic`, `avatar`) VALUES
('admin1', 'admin123', 'admin', 'Admin One', 'admin1@example.com', '', '')
ON DUPLICATE KEY UPDATE
  `password` = 'admin123',
  `role` = 'admin',
  `name` = 'Admin One',
  `email` = 'admin1@example.com',
  `academic` = '',
  `avatar` = '';

INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`, `academic`, `avatar`) VALUES
('mhz@gmail.com', 'haiqal123', 'student', 'Muhammad Haiqal Zafril', 'mhz@gmail.com', '', 'data/avatars/mhz_gmail.com.png')
ON DUPLICATE KEY UPDATE
  `password` = 'haiqal123',
  `role` = 'student',
  `name` = 'Muhammad Haiqal Zafril',
  `email` = 'mhz@gmail.com',
  `academic` = '',
  `avatar` = 'data/avatars/mhz_gmail.com.png';

INSERT INTO `users` (`username`, `password`, `role`, `name`, `email`, `academic`, `avatar`) VALUES
('aa@gmail.com', 'aa123', 'student', 'Ahmad Amri', 'aa@gmail.com', '', '')
ON DUPLICATE KEY UPDATE
  `password` = 'aa123',
  `role` = 'student',
  `name` = 'Ahmad Amri',
  `email` = 'aa@gmail.com',
  `academic` = '',
  `avatar` = '';

-- Total users: 3
