# User Migration Guide - Store All Users in Database

## Overview
This guide will help you migrate all users from JSON files to the MySQL database in phpMyAdmin.

## Prerequisites
- XAMPP installed and running
- Apache and MySQL services started
- phpMyAdmin accessible at http://localhost/phpmyadmin/

## Step-by-Step Instructions

### Step 1: Create the Database Tables

1. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin/
   - Login if required (default: username=root, password=empty)

2. **Select or Create the Database**
   - Look for `eduquest_db` in the left sidebar
   - If it doesn't exist, click "New" and create it
   - Select `eduquest_db` database

3. **Import the SQL File**
   - Click the **"Import"** tab at the top
   - Click **"Choose File"** button
   - Navigate to: `C:\xampp\htdocs\EduQuest\add_missing_tables.sql`
   - Click **"Go"** at the bottom
   - You should see: "Import has been successfully finished"

### Step 2: Verify Tables Were Created

1. In phpMyAdmin, with `eduquest_db` selected
2. Click **"Structure"** tab
3. You should see these tables:
   - ✓ users
   - ✓ pending_users
   - ✓ assignments
   - ✓ notes
   - ✓ submissions
   - ✓ files
   - ✓ content_statuses

### Step 3: Migrate Users from JSON to Database

1. **Run the Migration Script**
   - Open your browser
   - Go to: http://localhost:3310/EduQuest/migrate_users_to_db.php
   - The script will automatically:
     - Check database connection
     - Verify the users table exists
     - Load users from users.json
     - Migrate each user to the database
     - Show you a detailed report

2. **Review the Results**
   - The page will show you:
     - How many users were migrated
     - How many were skipped (already exist)
     - Any errors that occurred
     - Total users now in database

### Step 4: Verify Users in phpMyAdmin

1. Go back to phpMyAdmin
2. Select `eduquest_db` database
3. Click on the **"users"** table
4. Click **"Browse"** tab
5. You should see all your users listed:
   - admin1
   - mhz@gmail.com
   - aa@gmail.com
   - (and any others you have)

## Troubleshooting

### Problem: "Database connection failed"
**Solution:**
- Make sure XAMPP MySQL is running
- Check that port 3310 is correct in `db_config.php`
- Try accessing phpMyAdmin to confirm MySQL is working

### Problem: "Users table does not exist"
**Solution:**
- Go back to Step 1 and import the `add_missing_tables.sql` file
- Make sure you selected the correct database before importing

### Problem: "users.json file not found"
**Solution:**
- Check that the file exists at: `C:\xampp\htdocs\EduQuest\data\users.json`
- Verify the file has proper JSON format

### Problem: Port 3310 doesn't work
**Solution:**
- Check your MySQL port in XAMPP Control Panel
- Update `db_config.php` with the correct port (usually 3306 or 3310)

## What Happens After Migration?

After successful migration:
- ✓ All users are stored in the MySQL database
- ✓ User login will work with database authentication
- ✓ User data is persistent and properly structured
- ✓ JSON files can be kept as backup (but won't be used)
- ✓ You can manage users directly in phpMyAdmin

## Additional Notes

- The migration script is **safe to run multiple times** - it will skip users that already exist
- Original JSON files are not deleted - they remain as backup
- You can add new users directly in phpMyAdmin or through the application

## Quick Commands

### Start XAMPP Services (Windows)
```
Open XAMPP Control Panel
Click "Start" on Apache
Click "Start" on MySQL
```

### Access Key URLs
- phpMyAdmin: http://localhost/phpmyadmin/
- Migration Script: http://localhost:3310/EduQuest/migrate_users_to_db.php
- EduQuest App: http://localhost:3310/EduQuest/

## Success Indicators

You'll know everything worked when:
1. ✓ phpMyAdmin shows the users table with data
2. ✓ Migration script shows "Migration completed successfully!"
3. ✓ You can log in to EduQuest with your users
4. ✓ User count in database matches your JSON file

---

**Need Help?** Check the error messages in the migration script - they provide specific solutions for common issues.
