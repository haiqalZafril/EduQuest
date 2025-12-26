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

// ==================== USER DATA HELPERS (JSON-backed) ====================

/**
 * Get all users (associative by username)
 * @return array
 */
function eq_get_users(): array {
    $u = eq_load_data('users');
    return is_array($u) ? $u : [];
}

/**
 * Get a single user by username (or null)
 */
function eq_get_user(string $username): ?array {
    $users = eq_get_users();
    return $users[$username] ?? null;
}

/**
 * Save (create or update) a user record keyed by username
 */
function eq_save_user(string $username, array $data): void {
    $users = eq_get_users();
    $users[$username] = $data;
    eq_save_data('users', $users);
}

/**
 * Delete a user
 */
function eq_delete_user(string $username): void {
    $users = eq_get_users();
    if (isset($users[$username])) {
        unset($users[$username]);
        eq_save_data('users', $users);
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

// ==================== PENDING REGISTRATION HELPERS ====================

/**
 * Get pending registration requests (assoc by email)
 */
function eq_get_pending_requests(): array {
    $p = eq_load_data('pending_users');
    return is_array($p) ? $p : [];
}

/**
 * Save a pending registration request
 */
function eq_save_pending_request(string $email, array $data): void {
    $pending = eq_get_pending_requests();
    $pending[$email] = $data;
    eq_save_data('pending_users', $pending);
}

/**
 * Delete a pending request
 */
function eq_delete_pending_request(string $email): void {
    $pending = eq_get_pending_requests();
    if (isset($pending[$email])) {
        unset($pending[$email]);
        eq_save_data('pending_users', $pending);
    }
}

/**
 * Approve a pending request (create user and remove pending)
 */
function eq_approve_pending_request(string $email): bool {
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
}

// ==================== DATABASE FUNCTIONS FOR ANNOUNCEMENTS & DISCUSSIONS ====================

/**
 * Get database connection
 */
function eq_get_db() {
    static $db_conn = null;
    if ($db_conn === null) {
        require_once __DIR__ . '/db_config.php';
        global $conn;
        $db_conn = $conn;
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


