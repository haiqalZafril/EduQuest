# Quick Fix for Database Connection Error

## Step 1: Start MySQL in XAMPP
1. Open XAMPP Control Panel
2. Make sure **MySQL** is **RUNNING** (should show green/started)
3. If not running, click the **Start** button next to MySQL

## Step 2: Create the Database
You have two options:

### Option A: Use the Setup Script (Easiest)
1. Open your browser
2. Go to: `http://localhost/EduQuest/setup_database.php`
3. This will automatically create the database and tables

### Option B: Use phpMyAdmin
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click on the **SQL** tab
3. Copy and paste the SQL from `schema.sql` file
4. Click **Go** or **Execute**

## Step 3: Test the Connection
1. Open: `http://localhost/EduQuest/test_db_connection.php`
2. This will show you if the connection works

## Step 4: Try Creating a Discussion Again
1. Go back to the discussion page
2. Try creating a topic
3. If it still fails, check the error message - it will now show the actual problem

## Common Issues:

### "Connection refused" or "Target machine actively refused it"
- **Solution**: MySQL is not running in XAMPP Control Panel
- Start MySQL in XAMPP

### "Unknown database 'eduquest_db'"
- **Solution**: Database doesn't exist
- Create it using setup_database.php or phpMyAdmin

### "Access denied"
- **Solution**: Wrong username or password
- Check db_config.php settings

### "Can't connect to MySQL server"
- **Solution**: Wrong port number
- Check if MySQL is on port 3306 (standard) or 3310 (custom)
- Update db_config.php with the correct port

