<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in? Skip straight to the feed.
if (is_logged_in()) {
    header('Location: home.php');
    exit;
}

$error = '';

// directs to home if account exists 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT Email, HashedPassword FROM `user` WHERE Email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // password_verify compares the plaintext attempt against the bcrypt hash
    if ($user && password_verify($password, $user['HashedPassword'])) {
        $_SESSION['user_email'] = $user['Email'];
        $_SESSION['email']      = $user['Email'];
        header('Location: home.php');
        exit;
    } else {
        $error = 'Incorrect email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Log in</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card">
  <h2>Welcome Back!</h2>
  <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post">
    <label>Email</label>
    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
    <label>Password</label>
    <input type="password" name="password" required>
    <button type="submit" class="btn-block">Log In</button>
  </form>
  <p class="link">Don't have an account? <a href="register.php">Sign up</a></p>
</div>
</body>
</html>
