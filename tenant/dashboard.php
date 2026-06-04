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
if ($_SESSION['role'] !== 'tenant') {
    header('Location: ../login.php'); exit;
}

if (time() - $_SESSION['last_active'] > SESSION_TIMEOUT) {
    session_unset(); session_destroy();
    header('Location: ../login.php?reason=timeout'); exit;
}
$_SESSION['last_active'] = time();

require_once __DIR__ . '/../config/db.php';

$userId = $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare('
    SELECT t.*, p.title, p.address, p.rent_amount, p.status as property_status
    FROM tenants t
    JOIN properties p ON t.property_id = p.id
    WHERE t.user_id = ? AND t.status = "active"
    LIMIT 1
');
$stmt->execute([$userId]);
$tenantProperty = $stmt->fetch();

$stmt = $pdo->prepare('
    SELECT mr.*, p.title as property_title
    FROM maintenance_requests mr
    JOIN properties p ON mr.property_id = p.id
    WHERE mr.tenant_id = ?
    ORDER BY mr.created_at DESC
');
$stmt->execute([$userId]);
$myRequests = $stmt->fetchAll();

$properties = $pdo->query('SELECT * FROM properties WHERE status = "available" ORDER BY created_at DESC')->fetchAll();

$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title = trim(strip_tags($_POST['title'] ?? ''));
        $description = trim(strip_tags($_POST['description'] ?? ''));
        $priority = $_POST['priority'] ?? 'medium';
        $property_id = (int)($_POST['property_id'] ?? 0);

        if (empty($title)) $errors[] = 'Request title is required.';
        elseif (strlen($title) < 5) $errors[] = 'Title must be at least 5 characters.';
        if (empty($description)) $errors[] = 'Description is required.';
        elseif (strlen($description) < 10) $errors[] = 'Description must be at least 10 characters.';
        if ($property_id <= 0) $errors[] = 'Please select a property.';

        $allowedPriorities = ['low', 'medium', 'high'];
        if (!in_array($priority, $allowedPriorities)) $priority = 'medium';

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO maintenance_requests (tenant_id, property_id, title, description, priority) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$userId, $property_id, $title, $description, $priority]);
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                ->execute([$userId, 'SUBMIT_REQUEST', 'Submitted: ' . $title, $_SERVER['REMOTE_ADDR']]);
            $success = 'Maintenance request submitted successfully!';

            $stmt = $pdo->prepare('SELECT mr.*, p.title as property_title FROM maintenance_requests mr JOIN properties p ON mr.property_id = p.id WHERE mr.tenant_id = ? ORDER BY mr.created_at DESC');
            $stmt->execute([$userId]);
            $myRequests = $stmt->fetchAll();
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
    <title>Tenant Dashboard — IS351 Property Management</title>
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
        .content { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .welcome h2 { color: #1F4E79; font-size: 24px; margin-bottom: 4px; }
        .welcome p { color: #777; margin-bottom: 24px; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .section { background: #fff; border-radius: 10px; padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,.08); margin-bottom: 24px; }
        .section h3 { color: #1F4E79; margin-bottom: 16px; font-size: 18px;
            border-bottom: 2px solid #e8f0fe; padding-bottom: 8px; }
        .property-card { background: #e8f0fe; border-radius: 8px; padding: 16px; margin-bottom: 12px; }
        .property-card h4 { color: #1F4E79; margin-bottom: 8px; }
        .property-card p { color: #555; font-size: 14px; margin-bottom: 4px; }
        label { display: block; margin-bottom: 4px; font-size: 14px; color: #555; }
        input[type=text], textarea, select {
            width: 100%; padding: 10px; margin-bottom: 14px;
            border: 1px solid #ccc; border-radius: 6px; font-size: 14px; box-sizing: border-box; }
        input:focus, textarea:focus, select:focus { border-color: #2E75B6; outline: none; }
        textarea { height: 100px; resize: vertical; }
        button { width: 100%; padding: 11px; background: #2E75B6; color: #fff;
            border: none; border-radius: 6px; font-size: 15px; cursor: pointer; }
        button:hover { background: #1F4E79; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        th { background: #f8f9fa; color: #555; font-weight: bold; }
        tr:hover { background: #f8f9fa; }
        .status { padding: 3px 10px; border-radius: 12px; font-size: 12px; font-weight: bold; }
        .status-pending { background: #fff3e0; color: #e65100; }
        .status-completed { background: #e8f5e9; color: #2E7D32; }
        .status-in_progress { background: #e3f2fd; color: #1565c0; }
        .status-available { background: #e8f5e9; color: #2E7D32; }
        .errors { background: #fdecea; color: #c0392b; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .errors ul { padding-left: 18px; }
        .success { background: #e8f5e9; color: #2E7D32; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .no-property { text-align: center; color: #999; padding: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🏠 IS351 Property Management System</h1>
        <div class='header-right'>
            <span>Welcome, <?= $fullname ?></span>
            <span class='badge'>Tenant</span>
            <a href='../logout.php' class='logout'>Logout</a>
        </div>
    </div>

    <div class='content'>
        <div class='welcome'>
            <h2>Tenant Dashboard</h2>
            <p>View your property and submit maintenance requests</p>
        </div>

        <div class='grid'>
            <div class='section'>
                <h3>🏠 My Property</h3>
                <?php if ($tenantProperty): ?>
                    <div class='property-card'>
                        <h4><?= htmlspecialchars($tenantProperty['title']) ?></h4>
                        <p>📍 <?= htmlspecialchars($tenantProperty['address']) ?></p>
                        <p>💰 $<?= number_format($tenantProperty['rent_amount'], 2) ?>/month</p>
                        <p>📅 Lease: <?= $tenantProperty['lease_start'] ?> to <?= $tenantProperty['lease_end'] ?></p>
                        <p>Status: <span class='status status-<?= $tenantProperty['property_status'] ?>'><?= $tenantProperty['property_status'] ?></span></p>
                    </div>
                <?php else: ?>
                    <div class='no-property'>
                        <p>No property assigned yet.</p>
                        <p>Please contact the admin.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class='section'>
                <h3>🔧 Submit Maintenance Request</h3>

                <?php if (!empty($errors)): ?>
                    <div class='errors'>
                        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class='success'>✅ <?= htmlspecialchars($success) ?></div>
                <?php endif; ?>

                <form method='POST'>
                    <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>
                    <input type='hidden' name='submit_request' value='1'>

                    <label>Property</label>
                    <select name='property_id' required>
                        <option value=''>-- Select Property --</option>
                        <?php foreach ($properties as $prop): ?>
                            <option value='<?= $prop['id'] ?>'><?= htmlspecialchars($prop['title']) ?></option>
                        <?php endforeach; ?>
                        <?php if ($tenantProperty): ?>
                            <option value='<?= $tenantProperty['property_id'] ?>' selected>
                                <?= htmlspecialchars($tenantProperty['title']) ?> (My Property)
                            </option>
                        <?php endif; ?>
                    </select>

                    <label>Request Title</label>
                    <input type='text' name='title'
                        placeholder='e.g. Broken pipe in bathroom'
                        value='<?= htmlspecialchars($_POST['title'] ?? '') ?>' required>

                    <label>Description</label>
                    <textarea name='description' placeholder='Describe the issue...' required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                    <label>Priority</label>
                    <select name='priority'>
                        <option value='low'>Low</option>
                        <option value='medium' selected>Medium</option>
                        <option value='high'>High</option>
                    </select>

                    <button type='submit'>Submit Request</button>
                </form>
            </div>
        </div>

        <div class='section'>
            <h3>📋 My Maintenance Requests</h3>
            <table>
                <tr><th>ID</th><th>Property</th><th>Title</th><th>Priority</th><th>Status</th><th>Submitted</th></tr>
                <?php if (empty($myRequests)): ?>
                <tr><td colspan='6' style='text-align:center;color:#999'>No requests yet</td></tr>
                <?php else: ?>
                <?php foreach ($myRequests as $req): ?>
                <tr>
                    <td><?= $req['id'] ?></td>
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
            <h3>🏘️ Available Properties</h3>
            <table>
                <tr><th>Title</th><th>Address</th><th>Rent/Month</th><th>Status</th></tr>
                <?php foreach ($properties as $prop): ?>
                <tr>
                    <td><?= htmlspecialchars($prop['title']) ?></td>
                    <td><?= htmlspecialchars($prop['address']) ?></td>
                    <td>$<?= number_format($prop['rent_amount'], 2) ?></td>
                    <td><span class='status status-<?= $prop['status'] ?>'><?= $prop['status'] ?></span></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
</body>
</html>
