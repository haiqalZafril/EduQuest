<?php
// Simple JSON-based data storage helpers for EduQuest LMS.

const EDUQUEST_DATA_DIR = __DIR__ . '/data';

/**
 * Ensure the data directory exists.
 */
function eq_ensure_data_dir(): void {
    if (!is_dir(EDUQUEST_DATA_DIR)) {
        mkdir(EDUQUEST_DATA_DIR, 0777, true);
    }
}

/**
 * Load an array from a JSON file in the data directory OR database.
 * For assignments, notes, submissions, files - loads from database
 * For other types - loads from JSON files (backward compatibility)
 *
 * @param string $name Filename/table name without extension.
 * @return array
 */
function eq_load_data(string $name): array {
    // Database-backed types
    $db_types = ['assignments', 'notes', 'submissions', 'files'];
    if (in_array($name, $db_types)) {
        return eq_load_data_db($name);
    }
    
    // Fallback to JSON for other types (backward compatibility)
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/' . $name . '.json';
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

/**
 * Save an array as JSON into the data directory.
 *
 * @param string $name Filename without extension.
 * @param array $data
 * @return void
 */
function eq_save_data(string $name, array $data): void {
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/' . $name . '.json';
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

/**
 * Generate a simple incremental ID based on existing items.
 *
 * @param array $items
 * @param string $key
 * @return int
 */
function eq_next_id(array $items, string $key = 'id'): int {
    $max = 0;
    foreach ($items as $item) {
        if (isset($item[$key]) && (int)$item[$key] > $max) {
            $max = (int)$item[$key];
        }
    }
    return $max + 1;
}

/**
 * Basic helper to escape HTML.
 */
function eq_h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// ==================== USER DATA HELPERS (Database-backed) ====================

/**
 * Get all users (associative by username)
 * Falls back to JSON if database is empty or connection fails
 * @return array
 */
function eq_get_users(): array {
    try {
        $conn = eq_get_db();
        if ($conn) {
            $result = $conn->query("SELECT * FROM users");
            $users = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $users[$row['username']] = $row;
                }
            }
            // If database has users, return them
            if (!empty($users)) {
                return $users;
            }
        }
    } catch (Exception $e) {
        // Fall through to JSON fallback
    }
    
    // Fallback to JSON if database is empty or connection failed
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/users.json';
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $users = json_decode($json, true);
        return is_array($users) ? $users : [];
    }
    return [];
}

/**
 * Get a single user by username (or null)
 */
function eq_get_user(string $username): ?array {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return null;
        }
        $username_escaped = $conn->real_escape_string($username);
        $result = $conn->query("SELECT * FROM users WHERE username = '$username_escaped' LIMIT 1");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Save (create or update) a user record keyed by username
 * Saves to database ONLY - no JSON fallback (users should be in database)
 */
function eq_save_user(string $username, array $data): void {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            error_log("Cannot save user: database connection failed");
            return;
        }
        
        $username_escaped = $conn->real_escape_string($username);
        $password = $conn->real_escape_string($data['password'] ?? '');
        $role = $conn->real_escape_string($data['role'] ?? 'student');
        $name = $conn->real_escape_string($data['name'] ?? '');
        $email = $conn->real_escape_string($data['email'] ?? '');
        $academic = $conn->real_escape_string($data['academic'] ?? '');
        $avatar = $conn->real_escape_string($data['avatar'] ?? '');
        
        // Check if user exists
        $existing = eq_get_user($username);
        if ($existing) {
            // Update
            $result = $conn->query("UPDATE users SET password = '$password', role = '$role', name = '$name', email = '$email', academic = '$academic', avatar = '$avatar' WHERE username = '$username_escaped'");
            if (!$result) {
                error_log("Failed to update user in database: " . $conn->error);
            }
        } else {
            // Insert
            $result = $conn->query("INSERT INTO users (username, password, role, name, email, academic, avatar) VALUES ('$username_escaped', '$password', '$role', '$name', '$email', '$academic', '$avatar')");
            if (!$result) {
                error_log("Failed to insert user into database: " . $conn->error);
            }
        }
    } catch (Exception $e) {
        error_log("Exception saving user to database: " . $e->getMessage());
    }
}

/**
 * Delete a user
 */
function eq_delete_user(string $username): void {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return;
        }
        $username_escaped = $conn->real_escape_string($username);
        $conn->query("DELETE FROM users WHERE username = '$username_escaped'");
    } catch (Exception $e) {
        // Silent fail
    }
}

/**
 * Register a user (simple helper). Password is stored as provided (plaintext) for now.
 */
function eq_register_user(string $username, string $password, string $role = 'student', string $name = '', string $email = ''): void {
    $user = [
        'password' => $password,
        'role' => $role,
        'name' => $name,
        'email' => $email,
        'academic' => '',
        'avatar' => ''
    ];
    eq_save_user($username, $user);
}

// ==================== PENDING REGISTRATION HELPERS (Database-backed) ====================

/**
 * Get pending registration requests (assoc by email)
 * Falls back to JSON if database is empty or connection fails
 */
