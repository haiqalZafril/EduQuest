<?php
/**
 * Migration Script: Move all JSON data to MySQL database
 * Run this once to migrate existing JSON data to the database
 */

require_once __DIR__ . '/data_store.php';

echo "<h1>Data Migration to MySQL Database</h1>";
echo "<p>This script will migrate all data from JSON files to MySQL database.</p>";

$conn = eq_get_db();
if (!$conn) {
    die("<p style='color:red;'>❌ Cannot connect to database. Please check db_config.php settings.</p>");
}

echo "<p style='color:green;'>✓ Database connection successful!</p>";
echo "<hr>";

$migrated = 0;
$errors = 0;

// 1. Migrate Users
echo "<h2>1. Migrating Users...</h2>";
$users_json = eq_load_data('users'); // Load from JSON (old method)
if (!empty($users_json)) {
    foreach ($users_json as $username => $user_data) {
        try {
            // Check if user already exists
            $existing = eq_get_user($username);
            if (!$existing) {
                eq_save_user($username, $user_data);
                echo "<p>✓ Migrated user: $username</p>";
                $migrated++;
            } else {
                echo "<p>⏭ User already exists: $username (skipped)</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating user $username: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No users to migrate.</p>";
}

// 2. Migrate Pending Users
echo "<h2>2. Migrating Pending Users...</h2>";
$pending_json = eq_load_data('pending_users');
if (!empty($pending_json)) {
    foreach ($pending_json as $email => $pending_data) {
        try {
            $existing_pending = eq_get_pending_requests();
            if (!isset($existing_pending[$email])) {
                eq_save_pending_request($email, $pending_data);
                echo "<p>✓ Migrated pending user: $email</p>";
                $migrated++;
            } else {
                echo "<p>⏭ Pending user already exists: $email (skipped)</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating pending user $email: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No pending users to migrate.</p>";
}

// 3. Migrate Assignments
echo "<h2>3. Migrating Assignments...</h2>";
$assignments_json = json_decode(file_get_contents(__DIR__ . '/data/assignments.json'), true) ?? [];
if (!empty($assignments_json)) {
    foreach ($assignments_json as $assignment) {
        try {
            $id = (int)($assignment['id'] ?? 0);
            if ($id > 0) {
                // Check if exists
                $result = $conn->query("SELECT id FROM assignments WHERE id = $id LIMIT 1");
                if (!$result || $result->num_rows == 0) {
                    $title = $conn->real_escape_string($assignment['title'] ?? '');
                    $description = $conn->real_escape_string($assignment['description'] ?? '');
                    $deadline = $conn->real_escape_string($assignment['deadline'] ?? '');
                    $max_score = (int)($assignment['max_score'] ?? 100);
                    $rubric = $conn->real_escape_string($assignment['rubric'] ?? '');
                    $course_code = $conn->real_escape_string($assignment['course_code'] ?? '');
                    
                    // Handle files field (JSON)
                    $files_json = 'NULL';
                    if (isset($assignment['files']) && is_array($assignment['files'])) {
                        $files_json = "'" . $conn->real_escape_string(json_encode($assignment['files'])) . "'";
                    }
                    
                    $conn->query("INSERT INTO assignments (id, title, description, deadline, max_score, rubric, course_code, files) VALUES ($id, '$title', '$description', '$deadline', $max_score, '$rubric', '$course_code', $files_json)");
                    echo "<p>✓ Migrated assignment: $title (ID: $id)</p>";
                    $migrated++;
                } else {
                    echo "<p>⏭ Assignment already exists: ID $id (skipped)</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating assignment: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No assignments to migrate.</p>";
}

// 4. Migrate Notes
echo "<h2>4. Migrating Notes...</h2>";
$notes_json = json_decode(file_get_contents(__DIR__ . '/data/notes.json'), true) ?? [];
if (!empty($notes_json)) {
    foreach ($notes_json as $note) {
        try {
            $id = (int)($note['id'] ?? 0);
            if ($id > 0) {
                $result = $conn->query("SELECT id FROM notes WHERE id = $id LIMIT 1");
                if (!$result || $result->num_rows == 0) {
                    $title = $conn->real_escape_string($note['title'] ?? '');
                    $topic = $conn->real_escape_string($note['topic'] ?? '');
                    $content = $conn->real_escape_string($note['content'] ?? '');
                    $course_code = $conn->real_escape_string($note['course_code'] ?? '');
                    $attachment_name = $conn->real_escape_string($note['attachment_name'] ?? '');
                    $attachment_stored = $conn->real_escape_string($note['attachment_stored'] ?? '');
                    $file_size = (int)($note['file_size'] ?? 0);
                    $version = (int)($note['version'] ?? 1);
                    $downloads = (int)($note['downloads'] ?? 0);
                    $status = $conn->real_escape_string($note['status'] ?? 'shared');
                    
                    $conn->query("INSERT INTO notes (id, title, topic, content, course_code, attachment_name, attachment_stored, file_size, version, downloads, status) VALUES ($id, '$title', '$topic', '$content', '$course_code', '$attachment_name', '$attachment_stored', $file_size, $version, $downloads, '$status')");
                    echo "<p>✓ Migrated note: $title (ID: $id)</p>";
                    $migrated++;
                } else {
                    echo "<p>⏭ Note already exists: ID $id (skipped)</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating note: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No notes to migrate.</p>";
}

// 5. Migrate Submissions
echo "<h2>5. Migrating Submissions...</h2>";
$submissions_json = json_decode(file_get_contents(__DIR__ . '/data/submissions.json'), true) ?? [];
if (!empty($submissions_json)) {
    foreach ($submissions_json as $submission) {
        try {
            $id = (int)($submission['id'] ?? 0);
            if ($id > 0) {
                $result = $conn->query("SELECT id FROM submissions WHERE id = $id LIMIT 1");
                if (!$result || $result->num_rows == 0) {
                    $assignment_id = (int)($submission['assignment_id'] ?? 0);
                    $student_name = $conn->real_escape_string($submission['student_name'] ?? '');
                    $file_name = $conn->real_escape_string($submission['file_name'] ?? '');
                    $stored_name = $conn->real_escape_string($submission['stored_name'] ?? '');
                    $score = isset($submission['score']) ? ((int)$submission['score']) : 'NULL';
                    $feedback = $conn->real_escape_string($submission['feedback'] ?? '');
                    $submitted_at = $conn->real_escape_string($submission['submitted_at'] ?? date('Y-m-d H:i:s'));
                    
                    $score_sql = $score === 'NULL' ? 'NULL' : $score;
                    $conn->query("INSERT INTO submissions (id, assignment_id, student_name, file_name, stored_name, score, feedback, submitted_at) VALUES ($id, $assignment_id, '$student_name', '$file_name', '$stored_name', $score_sql, '$feedback', '$submitted_at')");
                    echo "<p>✓ Migrated submission: ID $id</p>";
                    $migrated++;
                } else {
                    echo "<p>⏭ Submission already exists: ID $id (skipped)</p>";
                }
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating submission: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No submissions to migrate.</p>";
}

// 6. Migrate Content Statuses
echo "<h2>6. Migrating Content Statuses...</h2>";
$content_statuses_json = json_decode(file_get_contents(__DIR__ . '/data/content_statuses.json'), true) ?? [];
if (!empty($content_statuses_json)) {
    foreach ($content_statuses_json as $key => $status_data) {
        try {
            $key_escaped = $conn->real_escape_string($key);
            $result = $conn->query("SELECT content_key FROM content_statuses WHERE content_key = '$key_escaped' LIMIT 1");
            if (!$result || $result->num_rows == 0) {
                $status = $conn->real_escape_string($status_data['status'] ?? 'pending');
                $flag_reason = $conn->real_escape_string($status_data['flag_reason'] ?? '');
                
                $conn->query("INSERT INTO content_statuses (content_key, status, flag_reason) VALUES ('$key_escaped', '$status', '$flag_reason')");
                echo "<p>✓ Migrated content status: $key</p>";
                $migrated++;
            } else {
                echo "<p>⏭ Content status already exists: $key (skipped)</p>";
            }
        } catch (Exception $e) {
            echo "<p style='color:red;'>✕ Error migrating content status: " . $e->getMessage() . "</p>";
            $errors++;
        }
    }
} else {
    echo "<p>No content statuses to migrate.</p>";
}

echo "<hr>";
echo "<h2>Migration Complete!</h2>";
echo "<p><strong>Items migrated:</strong> $migrated</p>";
echo "<p><strong>Errors:</strong> $errors</p>";
if ($errors == 0) {
    echo "<p style='color:green; font-weight:bold;'>✓ Migration successful! You can now use the database.</p>";
} else {
    echo "<p style='color:orange;'>⚠ Migration completed with some errors. Please review above.</p>";
}
echo "<p><a href='index.php'>← Back to Home</a></p>";
?>

