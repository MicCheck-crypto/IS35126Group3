<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

define('SESSION_TIMEOUT', 1800);

if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    header('Location: ../login.php'); exit;
}
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../login.php'); exit;
}

if (time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
    session_unset(); session_destroy();
    header('Location: ../login.php?reason=timeout'); exit;
}
$_SESSION['last_active'] = time();

require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request.';
    } else {
        $deleteId = (int)$_POST['user_id'];
        if ($deleteId !== $_SESSION['user_id']) {
            $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$deleteId]);
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'DELETE_USER', 'Deleted user ID: ' . $deleteId, $_SERVER['REMOTE_ADDR']]);
        }
        header('Location: dashboard.php'); exit;
    }
}

$totalProperties = $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();
$totalTenants = $pdo->query('SELECT COUNT(*) FROM users WHERE role = "tenant"')->fetchColumn();
$pendingRequests = $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status = "pending"')->fetchColumn();
$availableProperties = $pdo->query('SELECT COUNT(*) FROM properties WHERE status = "available"')->fetchColumn();

$users = $pdo->query('SELECT id, username, email, role, full_name, is_active, created_at FROM users ORDER BY created_at DESC')->fetchAll();
$properties = $pdo->query('SELECT * FROM properties ORDER BY created_at DESC')->fetchAll();
$requests = $pdo->query('
    SELECT mr.*, u.full_name as tenant_name, p.title as property_title
    FROM maintenance_requests mr
    JOIN users u ON mr.tenant_id = u.id
    JOIN properties p ON mr.property_id = p.id
    ORDER BY mr.created_at DESC
')->fetchAll();
$logs = $pdo->query('
    SELECT al.*, u.username
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 20
')->fetchAll();

$fullname = htmlspecialchars($_SESSION['full_name']);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Admin Dashboard — IS351 Property Management</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; margin: 0; }
        .header { background: #1F4E79; color: #fff; padding: 16px 32px;
            display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .badge { background: #2E75B6; color: #fff; padding: 4px 12px;
            border-radius: 20px; font-size: 13px; }
        .logout { background: #c0392b; color: #fff; padding: 8px 16px;
            border-radius: 6px; text-decoration: none; font-size: 14px; }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .content { max-width: 1100px; margin: 30px auto; padding: 0 20px; }
        .welcome h2 { color: #1F4E79; font-size: 24px; margin-bottom: 4px; }
        .welcome p { color: #777; margin-bottom: 24px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 30px; }
        .stat-card { background: #fff; border-radius: 10px; padding: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); text-align: center; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #2E75B6; }
        .stat-card .label { color: #777; font-size: 13px; margin-top: 4px; }
        .section { background: #fff; border-radius: 10px; padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 24px; }
        .section h3 { color: #1F4E79; margin-bottom: 16px; font-size: 18px;
            border-bottom: 2px solid #e8f0fe; padding-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        th { background: #f8f9fa; color: #555; font-weight: bold; }
        tr:hover { background: #f8f9fa; }
        .status { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-available { background: #e8f5e9; color: #2E7D32; }
        .status-occupied { background: #fff3e0; color: #e65100; }
        .status-maintenance { background: #fce4ec; color: #c62828; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-completed { background: #e8f5e9; color: #2E7D32; }
        .status-in_progress { background: #e3f2fd; color: #1565c0; }
        .role-admin { background: #fce4ec; color: #c62828; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
        .role-property_manager { background: #e3f2fd; color: #1565c0; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
        .role-tenant { background: #e8f5e9; color: #2E7D32; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
        .add-btn { background: #2E75B6; color: #fff; padding: 8px 16px;
            border-radius: 6px; text-decoration: none; font-size: 14px; float: right; }
        .delete-btn { background: #c0392b; color: #fff; padding: 4px 10px;
            border-radius: 4px; border: none; cursor: pointer; font-size: 12px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🏠 IS351 Property Management System</h1>
        <div class='header-right'>
            <span>Welcome, <?= $fullname ?></span>
            <span class='badge'>Admin</span>
            <a href='../logout.php' class='logout'>Logout</a>
        </div>
    </div>

    <div class='content'>
        <div class='welcome'>
            <h2>Admin Dashboard</h2>
            <p>Manage properties, tenants, and maintenance requests</p>
        </div>

        <div class='stats'>
            <div class='stat-card'>
                <div class='number'><?= $totalProperties ?></div>
                <div class='label'>Total Properties</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $totalTenants ?></div>
                <div class='label'>Total Tenants</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $pendingRequests ?></div>
                <div class='label'>Pending Requests</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $availableProperties ?></div>
                <div class='label'>Available Properties</div>
            </div>
        </div>

        <div class='section'>
            <h3>👥 All Users
                <a href='add_property.php' class='add-btn'>+ Add Property</a>
            </h3>
            <table>
                <tr>
                    <th>ID</th><th>Full Name</th><th>Username</th>
                    <th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th>Action</th>
                </tr>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= $user['id'] ?></td>
                    <td><?= htmlspecialchars($user['full_name']) ?></td>
                    <td><?= htmlspecialchars($user['username']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><span class='role-<?= $user['role'] ?>'><?= $user['role'] ?></span></td>
                    <td><?= $user['is_active'] ? '✅ Active' : '❌ Inactive' ?></td>
                    <td><?= date('d M Y', strtotime($user['created_at'])) ?></td>
                    <td>
                        <?php if ($user['id'] !== $_SESSION['user_id']): ?>
                        <form method='POST' onsubmit="return confirm('Delete this user?')">
                            <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>
                            <input type='hidden' name='user_id' value='<?= $user['id'] ?>'>
                            <input type='hidden' name='delete_user' value='1'>
                            <button type='submit' class='delete-btn'>Delete</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class='section'>
            <h3>🏠 All Properties</h3>
            <table>
                <tr><th>ID</th><th>Title</th><th>Address</th><th>Rent</th><th>Status</th></tr>
                <?php foreach ($properties as $property): ?>
                <tr>
                    <td><?= $property['id'] ?></td>
                    <td><?= htmlspecialchars($property['title']) ?></td>
                    <td><?= htmlspecialchars($property['address']) ?></td>
                    <td>$<?= number_format($property['rent_amount'], 2) ?></td>
                    <td><span class='status status-<?= $property['status'] ?>'><?= $property['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>

        <div class='section'>
            <h3>🔧 Maintenance Requests</h3>
            <table>
                <tr><th>ID</th><th>Tenant</th><th>Property</th><th>Title</th><th>Priority</th><th>Status</th><th>Date</th></tr>
                <?php if (empty($requests)): ?>
                <tr><td colspan='7' style='text-align:center;color:#999'>No maintenance requests yet</td></tr>
                <?php else: ?>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= $req['id'] ?></td>
                    <td><?= htmlspecialchars($req['tenant_name']) ?></td>
                    <td><?= htmlspecialchars($req['property_title']) ?></td>
                    <td><?= htmlspecialchars($req['title']) ?></td>
                    <td><?= $req['priority'] ?></td>
                    <td><span class='status status-<?= $req['status'] ?>'><?= $req['status'] ?></span></td>
                    <td><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class='section'>
            <h3>📋 Recent Audit Logs</h3>
            <table>
                <tr><th>User</th><th>Action</th><th>Details</th><th>IP Address</th><th>Time</th></tr>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['username'] ?? 'System') ?></td>
                    <td><?= htmlspecialchars($log['action']) ?></td>
                    <td><?= htmlspecialchars($log['details']) ?></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                    <td><?= date('d M Y H:i', strtotime($log['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