function eq_get_pending_requests(): array {
    try {
        $conn = eq_get_db();
        if ($conn) {
            $result = $conn->query("SELECT * FROM pending_users");
            $pending = [];
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $pending[$row['email']] = $row;
                }
            }
            // If database has pending requests, return them
            if (!empty($pending)) {
                return $pending;
            }
        }
    } catch (Exception $e) {
        // Fall through to JSON fallback
    }
    
    // Fallback to JSON if database is empty or connection failed
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/pending_users.json';
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $pending = json_decode($json, true);
        return is_array($pending) ? $pending : [];
    }
    return [];
}

/**
 * Save a pending registration request
 * Saves to database if available, otherwise falls back to JSON
 */
function eq_save_pending_request(string $email, array $data): void {
    try {
        $conn = eq_get_db();
        if ($conn) {
            $email_escaped = $conn->real_escape_string($email);
            $name = $conn->real_escape_string($data['name'] ?? '');
            $role = $conn->real_escape_string($data['role'] ?? 'student');
            $password = $conn->real_escape_string($data['password'] ?? '');
            
            // Check if exists
            $result = $conn->query("SELECT email FROM pending_users WHERE email = '$email_escaped' LIMIT 1");
            if ($result && $result->num_rows > 0) {
                // Update
                $conn->query("UPDATE pending_users SET name = '$name', role = '$role', password = '$password' WHERE email = '$email_escaped'");
            } else {
                // Insert
                $conn->query("INSERT INTO pending_users (email, name, role, password) VALUES ('$email_escaped', '$name', '$role', '$password')");
            }
            return; // Success, exit early
        }
    } catch (Exception $e) {
        // Fall through to JSON fallback
    }
    
    // Fallback to JSON if database connection failed
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/pending_users.json';
    $pending = [];
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $pending = json_decode($json, true);
        $pending = is_array($pending) ? $pending : [];
    }
    $pending[$email] = $data;
    file_put_contents($file, json_encode($pending, JSON_PRETTY_PRINT));
}

/**
 * Delete a pending request
 * Deletes from database if available, otherwise from JSON
 */
function eq_delete_pending_request(string $email): void {
    try {
        $conn = eq_get_db();
        if ($conn) {
            $email_escaped = $conn->real_escape_string($email);
            $conn->query("DELETE FROM pending_users WHERE email = '$email_escaped'");
            return; // Success, exit early
        }
    } catch (Exception $e) {
        // Fall through to JSON fallback
    }
    
    // Fallback to JSON if database connection failed
    eq_ensure_data_dir();
    $file = EDUQUEST_DATA_DIR . '/pending_users.json';
    if (file_exists($file)) {
        $json = file_get_contents($file);
        $pending = json_decode($json, true);
        if (is_array($pending) && isset($pending[$email])) {
            unset($pending[$email]);
            file_put_contents($file, json_encode($pending, JSON_PRETTY_PRINT));
        }
    }
}

/**
 * Approve a pending request (create user and remove pending)
 */
function eq_approve_pending_request(string $email): bool {
    try {
        $pending = eq_get_pending_requests();
        if (!isset($pending[$email])) {
            return false;
        }
        $req = $pending[$email];
        // Create user account (password stored as-is to match existing approach)
        eq_register_user($email, $req['password'], $req['role'] ?? 'student', $req['name'] ?? '', $req['email'] ?? $email);
        // Remove pending
        eq_delete_pending_request($email);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==================== DATABASE FUNCTIONS FOR ANNOUNCEMENTS & DISCUSSIONS ====================

/**
 * Get database connection
 */
function eq_get_db() {
    static $db_conn = false; // Use false to distinguish between "not tried" and "failed"
    static $db_error = null;
    
    if ($db_conn === false) {
        try {
            // Clear any previous error
            $GLOBALS['db_connection_error'] = null;
            
            require_once __DIR__ . '/db_config.php';
            global $conn;
            $db_conn = $conn;
            
            // Check if error was set in db_config.php
            if ($db_conn === null && isset($GLOBALS['db_connection_error'])) {
                $db_error = $GLOBALS['db_connection_error'];
            }
        } catch (Exception $e) {
            $db_conn = null;
            $db_error = $e->getMessage();
            $GLOBALS['db_connection_error'] = $db_error;
        }
    }
    
    // Store error in GLOBALS for access by other functions
    if ($db_error) {
        $GLOBALS['db_connection_error'] = $db_error;
    }
    
    return $db_conn;
}

/**
 * Load all announcements from database
 */
function eq_load_announcements() {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return [];
        }
        $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
        $announcements = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        }
        return $announcements;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Save announcement to database
 */
function eq_save_announcement($author, $author_role, $title, $category, $content) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            $errorMsg = isset($GLOBALS['db_connection_error']) ? $GLOBALS['db_connection_error'] : 'Database connection failed. Please check your database configuration.';
            return ['success' => false, 'error' => $errorMsg];
        }
        $author = $conn->real_escape_string($author);
        $author_role = $conn->real_escape_string($author_role);
        $title = $conn->real_escape_string($title);
        $category = $conn->real_escape_string($category);
        $content = $conn->real_escape_string($content);
        
        $sql = "INSERT INTO announcements (author, author_role, title, category, content) 
                VALUES ('$author', '$author_role', '$title', '$category', '$content')";
        
        if ($conn->query($sql)) {
            return ['success' => true, 'id' => $conn->insert_id];
        } else {
            return ['success' => false, 'error' => $conn->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete announcement from database
 */
function eq_delete_announcement($id) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return false;
        }
        $id = (int)$id;
        $sql = "DELETE FROM announcements WHERE id = $id";
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Load all discussions from database
 */
function eq_load_discussions() {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return [];
        }
        $result = $conn->query("SELECT * FROM discussions ORDER BY created_at DESC");
        $discussions = [];
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Load replies for this discussion
                $discussion_id = (int)$row['id'];
                $replies_result = $conn->query("SELECT * FROM discussion_replies WHERE discussion_id = $discussion_id ORDER BY created_at ASC");
                $row['replies'] = [];
                
                if ($replies_result && $replies_result->num_rows > 0) {
                    while ($reply = $replies_result->fetch_assoc()) {
                        $row['replies'][] = $reply;
                    }
                }
                
                $discussions[] = $row;
            }
        }
        return $discussions;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Save discussion to database
 */
