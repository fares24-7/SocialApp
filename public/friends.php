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

// (unfriends)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_friend'])) {
    $friendEmail = trim($_POST['friend_email'] ?? '');

    if (!empty($friendEmail)) {
        $stmt = $pdo->prepare('DELETE FROM `friends_with` 
                               WHERE (RequesterEmail = ? AND ReceiverEmail = ?) 
                                  OR (RequesterEmail = ? AND ReceiverEmail = ?)');
        $stmt->execute([$currentEmail, $friendEmail, $friendEmail, $currentEmail]);
        flash_set('Friend removed successfully.');
    }
    header('Location: friends.php');
    exit;
}

$flash = flash_get();
if ($flash) {
    $success = $flash['message'];
}

// (view friends list) 
$sql = 'SELECT u.Email, u.FirstName, u.LastName 
        FROM `user` u
        INNER JOIN `friends_with` f 
           ON (f.RequesterEmail = u.Email AND f.ReceiverEmail = ?) 
           OR (f.ReceiverEmail = u.Email AND f.RequesterEmail = ?)
        WHERE f.Status = "accepted"';
$stmt = $pdo->prepare($sql);
$stmt->execute([$currentEmail, $currentEmail]);
$friends = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Friends</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 450px;">
    <?php include '../includes/nav.php'; ?>
    <h2>My Friends</h2>
    <?php if ($error): ?><p class="error" style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success" style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <?php if (empty($friends)): ?>
        <p>You have no friends on your list yet.</p>
    <?php else: ?>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($friends as $f): ?>
                <li style="padding: 10px 0; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <a href="user-profile.php?email=<?= urlencode($f['Email']) ?>" style="text-decoration:none; color:#2c3e50;">
                            <strong><?= htmlspecialchars($f['FirstName'] . ' ' . $f['LastName']) ?></strong>
                        </a><br>
                        <small><?= htmlspecialchars($f['Email']) ?></small>
                    </div>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="friend_email" value="<?= htmlspecialchars($f['Email']) ?>">
                        <button type="submit" name="remove_friend" style="padding: 5px 10px; font-size: 12px; background: #c0392b;">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <p class="link"><a href="profile.php">Back to Profile</a></p>
</div>
</body>
</html>