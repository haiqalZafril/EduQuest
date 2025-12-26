<?php
/**
 * Safe upgrade script for the users table.
 * Run from the project root with: php scripts/upgrade_users_schema.php
 * It will:
 *  - Add missing columns to `users` if they don't exist.
 *  - Optionally migrate plaintext `password` values into `password_hash` (using password_hash()).
 *  - Add unique index on email if no duplicates exist.
 *
 * IMPORTANT: Backup your DB first. This script is defensive but you should still backup.
 */

require_once __DIR__ . '/../data_store.php';

$conn = eq_get_db();
if (!$conn) {
    echo "Could not establish DB connection. Check db_config.php\n";
    exit(1);
}

function column_exists($conn, $table, $column) {
    $sql = "SHOW COLUMNS FROM `$table` LIKE ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $column);
    $stmt->execute();
    $res = $stmt->get_result();
    $exists = $res && $res->num_rows > 0;
    $stmt->close();
    return $exists;
}

$adds = [];
// Columns we want to ensure exist (definition part after column name)
$wanted = [
    'username' => "VARCHAR(191) DEFAULT NULL",
    'password_hash' => "VARCHAR(255) DEFAULT NULL",
    'role' => "ENUM('student','teacher','admin') NOT NULL DEFAULT 'student'",
    'academic' => "VARCHAR(255) DEFAULT ''",
    'avatar' => "VARCHAR(255) DEFAULT ''",
    'is_active' => "TINYINT(1) DEFAULT 1",
    'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
    'approved_at' => "DATETIME NULL",
    'approved_by' => "VARCHAR(191) NULL"
];

echo "Checking and adding missing columns on users table...\n";
foreach ($wanted as $col => $def) {
    if (!column_exists($conn, 'users', $col)) {
        $sql = "ALTER TABLE `users` ADD COLUMN `$col` $def";
        if ($conn->query($sql) === TRUE) {
            echo "Added column: $col\n";
            $adds[] = $col;
        } else {
            echo "Failed to add column $col: " . $conn->error . "\n";
        }
    } else {
        echo "Column exists: $col\n";
    }
}

// If there is an old plaintext 'password' column, back it up into 'password_plain_backup'
if (column_exists($conn, 'users', 'password')) {
    if (!column_exists($conn, 'users', 'password_plain_backup')) {
        $sql = "ALTER TABLE `users` ADD COLUMN `password_plain_backup` VARCHAR(255) DEFAULT NULL";
        if ($conn->query($sql) === TRUE) {
            echo "Added column: password_plain_backup\n";
            // Move current password values into backup column
            if ($conn->query("UPDATE `users` SET password_plain_backup = password WHERE password IS NOT NULL") === TRUE) {
                echo "Backed up existing plaintext passwords to password_plain_backup\n";
                // Null out the original password column for safety
                if ($conn->query("UPDATE `users` SET password = NULL WHERE password IS NOT NULL") === TRUE) {
                    echo "Cleared old password column values (left column structure intact).\n";
                }
            }
        } else {
            echo "Failed to add password_plain_backup: " . $conn->error . "\n";
        }
    } else {
        echo "Column exists: password_plain_backup\n";
    }
}

// Migrate any non-hashed passwords found in `password_plain_backup` into `password_hash`
if (column_exists($conn, 'users', 'password_plain_backup')) {
    $res = $conn->query("SELECT id, password_plain_backup FROM users WHERE password_plain_backup IS NOT NULL AND (password_hash IS NULL OR password_hash = '')");
    if ($res && $res->num_rows > 0) {
        echo "Migrating plaintext passwords to password_hash for " . $res->num_rows . " users...\n";
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        foreach ($res as $row) {
            $id = $row['id'];
            $plain = $row['password_plain_backup'];
            $hash = password_hash($plain, PASSWORD_DEFAULT);
            $stmt->bind_param('si', $hash, $id);
            $stmt->execute();
        }
        $stmt->close();
        echo "Password migration complete.\n";
    } else {
        echo "No plaintext passwords found to migrate.\n";
    }
}

// Ensure email uniqueness index exists (but only if there are no duplicates)
$dupRes = $conn->query("SELECT email, COUNT(*) c FROM users GROUP BY email HAVING c > 1");
if ($dupRes && $dupRes->num_rows > 0) {
    echo "Cannot add UNIQUE index on email: found duplicate email values. Please resolve duplicates first.\n";
    while ($r = $dupRes->fetch_assoc()) {
        echo "Duplicate: " . $r['email'] . " (" . $r['c'] . " entries)\n";
    }
} else {
    // Check index existence
    $idxRes = $conn->query("SHOW INDEX FROM users WHERE Key_name = 'uq_users_email'");
    if (!$idxRes || $idxRes->num_rows === 0) {
        // try adding unique index
        $sql = "ALTER TABLE users ADD CONSTRAINT uq_users_email UNIQUE (email)";
        if ($conn->query($sql) === TRUE) {
            echo "Added UNIQUE constraint on email (uq_users_email).\n";
        } else {
            echo "Failed to add UNIQUE constraint on email: " . $conn->error . "\n";
        }
    } else {
        echo "Unique index on email already exists.\n";
    }
}

// Ensure there's an index on role
$idxRoleRes = $conn->query("SHOW INDEX FROM users WHERE Column_name = 'role'");
if (!$idxRoleRes || $idxRoleRes->num_rows === 0) {
    if ($conn->query("ALTER TABLE users ADD INDEX idx_role (role)") === TRUE) {
        echo "Added index idx_role (role).\n";
    } else {
        echo "Failed to add idx_role: " . $conn->error . "\n";
    }
} else {
    echo "Index on role already exists.\n";
}

// Ensure is_active set for existing rows (mark existing as active if NULL)
if (column_exists($conn, 'users', 'is_active')) {
    if ($conn->query("UPDATE users SET is_active = 1 WHERE is_active IS NULL") === TRUE) {
        echo "Set is_active=1 for existing rows where it was NULL.\n";
    }
}

echo "Upgrade completed. Please review the output for any errors and backup your DB before making further changes.\n";

?>