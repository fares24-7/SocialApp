<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$email = current_user_email();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $birthDate = trim($_POST['birth_date'] ?? '');

    if (empty($firstName) || empty($lastName) || empty($birthDate)) {
        $error = 'All fields are required.';
    } else {
        $stmt = $pdo->prepare('UPDATE `user` SET FirstName = ?, LastName = ?, BirthDate = ? WHERE Email = ?');
        if ($stmt->execute([$firstName, $lastName, $birthDate, $email])) {
            $success = 'Profile updated successfully!';
        } else {
            $error = 'Failed to update profile.';
        }
    }
}

$stmt = $pdo->prepare('SELECT FirstName, LastName, BirthDate FROM `user` WHERE Email = ?');
$stmt->execute([$email]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Profile</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <?php include '../includes/nav.php'; ?>
  <h2>Edit Profile</h2>
  <?php if ($error): ?><p class="error" style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <?php if ($success): ?><p class="success" style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>
  <form method="post">
    <label>First Name</label>
    <input type="text" name="first_name" required value="<?= htmlspecialchars($user['FirstName'] ?? '') ?>">
    <label>Last Name</label>
    <input type="text" name="last_name" required value="<?= htmlspecialchars($user['LastName'] ?? '') ?>">
    <label>Birth Date</label>
    <input type="date" name="birth_date" required value="<?= htmlspecialchars($user['BirthDate'] ?? '') ?>">
    <button type="submit" class="btn-block">Save Changes</button>
  </form>
  <p class="link"><a href="profile.php">Back to Profile</a></p>
</div>
</body>
</html>