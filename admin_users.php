<?php
session_start();
require_once __DIR__ . '/data_store.php';

// Require admin login
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Access denied</title>';
    echo '<link rel="stylesheet" href="styles.css">';
    echo '<style>body{font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,\'Helvetica Neue\',Arial,sans-serif;background:#f9fafb;color:#1f2937;padding:3rem} .card{max-width:720px;margin:2rem auto;background:white;border-radius:12px;padding:1.5rem;box-shadow:0 6px 18px rgba(0,0,0,0.06)} a{color:#667eea;text-decoration:none}</style>';
    echo '</head><body><div class="card"><h1>Access Denied</h1><p class="muted">You must be signed in as an administrator to access user management.</p><p><a href="role_selection.php">Return to role selection / sign in</a></p></div></body></html>';
    exit;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete') {
        $username = $_POST['username'] ?? '';
        if (!empty($username) && $username !== $_SESSION['username']) {
            eq_delete_user($username);
            $message = "User '$username' has been deleted successfully.";
        } else {
            $error = "Cannot delete your own account or invalid username.";
        }
    } elseif ($action === 'update') {
        $username = $_POST['username'] ?? '';
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        $role = $_POST['role'] ?? 'student';
        $academic = $_POST['academic'] ?? '';
        
        if (!empty($username)) {
            $user = eq_get_user($username);
            if ($user) {
                $userData = [
                    'password' => $user['password'], // Keep existing password
                    'role' => $role,
                    'name' => $name,
                    'email' => $email,
                    'academic' => $academic,
                    'avatar' => $user['avatar'] ?? ''
                ];
                eq_save_user($username, $userData);
                $message = "User '$username' has been updated successfully.";
            } else {
                $error = "User not found.";
            }
        }
    }
}