function eq_save_discussion($author, $author_role, $title, $content) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            $errorMsg = isset($GLOBALS['db_connection_error']) ? $GLOBALS['db_connection_error'] : 'Database connection failed. Please check your database configuration.';
            return ['success' => false, 'error' => $errorMsg];
        }
        $author = $conn->real_escape_string($author);
        $author_role = $conn->real_escape_string($author_role);
        $title = $conn->real_escape_string($title);
        $content = $conn->real_escape_string($content);
        
        $sql = "INSERT INTO discussions (author, author_role, title, content) 
                VALUES ('$author', '$author_role', '$title', '$content')";
        
        if ($conn->query($sql)) {
            return ['success' => true, 'id' => $conn->insert_id];
        } else {
            return ['success' => false, 'error' => $conn->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add reply to discussion
 */
function eq_add_discussion_reply($discussion_id, $author, $author_role, $content) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            $errorMsg = isset($GLOBALS['db_connection_error']) ? $GLOBALS['db_connection_error'] : 'Database connection failed. Please check your database configuration.';
            return ['success' => false, 'error' => $errorMsg];
        }
        $discussion_id = (int)$discussion_id;
        $author = $conn->real_escape_string($author);
        $author_role = $conn->real_escape_string($author_role);
        $content = $conn->real_escape_string($content);
        
        $sql = "INSERT INTO discussion_replies (discussion_id, author, author_role, content) 
                VALUES ($discussion_id, '$author', '$author_role', '$content')";
        
        if ($conn->query($sql)) {
            return ['success' => true, 'id' => $conn->insert_id];
        } else {
            return ['success' => false, 'error' => $conn->error];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Delete discussion from database
 */
function eq_delete_discussion($id) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return false;
        }
        $id = (int)$id;
        $sql = "DELETE FROM discussions WHERE id = $id";
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Delete discussion reply from database
 */
function eq_delete_discussion_reply($id) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return false;
        }
        $id = (int)$id;
        $sql = "DELETE FROM discussion_replies WHERE id = $id";
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update discussion in database
 */
function eq_update_discussion($id, $title, $content) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return false;
        }
        $id = (int)$id;
        $title = $conn->real_escape_string($title);
        $content = $conn->real_escape_string($content);
        
        $sql = "UPDATE discussions SET title = '$title', content = '$content' WHERE id = $id";
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Update discussion reply in database
 */
function eq_update_discussion_reply($id, $content) {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return false;
        }
        $id = (int)$id;
        $content = $conn->real_escape_string($content);
        
        $sql = "UPDATE discussion_replies SET content = '$content' WHERE id = $id";
        return $conn->query($sql);
    } catch (Exception $e) {
        return false;
    }
}

// ==================== ASSIGNMENTS, NOTES, SUBMISSIONS, FILES HELPERS (Database-backed) ====================

/**
 * Load data from database (helper for specific types)
 * Supports: assignments, notes, submissions, files, content_statuses
 */
function eq_load_data_db(string $name): array {
    try {
        $conn = eq_get_db();
        if (!$conn) {
            return [];
        }
        
        $table_name = $conn->real_escape_string($name);
        $result = $conn->query("SELECT * FROM `$table_name` ORDER BY id ASC");
        $data = [];
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Handle JSON fields (like files in assignments)
                if (isset($row['files']) && is_string($row['files'])) {
                    $decoded = json_decode($row['files'], true);
                    $row['files'] = is_array($decoded) ? $decoded : [];
                }
                $data[] = $row;
            }
        }
        return $data;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Save data to database (placeholder - individual functions handle this better)
 */
function eq_save_data_db(string $name, array $data): void {
    // This is a complex operation - would need to handle inserts/updates
    // For now, this is a placeholder - individual functions handle this better
}

// Note: eq_load_data() was updated earlier in the file to use database for specific types
// Note: eq_save_data cannot be easily overridden for database
// Individual pages will need to use database functions directly
// We'll keep eq_save_data for JSON compatibility but add warning


