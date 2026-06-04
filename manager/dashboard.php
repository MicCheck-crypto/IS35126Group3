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
if ($_SESSION['role'] !== 'property_manager') {
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

$pendingRequests = $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status = "pending"')->fetchColumn();
$inProgressRequests = $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status = "in_progress"')->fetchColumn();
$completedRequests = $pdo->query('SELECT COUNT(*) FROM maintenance_requests WHERE status = "completed"')->fetchColumn();
$totalProperties = $pdo->query('SELECT COUNT(*) FROM properties')->fetchColumn();

$requests = $pdo->query('
    SELECT mr.*, u.full_name as tenant_name,
           p.title as property_title, p.address as property_address
    FROM maintenance_requests mr
    JOIN users u ON mr.tenant_id = u.id
    JOIN properties p ON mr.property_id = p.id
    ORDER BY mr.created_at DESC
')->fetchAll();

$properties = $pdo->query('SELECT * FROM properties ORDER BY created_at DESC')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request.';
    } else {
        $requestId = (int)$_POST['request_id'];
        $newStatus = $_POST['new_status'];
        $allowedStatuses = ['pending', 'in_progress', 'completed'];
        if (in_array($newStatus, $allowedStatuses)) {
            $stmt = $pdo->prepare('UPDATE maintenance_requests SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $requestId]);
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'UPDATE_REQUEST', 'Updated request #' . $requestId . ' to ' . $newStatus, $_SERVER['REMOTE_ADDR']]);
            header('Location: dashboard.php'); exit;
        }
    }
}

$fullname = htmlspecialchars($_SESSION['full_name']);
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Manager Dashboard — IS351 Property Management</title>
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
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-completed { background: #e8f5e9; color: #2E7D32; }
        .status-in_progress { background: #e3f2fd; color: #1565c0; }
        .status-available { background: #e8f5e9; color: #2E7D32; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
        .status-occupied { background: #fff3e0; color: #e65100; padding: 3px 10px; border-radius: 12px; font-size: 12px; }
        .priority-high { color: #c0392b; font-weight: bold; }
        .priority-medium { color: #e65100; }
        .priority-low { color: #2E7D32; }
        select { padding: 6px 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
        .update-btn { padding: 6px 12px; background: #2E75B6; color: #fff;
            border: none; border-radius: 4px; cursor: pointer; font-size: 13px; }
        .update-btn:hover { background: #1F4E79; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🏠 IS351 Property Management System</h1>
        <div class='header-right'>
            <span>Welcome, <?= $fullname ?></span>
            <span class='badge'>Property Manager</span>
            <a href='../logout.php' class='logout'>Logout</a>
        </div>
    </div>

    <div class='content'>
        <div class='welcome'>
            <h2>Property Manager Dashboard</h2>
            <p>Manage maintenance requests and monitor properties</p>
        </div>

        <div class='stats'>
            <div class='stat-card'>
                <div class='number'><?= $pendingRequests ?></div>
                <div class='label'>Pending Requests</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $inProgressRequests ?></div>
                <div class='label'>In Progress</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $completedRequests ?></div>
                <div class='label'>Completed</div>
            </div>
            <div class='stat-card'>
                <div class='number'><?= $totalProperties ?></div>
                <div class='label'>Total Properties</div>
            </div>
        </div>

        <div class='section'>
            <h3>🔧 Maintenance Requests</h3>
            <table>
                <tr>
                    <th>ID</th><th>Tenant</th><th>Property</th><th>Title</th>
                    <th>Priority</th><th>Status</th><th>Date</th><th>Update</th>
                </tr>
                <?php if (empty($requests)): ?>
                <tr><td colspan='8' style='text-align:center;color:#999'>No requests yet</td></tr>
                <?php else: ?>
                <?php foreach ($requests as $req): ?>
                <tr>
                    <td><?= $req['id'] ?></td>
                    <td><?= htmlspecialchars($req['tenant_name']) ?></td>
                    <td><?= htmlspecialchars($req['property_title']) ?></td>
                    <td><?= htmlspecialchars($req['title']) ?></td>
                    <td><span class='priority-<?= $req['priority'] ?>'><?= strtoupper($req['priority']) ?></span></td>
                    <td><span class='status status-<?= $req['status'] ?>'><?= $req['status'] ?></span></td>
                    <td><?= date('d M Y', strtotime($req['created_at'])) ?></td>
                    <td>
                        <form method='POST' style='display:flex;gap:6px;'>
                            <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>
                            <input type='hidden' name='request_id' value='<?= $req['id'] ?>'>
                            <input type='hidden' name='update_status' value='1'>
                            <select name='new_status'>
                                <option value='pending' <?= $req['status']==='pending'?'selected':'' ?>>Pending</option>
                                <option value='in_progress' <?= $req['status']==='in_progress'?'selected':'' ?>>In Progress</option>
                                <option value='completed' <?= $req['status']==='completed'?'selected':'' ?>>Completed</option>
                            </select>
                            <button type='submit' class='update-btn'>Update</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </table>
        </div>

        <div class='section'>
            <h3>🏠 Properties Overview</h3>
            <table>
                <tr><th>ID</th><th>Title</th><th>Address</th><th>Rent</th><th>Status</th></tr>
                <?php foreach ($properties as $property): ?>
                <tr>
                    <td><?= $property['id'] ?></td>
                    <td><?= htmlspecialchars($property['title']) ?></td>
                    <td><?= htmlspecialchars($property['address']) ?></td>
                    <td>$<?= number_format($property['rent_amount'], 2) ?></td>
                    <td><span class='status-<?= $property['status'] ?>'><?= $property['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
