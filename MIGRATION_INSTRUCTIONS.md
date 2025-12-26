# Migration to MySQL Database - Instructions

## Overview
All data is now migrated from JSON files to MySQL database (`eduquest_db`).

## Step 1: Create Database Tables

You have two options:

### Option A: Use schema.sql (Recommended)
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Go to **SQL** tab
3. Copy and paste the entire contents of `schema.sql`
4. Click **Go** or **Execute**

This will create all necessary tables:
- `users` - User accounts
- `pending_users` - Pending registration requests
- `assignments` - Assignments
- `notes` - Notes
- `submissions` - Assignment submissions
- `files` - Files
- `content_statuses` - Content moderation statuses
- `announcements` - Announcements (already exists)
- `discussions` - Discussions (already exists)
- `discussion_replies` - Discussion replies (already exists)

### Option B: Use setup_database.php
Visit: `http://localhost/EduQuest/setup_database.php`
(Note: You may need to update this file to include all tables)

## Step 2: Run Migration Script

1. Visit: `http://localhost/EduQuest/migrate_to_database.php`
2. This will copy all existing data from JSON files to the database
3. Review the results and check for any errors

## Step 3: Verify Migration

1. Check phpMyAdmin to see if data exists in the tables
2. Try logging in with existing accounts
3. Try creating assignments, notes, etc.

## What Changed

### Functions Updated (now use database):
- `eq_get_users()` - Loads from `users` table
- `eq_save_user()` - Saves to `users` table
- `eq_get_pending_requests()` - Loads from `pending_users` table
- `eq_save_pending_request()` - Saves to `pending_users` table
- `eq_load_data('assignments')` - Loads from `assignments` table
- `eq_load_data('notes')` - Loads from `notes` table
- `eq_load_data('submissions')` - Loads from `submissions` table
- `eq_load_data('files')` - Loads from `files` table

### Important Notes:
- **JSON files are still there** but the system now reads from database
- Old JSON data is preserved as backup
- You can delete JSON files after verifying migration worked
- Database connection settings are in `db_config.php`

## Troubleshooting

### If migration fails:
1. Check database connection in `db_config.php`
2. Make sure all tables exist (run schema.sql)
3. Check MySQL is running in XAMPP

### If data doesn't appear:
1. Check if migration script ran successfully
2. Verify data exists in database tables (use phpMyAdmin)
3. Check browser console for errors

