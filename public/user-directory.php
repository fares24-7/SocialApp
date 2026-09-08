<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$currentEmail = current_user_email();
$message = '';

// Handle Add Friend Action directly inside this page
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $receiverEmail = trim($_POST['receiver_email'] ?? '');

    if (!empty($receiverEmail) && $currentEmail !== $receiverEmail) {
        // Check both directions — a row could already exist either way
        $checkStmt = $pdo->prepare(
            'SELECT * FROM `friends_with`
             WHERE (RequesterEmail = ? AND ReceiverEmail = ?)
                OR (RequesterEmail = ? AND ReceiverEmail = ?)'
        );
        $checkStmt->execute([$currentEmail, $receiverEmail, $receiverEmail, $currentEmail]);

        if (!$checkStmt->fetch()) {
            $insStmt = $pdo->prepare(
                "INSERT INTO `friends_with` (RequesterEmail, ReceiverEmail, Status) VALUES (?, ?, 'pending')"
            );
            $insStmt->execute([$currentEmail, $receiverEmail]);
            header('Location: user-directory.php?request=sent');
            exit;
        } else {
            $message = 'A request or friendship already exists with this user.';
        }
    }
}

if (isset($_GET['request']) && $_GET['request'] === 'sent') {
    $message = 'Friend request sent!';
}

// Fetch only users who have NO existing relationship with the current user.
// This excludes pending requests in either direction and accepted friendships,
// so a user cannot be offered the "Add Friend" button again.
$stmt = $pdo->prepare(
    'SELECT u.FirstName, u.LastName, u.Email
     FROM `user` u
     WHERE u.Email != ?
       AND NOT EXISTS (
           SELECT 1
           FROM `friends_with` f
           WHERE (f.RequesterEmail = ? AND f.ReceiverEmail = u.Email)
              OR (f.RequesterEmail = u.Email AND f.ReceiverEmail = ?)
       )
     ORDER BY u.FirstName ASC'
);
$stmt->execute([$currentEmail, $currentEmail, $currentEmail]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Discover - User Directory</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 580px; max-width: 100%;">
    <?php include '../includes/nav.php'; ?>

    <h2 style="text-align: center; margin-top: 15px;">Discover New People</h2>

    <?php if ($message): ?>
        <p style="text-align: center; color: #27ae60; font-size: 13px; font-weight: bold;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>

    <?php if (empty($users)): ?>
        <p style="text-align: center; color: #777; margin-top: 20px;">No new users to discover right now.</p>
    <?php else: ?>
        <div style="margin-top: 20px;">
            <?php foreach ($users as $u): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border: 1px solid #e0e0e0; border-radius: 6px; margin-bottom: 10px; background: #fff;">
                    <div>
                        <a href="user-profile.php?email=<?= urlencode($u['Email']) ?>" style="text-decoration:none; color:#2c3e50;">
                            <strong><?= htmlspecialchars(($u['FirstName'] ?? '') . ' ' . ($u['LastName'] ?? '')) ?></strong>
                        </a>
                        <br><small style="color: #777;"><?= htmlspecialchars($u['Email'] ?? '') ?></small>
                    </div>
                    <form method="post" style="margin: 0;">
                        <input type="hidden" name="receiver_email" value="<?= htmlspecialchars($u['Email']) ?>">
                        <button type="submit" style="padding: 6px 12px; font-size: 12px; background: #27ae60; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Add Friend</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>