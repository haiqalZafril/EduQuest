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
 * Load an array from a JSON file in the data directory.
 *
 * @param string $name Filename without extension.
 * @return array
 */
function eq_load_data(string $name): array {
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

// ==================== USER DATA HELPERS (DATABASE-backed) ====================

/**
 * Get all users (associative by username)
 * @return array
 */
function eq_get_users(): array {
    $conn = eq_get_db();
    $result = $conn->query("SELECT * FROM users");
    $users = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Key by username for backward compatibility
            $users[$row['username']] = $row;
        }
    }
    return $users;
}

/**
 * Get a single user by username (or null)
 */
function eq_get_user(string $username): ?array {
    $conn = eq_get_db();
    $username = $conn->real_escape_string($username);
    $result = $conn->query("SELECT * FROM users WHERE username = '$username'");
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

/**
 * Save (create or update) a user record keyed by username
 */
function eq_save_user(string $username, array $data): void {
    $conn = eq_get_db();
    $username = $conn->real_escape_string($username);
    $password = $conn->real_escape_string($data['password'] ?? '');
    $role = $conn->real_escape_string($data['role'] ?? 'student');
    $name = $conn->real_escape_string($data['name'] ?? '');
    $email = $conn->real_escape_string($data['email'] ?? '');
    $academic = $conn->real_escape_string($data['academic'] ?? '');
    $avatar = $conn->real_escape_string($data['avatar'] ?? '');
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both create and update
    $sql = "INSERT INTO users (username, password, role, name, email, academic, avatar) 
            VALUES ('$username', '$password', '$role', '$name', '$email', '$academic', '$avatar')
            ON DUPLICATE KEY UPDATE 
                password = '$password', 
                role = '$role', 
                name = '$name', 
                email = '$email', 
                academic = '$academic', 
                avatar = '$avatar'";
    
    $conn->query($sql);
}

/**
 * Change a user's username (since username is the primary key)
 * Updates all related records and the session
 * @param string $old_username Current username
 * @param string $new_username New username to change to
 * @return array Result with 'success' boolean and 'error' message if failed
 */
function eq_change_username(string $old_username, string $new_username): array {
    $conn = eq_get_db();
    
    // Validate new username
    if (empty($new_username)) {
        return ['success' => false, 'error' => 'Username cannot be empty.'];
    }
    
    // Check if new username already exists (and is not the same user)
    $new_username_esc = $conn->real_escape_string($new_username);
    $result = $conn->query("SELECT username FROM users WHERE username = '$new_username_esc'");
    if ($result && $result->num_rows > 0) {
        return ['success' => false, 'error' => 'Username already taken.'];
    }
    
    // Get the current user data
    $user = eq_get_user($old_username);
    if (!$user) {
        return ['success' => false, 'error' => 'User not found.'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        $old_username_esc = $conn->real_escape_string($old_username);
        
        // Update submissions table
        $conn->query("UPDATE submissions SET student_name = '$new_username_esc' WHERE student_name = '$old_username_esc'");
        
        // Update files table
        $conn->query("UPDATE files SET uploaded_by = '$new_username_esc' WHERE uploaded_by = '$old_username_esc'");
        
        // Update announcements table
        $conn->query("UPDATE announcements SET author = '$new_username_esc' WHERE author = '$old_username_esc'");
        
        // Update discussions table
        $conn->query("UPDATE discussions SET author = '$new_username_esc' WHERE author = '$old_username_esc'");
        
        // Update discussion_replies table
        $conn->query("UPDATE discussion_replies SET author = '$new_username_esc' WHERE author = '$old_username_esc'");
        
        // Delete old user record
        $conn->query("DELETE FROM users WHERE username = '$old_username_esc'");
        
        // Insert new user record with new username
        $password = $conn->real_escape_string($user['password'] ?? '');
        $role = $conn->real_escape_string($user['role'] ?? 'student');
        $name = $conn->real_escape_string($user['name'] ?? '');
        $email = $conn->real_escape_string($user['email'] ?? '');
        $academic = $conn->real_escape_string($user['academic'] ?? '');
        $avatar = $conn->real_escape_string($user['avatar'] ?? '');
        
        $sql = "INSERT INTO users (username, password, role, name, email, academic, avatar, created_at, updated_at) 
                VALUES ('$new_username_esc', '$password', '$role', '$name', '$email', '$academic', '$avatar', 
                        '{$user['created_at']}', NOW())";
        $conn->query($sql);
        
        // Commit transaction
        $conn->commit();
        
        // Update session if it's the current user
        if (isset($_SESSION['username']) && $_SESSION['username'] === $old_username) {
            $_SESSION['username'] = $new_username;
        }
        
        return ['success' => true];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => 'Failed to update username: ' . $e->getMessage()];
    }
}

/**
 * Delete a user
 */
function eq_delete_user(string $username): void {
    $conn = eq_get_db();
    $username = $conn->real_escape_string($username);
    $sql = "DELETE FROM users WHERE username = '$username'";
    $conn->query($sql);
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

// ==================== DATABASE FUNCTIONS FOR ANNOUNCEMENTS & DISCUSSIONS ====================

/**
 * Get database connection
 */
function eq_get_db() {
    static $connection = null;
    if ($connection === null) {
        require_once __DIR__ . '/db_config.php';
        $connection = isset($GLOBALS['conn']) ? $GLOBALS['conn'] : null;
        if ($connection === null) {
            die('Database connection failed: $GLOBALS[\'conn\'] is not set after including db_config.php');
        }
    }
    return $connection;
}

/**
 * Load all announcements from database
 */
function eq_load_announcements() {
    $conn = eq_get_db();
    $result = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");
    $announcements = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $announcements[] = $row;
        }
    }
    return $announcements;
}

