-- Add username column to existing pending_users table
-- Run this SQL in phpMyAdmin if the pending_users table already exists

USE `eduquest_db`;

-- Check if the username column doesn't exist and add it
ALTER TABLE `pending_users` 
ADD COLUMN IF NOT EXISTS `username` VARCHAR(255) NOT NULL DEFAULT '' AFTER `email`,
ADD UNIQUE INDEX IF NOT EXISTS `idx_username` (`username`);

-- If your MySQL version doesn't support IF NOT EXISTS in ALTER TABLE, use this instead:
-- ALTER TABLE `pending_users` ADD COLUMN `username` VARCHAR(255) NOT NULL DEFAULT '' AFTER `email`;
-- ALTER TABLE `pending_users` ADD UNIQUE INDEX `idx_username` (`username`);
