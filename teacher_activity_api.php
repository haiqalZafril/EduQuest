<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require instructor login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Helper function to calculate time ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $currentTime = time();
    $diff = $currentTime - $timestamp;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' min ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}

// Load data
$assignments = eq_load_data('assignments');
$submissions = eq_load_data('submissions');
$notes = eq_load_data('notes');

// Generate recent activity from actual data
$activities = [];

// Add submission activities
foreach ($submissions as $sub) {
    if (isset($sub['submitted_at'])) {
        $assignment = null;
        foreach ($assignments as $ass) {
            if ((int)$ass['id'] === (int)$sub['assignment_id']) {
                $assignment = $ass;
                break;
            }
        }
        
        if ($assignment) {
            $courseCode = $assignment['course_code'] ?? 'CS ' . (100 + (int)$sub['assignment_id']);
            $activities[] = [
                'type' => ($sub['score'] !== null && $sub['score'] !== '') ? 'graded' : 'submission',
                'course' => $courseCode,
                'student' => $sub['student_name'] ?? 'Unknown Student',
                'timestamp' => strtotime($sub['submitted_at']),
                'datetime' => $sub['submitted_at']
            ];
        }
    }
}

// Add note upload activities
foreach ($notes as $note) {
    if (isset($note['created_at'])) {
        $activities[] = [
            'type' => 'note',
            'course' => $note['course_code'] ?? 'CS 101',
            'student' => null,
            'title' => $note['title'] ?? 'Note',
            'timestamp' => strtotime($note['created_at']),
            'datetime' => $note['created_at']
        ];
    }
}

// Files are independent and not tracked in activity feed

// Sort activities by timestamp (most recent first)
usort($activities, function($a, $b) {
    return $b['timestamp'] - $a['timestamp'];
});

// Take only the 10 most recent and format them
$recentActivity = [];
foreach (array_slice($activities, 0, 10) as $activity) {
    $recentActivity[] = [
        'type' => $activity['type'],
        'course' => $activity['course'],
        'student' => $activity['student'],
        'title' => $activity['title'] ?? null,
        'time' => timeAgo($activity['datetime']),
        'timestamp' => $activity['timestamp']
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'activities' => $recentActivity,
    'timestamp' => time()
]);
?>