/**
 * Save announcement to database
 */
function eq_save_announcement($author, $author_role, $title, $category, $content) {
    $conn = eq_get_db();
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
}

/**
 * Delete announcement from database
 */
function eq_delete_announcement($id) {
    $conn = eq_get_db();
    $id = (int)$id;
    $sql = "DELETE FROM announcements WHERE id = $id";
    return $conn->query($sql);
}

/**
 * Load all discussions from database
 */
function eq_load_discussions() {
    $conn = eq_get_db();
    $result = $conn->query("SELECT * FROM discussions ORDER BY created_at DESC");
    $discussions = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Load replies for this discussion
            $replies_result = $conn->query("SELECT * FROM discussion_replies WHERE discussion_id = {$row['id']} ORDER BY created_at ASC");
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
}

/**
 * Save discussion to database
 */
function eq_save_discussion($author, $author_role, $title, $content) {
    $conn = eq_get_db();
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
}

/**
 * Add reply to discussion
 */
function eq_add_discussion_reply($discussion_id, $author, $author_role, $content) {
    $conn = eq_get_db();
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
}

/**
 * Delete discussion from database
 */
function eq_delete_discussion($id) {
    $conn = eq_get_db();
    $id = (int)$id;
    $sql = "DELETE FROM discussions WHERE id = $id";
    return $conn->query($sql);
}

/**
 * Delete discussion reply from database
 */
function eq_delete_discussion_reply($id) {
    $conn = eq_get_db();
    $id = (int)$id;
    $sql = "DELETE FROM discussion_replies WHERE id = $id";
    return $conn->query($sql);
}

/**
 * Update discussion in database
 */
function eq_update_discussion($id, $title, $content) {
    $conn = eq_get_db();
    $id = (int)$id;
    $title = $conn->real_escape_string($title);
    $content = $conn->real_escape_string($content);
    
    $sql = "UPDATE discussions SET title = '$title', content = '$content' WHERE id = $id";
    return $conn->query($sql);
}

/**
 * Update discussion reply in database
 */
function eq_update_discussion_reply($id, $content) {
    $conn = eq_get_db();
    $id = (int)$id;
    $content = $conn->real_escape_string($content);
    
    $sql = "UPDATE discussion_replies SET content = '$content' WHERE id = $id";
    return $conn->query($sql);
}

// ==================== PENDING USER REQUESTS ====================

/**
 * Get all pending registration requests from the database
 * @return array Associative array keyed by email
 */
function eq_get_pending_requests(): array {
    $conn = eq_get_db();
    $result = $conn->query("SELECT * FROM pending_users ORDER BY created_at ASC");
    $pending = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $pending[$row['email']] = $row;
        }
    }
    return $pending;
}

/**
 * Save a pending registration request to the database
 * @param string $email User's email (primary key)
 * @param array $data Request data (must include: username, name, role, password)
 */
function eq_save_pending_request(string $email, array $data): void {
    $conn = eq_get_db();
    $email = $conn->real_escape_string($email);
    $username = $conn->real_escape_string($data['username'] ?? '');
    $name = $conn->real_escape_string($data['name'] ?? '');
    $role = $conn->real_escape_string($data['role'] ?? 'student');
    $password = $conn->real_escape_string($data['password'] ?? '');
    
    // Use INSERT ... ON DUPLICATE KEY UPDATE to handle both create and update
    $sql = "INSERT INTO pending_users (email, username, name, role, password) 
            VALUES ('$email', '$username', '$name', '$role', '$password')
            ON DUPLICATE KEY UPDATE username = '$username', name = '$name', role = '$role', password = '$password'";
    
    $conn->query($sql);
}

/**
 * Delete a pending registration request from the database
 * @param string $email User's email to delete
 */
function eq_delete_pending_request(string $email): void {
    $conn = eq_get_db();
    $email = $conn->real_escape_string($email);
    $sql = "DELETE FROM pending_users WHERE email = '$email'";
    $conn->query($sql);
}

/**
 * Approve a pending registration request
 * Creates the user account and removes from pending requests
 * @param string $email Email of the pending user
 * @return bool True if approved successfully, false otherwise
 */
function eq_approve_pending_request(string $email): bool {
    $pending = eq_get_pending_requests();
    
    if (!isset($pending[$email])) {
        return false;
    }
    
    $req = $pending[$email];
    
    // Check if user already exists
    $users = eq_get_users();
    if (isset($users[$email])) {
        // User already exists, just remove from pending
        eq_delete_pending_request($email);
        return false;
    }
    
    // Create the user account
    $conn = eq_get_db();
    $username = $conn->real_escape_string($req['username'] ?? $email);
    $email_esc = $conn->real_escape_string($email);
    $name = $conn->real_escape_string($req['name'] ?? '');
    $role = $conn->real_escape_string($req['role'] ?? 'student');
    $password = $conn->real_escape_string($req['password'] ?? '');
    
    $sql = "INSERT INTO users (username, name, email, role, password, academic, avatar) 
            VALUES ('$username', '$name', '$email_esc', '$role', '$password', '', '')";
    
    if ($conn->query($sql)) {
        // Successfully created user, now delete from pending
        eq_delete_pending_request($email);
        return true;
    }
    
    return false;
}