// Load all users
$users = eq_get_users();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
            background:#f5f7fa; 
            color:#1f2937; 
        }
        .container { 
            max-width:1400px; 
            margin:2rem auto; 
            padding:1rem; 
        }
        .card { 
            background:white; 
            border-radius:12px; 
            padding:1.5rem; 
            box-shadow:0 1px 3px rgba(0,0,0,0.08); 
            margin-bottom:1.5rem;
        }
        .card h1 { 
            font-size:1.5rem; 
            margin-bottom:0.25rem; 
        }
        .muted { 
            color:#6b7280; 
            font-size:0.95rem; 
            margin-bottom:1rem; 
        }
        table { 
            width:100%; 
            border-collapse:collapse; 
            margin-top:1rem; 
        }
        th, td { 
            text-align:left; 
            padding:0.75rem; 
            border-bottom:1px solid #eef2f7; 
        }
        th { 
            color:#6b7280; 
            font-size:0.9rem; 
            font-weight:600;
        }
        .btn { 
            padding:0.5rem 0.75rem; 
            border-radius:8px; 
            border:none; 
            cursor:pointer; 
            font-weight:500; 
            font-size:0.85rem;
            margin-right:0.5rem;
            transition: all 0.2s;
        }
        .btn-edit { 
            background:#3b82f6; 
            color:white; 
        }
        .btn-edit:hover {
            background:#2563eb;
        }
        .btn-delete { 
            background:#ef4444; 
            color:white; 
        }
        .btn-delete:hover {
            background:#dc2626;
        }
        .btn-cancel { 
            background:#6b7280; 
            color:white; 
        }
        .btn-cancel:hover {
            background:#4b5563;
        }
        .btn-save { 
            background:#22c55e; 
            color:white; 
        }
        .btn-save:hover {
            background:#16a34a;
        }
        .empty { 
            padding:1rem; 
            color:#6b7280; 
            text-align:center;
        }
        .notice { 
            padding:0.75rem 1rem; 
            border-radius:8px; 
            margin-bottom:1rem; 
        }
        .notice.success { 
            background:#ecfdf5; 
            color:#065f46; 
            border:1px solid #a7f3d0;
        }
        .notice.error { 
            background:#fee2e2; 
            color:#991b1b; 
            border:1px solid #fecaca;
        }
        .back { 
            margin-bottom:1rem; 
            display:inline-block; 
            color:#6b7280; 
            text-decoration:none; 
            font-weight:500;
        }
        .back:hover {
            color:#1f2937;
        }
        .role-badge {
            display:inline-block;
            padding:0.25rem 0.75rem;
            border-radius:6px;
            font-size:0.85rem;
            font-weight:600;
        }
        .role-badge.admin {
            background:#fee2e2;
            color:#991b1b;
        }
        .role-badge.teacher {
            background:#dbeafe;
            color:#1e40af;
        }
        .role-badge.student {
            background:#dcfce7;
            color:#166534;
        }
        .modal {
            display:none;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            z-index:1000;
            align-items:center;
            justify-content:center;
        }
        .modal.active {
            display:flex;
        }
        .modal-content {
            background:white;
            border-radius:12px;
            padding:2rem;
            max-width:500px;
            width:90%;
            box-shadow:0 20px 25px -5px rgba(0,0,0,0.1);
        }
        .modal-title {
            font-size:1.25rem;
            font-weight:600;
            margin-bottom:1.5rem;
        }
        .form-group {
            margin-bottom:1rem;
        }
        .form-group label {
            display:block;
            font-weight:500;
            margin-bottom:0.5rem;
            color:#374151;
        }
        .form-group input,
        .form-group select {
            width:100%;
            padding:0.5rem;
            border:1px solid #d1d5db;
            border-radius:6px;
            font-size:0.95rem;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline:none;
            border-color:#3b82f6;
        }
        .modal-actions {
            display:flex;
            gap:0.5rem;
            justify-content:flex-end;
            margin-top:1.5rem;
        }
        .stats-row {
            display:grid;
            grid-template-columns:repeat(auto-fit, minmax(200px, 1fr));
            gap:1rem;
            margin-bottom:1.5rem;
        }
        .stat-box {
            background:linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color:white;
            padding:1.5rem;
            border-radius:12px;
            box-shadow:0 4px 6px rgba(0,0,0,0.1);
        }
        .stat-box.green {
            background:linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }
        .stat-box.blue {
            background:linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        .stat-box.orange {
            background:linear-gradient(135deg, #f59e0b 0%, #ea580c 100%);
        }
        .stat-value {
            font-size:2rem;
            font-weight:700;
            margin-bottom:0.25rem;
        }
        .stat-label {
            font-size:0.9rem;
            opacity:0.9;
        }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="admin_dashboard.php">← Back to Dashboard</a>
        
        <?php if (!empty($message)): ?>
            <div class="notice success"><?php echo eq_h($message); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="notice error"><?php echo eq_h($error); ?></div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="stat-box">
                <div class="stat-value"><?php echo count($users); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-box green">
                <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'student')); ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-box blue">
                <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'teacher')); ?></div>
                <div class="stat-label">Teachers</div>
            </div>
            <div class="stat-box orange">
                <div class="stat-value"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></div>
                <div class="stat-label">Administrators</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <h1>All Users</h1>
            <div class="muted">View, edit, and manage user accounts.</div>

            <?php if (empty($users)): ?>
                <div class="empty">No users found.</div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Academic Info</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $username => $user): ?>
                            <tr>
                                <td><strong><?php echo eq_h($username); ?></strong></td>
                                <td><?php echo eq_h($user['name'] ?? ''); ?></td>
                                <td><?php echo eq_h($user['email'] ?? ''); ?></td>
                                <td>
                                    <span class="role-badge <?php echo eq_h($user['role']); ?>">
                                        <?php echo eq_h(ucfirst($user['role'])); ?>
                                    </span>
                                </td>
                                <td><?php echo eq_h($user['academic'] ?? ''); ?></td>
                                <td><?php echo eq_h($user['created_at'] ?? ''); ?></td>
                                <td>
                                    <button class="btn btn-edit" onclick="openEditModal('<?php echo eq_h($username); ?>', '<?php echo eq_h($user['name'] ?? ''); ?>', '<?php echo eq_h($user['email'] ?? ''); ?>', '<?php echo eq_h($user['role']); ?>', '<?php echo eq_h($user['academic'] ?? ''); ?>')">
                                        Edit
                                    </button>
                                    <?php if ($username !== $_SESSION['username']): ?>
                                        <button class="btn btn-delete" onclick="confirmDelete('<?php echo eq_h($username); ?>')">
                                            Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Edit User</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="username" id="edit_username">
                
                <div class="form-group">
                    <label for="edit_name">Name</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Role</label>
                    <select id="edit_role" name="role" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_academic">Academic Info</label>
                    <input type="text" id="edit_academic" name="academic" placeholder="e.g., Computer Science, Year 2">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" class="btn btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2 class="modal-title">Confirm Delete</h2>
            <p>Are you sure you want to delete user <strong id="delete_username_display"></strong>? This action cannot be undone.</p>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="username" id="delete_username">
                
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-delete">Delete User</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(username, name, email, role, academic) {
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_academic').value = academic;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        function confirmDelete(username) {
            document.getElementById('delete_username').value = username;
            document.getElementById('delete_username_display').textContent = username;
            document.getElementById('deleteModal').classList.add('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
