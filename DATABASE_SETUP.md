# Database Setup Instructions

## Overview
This document explains how to set up the MySQL database for announcements and discussions in the EduQuest LMS. Other data (login, assignments, notes, etc.) will remain in JSON files.

## Step 1: Create the Database

1. Open phpMyAdmin at `http://localhost/phpmyadmin`
2. Go to the **SQL** tab
3. Copy the entire SQL code from `schema.sql` file
4. Paste it into the SQL editor
5. Click **Go** or **Execute**

This will create:
- Database: `eduquest_db`
- Tables: `announcements`, `discussions`, `discussion_replies`

## Step 2: Verify Database Configuration

Check that `db_config.php` has the correct settings:
```php
$db_host = 'localhost';
$db_username = 'root';
$db_password = '';
$db_name = 'eduquest_db';
```

**Note:** Update these values if your MySQL setup is different.

## Step 3: Files Updated

The following files have been updated to use MySQL for announcements and discussions:

### Student/Teacher Pages:
- `announcements.php` - Now saves/loads announcements from database
- `discussion.php` - Now saves/loads discussions and replies from database

### Admin Pages:
- `admin_announcements.php` - Now manages announcements from database
- `admin_discussions.php` - Now manages discussions from database

### Support Files:
- `data_store.php` - Added database functions for CRUD operations
- `db_config.php` - Database connection configuration
- `schema.sql` - SQL schema for creating tables

## Step 4: Test the System

1. Login as a **teacher** or **student**
2. Go to **Announcements** or **Discussion**
3. Create a new announcement or discussion
4. Verify it saves to the database (check in phpMyAdmin)
5. Refresh the page - the data should still be there

## Database Functions Added

New functions in `data_store.php`:

```php
// Announcements
eq_load_announcements()           // Get all announcements
eq_save_announcement()             // Create new announcement
eq_delete_announcement()          // Delete announcement

// Discussions
eq_load_discussions()              // Get all discussions with replies
eq_save_discussion()               // Create new discussion
eq_add_discussion_reply()          // Add reply to discussion
eq_delete_discussion()             // Delete discussion
eq_delete_discussion_reply()       // Delete reply
eq_update_discussion_reply()       // Update reply content
```

## Troubleshooting

### Database Connection Error
- Verify MySQL is running (check XAMPP Control Panel)
- Check `db_config.php` credentials
- Make sure `eduquest_db` database exists in phpMyAdmin

### Data Not Saving
- Check browser console for errors
- Verify database user has proper permissions
- Check MySQL error log in phpMyAdmin

### Old JSON Data Not Showing
- The system now only reads from database
- Old JSON data in `/data/announcements.json` and `/data/discussions.json` is no longer used
- You can delete those files or keep them as backup

## Next Steps

When ready, you can migrate other data types (assignments, notes, etc.) using the same pattern:
1. Create database tables
2. Add database functions to `data_store.php`
3. Update the respective PHP files to use database functions

## Additional Notes

- All announcements and discussions created before this update will need to be manually migrated if using JSON backups
- The database uses InnoDB with foreign key constraints for data integrity
- Replies are automatically deleted when a discussion is deleted
