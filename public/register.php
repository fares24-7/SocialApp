<?php
require_once '../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email     = trim($_POST['email'] ?? '');
    $password  = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $birthDate = $_POST['birth_date'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }
    if ($firstName === '' || $lastName === '') {
        $errors[] = 'First and last name are required.';
    }
    if ($birthDate === '') {
        $errors[] = 'Birth date is required.';
    }

    if (empty($errors)) {
        $check = $pdo->prepare('SELECT Email FROM `user` WHERE Email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO `user` (Email, HashedPassword, FirstName, LastName, BirthDate)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$email, $hashed, $firstName, $lastName, $birthDate]);

        $_SESSION['user_email'] = $email;
        $_SESSION['email']      = $email;
        header('Location: home.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Create account</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>Create Account</h2>
  <?php foreach ($errors as $e): ?>
    <p class="error" style="color:red; font-size:13px;"><?= htmlspecialchars($e) ?></p>
  <?php endforeach; ?>
  <form method="post">
    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Password</label>
    <input type="password" name="password" required>
    <label>First Name</label>
    <input type="text" name="first_name" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
    <label>Last Name</label>
    <input type="text" name="last_name" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
    <label>Birth Date</label>
    <input type="date" name="birth_date" required value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>">
    <button type="submit" class="btn-block">Sign Up</button>
  </form>
  <p class="link">Already have an account? <a href="login.php">Log in</a></p>
</div>
</body>
</html>