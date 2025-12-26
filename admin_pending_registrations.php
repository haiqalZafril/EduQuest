<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Show a helpful access denied message instead of silently redirecting
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access denied</title>';
    echo '<link rel="stylesheet" href="styles.css">';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;background:#f9fafb;color:#1f2937;padding:3rem} .card{max-width:720px;margin:2rem auto;background:white;border-radius:12px;padding:1.5rem;box-shadow:0 6px 18px rgba(0,0,0,0.06)} a{color:#667eea;text-decoration:none}</style>';
    echo '</head><body><div class="card"><h1>Access Denied</h1><p class="muted">You must be signed in as an administrator to access user management.</p><p><a href="role_selection.php">Return to role selection / sign in</a></p></div></body></html>';
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $email = $_POST['email'] ?? '';

    if ($action === 'approve') {
        if (eq_approve_pending_request($email)) {
            $message = "Approved $email.";
        } else {
            $error = "Could not approve request.";
        }
    } elseif ($action === 'reject') {
        eq_delete_pending_request($email);
        $message = "Rejected $email.";
    }
}

$pending = eq_get_pending_requests();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pending Registrations - Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background:#f5f7fa; color:#1f2937; }
        .container { max-width:1100px; margin:2rem auto; padding:1rem; }
        .card { background:white; border-radius:12px; padding:1.25rem; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
        .card h1 { font-size:1.25rem; margin-bottom:0.25rem; }
        .muted { color:#6b7280; font-size:0.95rem; margin-bottom:1rem; }
        table { width:100%; border-collapse:collapse; margin-top:1rem; }
        th, td { text-align:left; padding:0.75rem; border-bottom:1px solid #eef2f7; }
        th { color:#6b7280; font-size:0.9rem; }
        .btn { padding:0.5rem 0.75rem; border-radius:8px; border:none; cursor:pointer; font-weight:600; }
        .btn-approve { background:#22c55e; color:white; }
        .btn-reject { background:#ef4444; color:white; }
        .empty { padding:1rem; color:#6b7280; }
        .notice { padding:0.75rem; border-radius:8px; margin-bottom:0.75rem; }
        .notice.success { background:#ecfdf5; color:#065f46; }
        .notice.error { background:#fee2e2; color:#991b1b; }
        .back { margin-bottom:1rem; display:inline-block; color:#6b7280; text-decoration:none; }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="admin_dashboard.php">← Back to Dashboard</a>
        <div class="card">
            <h1>Pending Registration Requests</h1>
            <div class="muted">Approve or reject requests to create accounts.</div>

            <?php if (!empty($message)): ?>
                <div class="notice success"><?php echo eq_h($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="notice error"><?php echo eq_h($error); ?></div>
            <?php endif; ?>

            <?php if (empty($pending)): ?>
                <div class="empty">No pending registration requests.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Requested At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $pemail => $req): ?>
                            <tr>
                                <td><?php echo eq_h($req['name'] ?? ''); ?></td>
                                <td><?php echo eq_h($pemail); ?></td>
                                <td><?php echo eq_h($req['role'] ?? 'student'); ?></td>
                                <td><?php echo eq_h($req['created_at'] ?? ''); ?></td>
                                <td>
                                    <form method="post" style="display:inline-block; margin-right:0.5rem;">
                                        <input type="hidden" name="email" value="<?php echo eq_h($pemail); ?>">
                                        <input type="hidden" name="action" value="approve">
                                        <button class="btn btn-approve" type="submit">Approve</button>
                                    </form>
                                    <form method="post" style="display:inline-block;">
                                        <input type="hidden" name="email" value="<?php echo eq_h($pemail); ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button class="btn btn-reject" type="submit" onclick="return confirm('Reject this request?');">Reject</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>