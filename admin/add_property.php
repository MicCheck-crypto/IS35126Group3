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

$errors = [];
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $title = trim(strip_tags($_POST['title'] ?? ''));
        $description = trim(strip_tags($_POST['description'] ?? ''));
        $address = trim(strip_tags($_POST['address'] ?? ''));
        $rent_amount = $_POST['rent_amount'] ?? '';
        $status = $_POST['status'] ?? 'available';

        if (empty($title)) $errors[] = 'Property title is required.';
        elseif (strlen($title) < 3) $errors[] = 'Title must be at least 3 characters.';
        if (empty($address)) $errors[] = 'Address is required.';
        if (empty($rent_amount) || !is_numeric($rent_amount) || $rent_amount <= 0)
            $errors[] = 'Please enter a valid rent amount.';

        $allowedStatuses = ['available', 'occupied', 'maintenance'];
        if (!in_array($status, $allowedStatuses)) $status = 'available';

        if (empty($errors)) {
            $stmt = $pdo->prepare('INSERT INTO properties (title, description, address, rent_amount, status, created_by) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$title, $description, $address, $rent_amount, $status, $_SESSION['user_id']]);
            $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                ->execute([$_SESSION['user_id'], 'ADD_PROPERTY', 'Added: ' . $title, $_SERVER['REMOTE_ADDR']]);
            $success = 'Property added successfully!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Add Property — IS351 Property Management</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; margin: 0; }
        .header { background: #1F4E79; color: #fff; padding: 16px 32px;
            display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .back-btn { background: #fff; color: #1F4E79; padding: 8px 16px;
            border-radius: 6px; text-decoration: none; font-size: 14px; font-weight: bold; }
        .logout { background: #c0392b; color: #fff; padding: 8px 16px;
            border-radius: 6px; text-decoration: none; font-size: 14px; }
        .content { max-width: 600px; margin: 40px auto; padding: 0 20px; }
        .card { background: #fff; border-radius: 10px; padding: 40px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); }
        .card h2 { color: #1F4E79; margin-bottom: 24px; font-size: 22px; }
        label { display: block; margin-bottom: 4px; font-size: 14px; color: #555; }
        input[type=text], input[type=number], textarea, select {
            width: 100%; padding: 10px; margin-bottom: 16px;
            border: 1px solid #ccc; border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        input:focus, textarea:focus, select:focus { border-color: #2E75B6; outline: none; }
        textarea { height: 100px; resize: vertical; }
        button { width: 100%; padding: 11px; background: #2E75B6; color: #fff;
            border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        button:hover { background: #1F4E79; }
        .errors { background: #fdecea; color: #c0392b; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .errors ul { padding-left: 18px; }
        .success { background: #e8f5e9; color: #2E7D32; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; text-align: center; }
    </style>
</head>
<body>
    <div class='header'>
        <h1>🏠 IS351 Property Management System</h1>
        <div class='header-right'>
            <a href='dashboard.php' class='back-btn'>← Back</a>
            <a href='../logout.php' class='logout'>Logout</a>
        </div>
    </div>

    <div class='content'>
        <div class='card'>
            <h2>➕ Add New Property</h2>

            <?php if (!empty($errors)): ?>
                <div class='errors'>
                    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class='success'>
                    ✅ <?= htmlspecialchars($success) ?>
                    <br><br><a href='dashboard.php'>Back to Dashboard</a>
                </div>
            <?php endif; ?>

            <form method='POST'>
                <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>

                <label>Property Title</label>
                <input type='text' name='title'
                    value='<?= htmlspecialchars($_POST['title'] ?? '') ?>'
                    placeholder='e.g. Sunset Apartment 2B' required>

                <label>Description</label>
                <textarea name='description' placeholder='Describe the property...'><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                <label>Address</label>
                <input type='text' name='address'
                    value='<?= htmlspecialchars($_POST['address'] ?? '') ?>'
                    placeholder='e.g. 123 Main Street, Suva' required>

                <label>Monthly Rent (FJD)</label>
                <input type='number' name='rent_amount'
                    value='<?= htmlspecialchars($_POST['rent_amount'] ?? '') ?>'
                    placeholder='e.g. 850' min='1' step='0.01' required>

                <label>Status</label>
                <select name='status'>
                    <option value='available'>Available</option>
                    <option value='occupied'>Occupied</option>
                    <option value='maintenance'>Under Maintenance</option>
                </select>

                <button type='submit'>Add Property</button>
            </form>
        </div>
    </div>
</body>
</html>
