<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$currentEmail = current_user_email();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newEmail        = trim($_POST['new_email'] ?? '');
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if ($newEmail === '') {
        $newEmail = $currentEmail;
    }

    try {
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address.');
        }

        if ($newPassword !== '' || $confirmPassword !== '') {
            if ($newPassword === '') {
                throw new Exception('Please enter a new password.');
            }
            if ($confirmPassword === '') {
                throw new Exception('Please confirm your new password.');
            }
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match.');
            }
            if (strlen($newPassword) < 8) {
                throw new Exception('Password must be at least 8 characters long.');
            }
        }

        $updates = [];
        $params = [];
        $changed = [];

        if ($newEmail !== $currentEmail) {
            $check = $pdo->prepare('SELECT Email FROM `user` WHERE Email = ? LIMIT 1');
            $check->execute([$newEmail]);

            if ($check->fetch()) {
                throw new Exception('That email is already in use by another account.');
            }

            $updates[] = 'Email = ?';
            $params[] = $newEmail;
            $changed[] = 'Email';
        }

        if ($newPassword !== '') {
            $updates[] = 'HashedPassword = ?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            $changed[] = 'Password';
        }

        if (empty($updates)) {
            throw new Exception('No changes were made. Change your email or enter a new password.');
        }

        $params[] = $currentEmail;
        $sql = 'UPDATE `user` SET ' . implode(', ', $updates) . ' WHERE Email = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        if ($newEmail !== $currentEmail) {
            $_SESSION['user_email'] = $newEmail;
            $_SESSION['email']      = $newEmail;
            $currentEmail = $newEmail;
        }

        if (count($changed) === 1) {
            $success = $changed[0] . ' updated successfully!';
        } else {
            $success = 'Email and Password updated successfully!';
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Settings</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
    <?php include '../includes/nav.php'; ?>
    <h2>Security Settings</h2>

    <?php if ($error): ?>
        <p class="error" style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p class="success" style="color: green; font-size: 13px;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="post">
        <label>Email</label>
        <input type="email" name="new_email" value="<?= htmlspecialchars($currentEmail) ?>">

        <label>New Password</label>
        <input type="password" name="new_password" placeholder="Leave blank to keep current password">

        <label>Confirm New Password</label>
        <input type="password" name="confirm_password" placeholder="Confirm new password">

        <button type="submit" class="btn-block">Update Settings</button>
    </form>

    <p class="link"><a href="profile.php">Back to Profile</a></p>
</div>
</body>
</html>