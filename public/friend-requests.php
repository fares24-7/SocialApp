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

// Handle Request Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $targetEmail = trim($_POST['target_email'] ?? '');
    $action      = $_POST['action'] ?? '';

    if (!empty($targetEmail)) {
        if ($action === 'accept') {
            $stmt = $pdo->prepare('UPDATE `friends_with` SET Status = "accepted" WHERE RequesterEmail = ? AND ReceiverEmail = ?');
            $stmt->execute([$targetEmail, $currentEmail]);
            flash_set('Friend request accepted!');
        } elseif ($action === 'decline') {
            $stmt = $pdo->prepare('DELETE FROM `friends_with` WHERE RequesterEmail = ? AND ReceiverEmail = ?');
            $stmt->execute([$targetEmail, $currentEmail]);
            flash_set('Friend request declined.');
        } elseif ($action === 'cancel') {
            $stmt = $pdo->prepare('DELETE FROM `friends_with` WHERE RequesterEmail = ? AND ReceiverEmail = ? AND Status = "pending"');
            $stmt->execute([$currentEmail, $targetEmail]);
            flash_set('Friend request canceled.');
        }
    }
    header('Location: friend-requests.php');
    exit;
}

$flash = flash_get();
if ($flash) {
    $success = $flash['message'];
}

// Query Incoming Requests (Received)
$incomingStmt = $pdo->prepare('SELECT u.Email, u.FirstName, u.LastName 
                               FROM `friends_with` f
                               JOIN `user` u ON f.RequesterEmail = u.Email
                               WHERE f.ReceiverEmail = ? AND f.Status = "pending"');
$incomingStmt->execute([$currentEmail]);
$incomingRequests = $incomingStmt->fetchAll();

// Query Outgoing Requests (Sent)
$outgoingStmt = $pdo->prepare('SELECT u.Email, u.FirstName, u.LastName 
                               FROM `friends_with` f
                               JOIN `user` u ON f.ReceiverEmail = u.Email
                               WHERE f.RequesterEmail = ? AND f.Status = "pending"');
$outgoingStmt->execute([$currentEmail]);
$outgoingRequests = $outgoingStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Friend Requests</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 480px;">
    <?php include '../includes/nav.php'; ?>
    <h2>Friend Requests</h2>
    
    <?php if ($error): ?><p class="error" style="color:red; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success" style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <h3 style="margin-top: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Received Requests</h3>
    <?php if (empty($incomingRequests)): ?>
        <p style="font-size: 13px; color: #777;">No incoming friend requests.</p>
    <?php else: ?>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($incomingRequests as $r): ?>
                <li style="padding: 10px 0; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <a href="user-profile.php?email=<?= urlencode($r['Email']) ?>" style="text-decoration:none; color:#2c3e50;">
                            <strong><?= htmlspecialchars($r['FirstName'] . ' ' . $r['LastName']) ?></strong>
                        </a><br>
                        <small><?= htmlspecialchars($r['Email']) ?></small>
                    </div>
                    <form method="post" style="margin:0; display:flex; gap: 5px;">
                        <input type="hidden" name="target_email" value="<?= htmlspecialchars($r['Email']) ?>">
                        <button type="submit" name="action" value="accept" style="padding: 5px 10px; font-size: 12px; background: #27ae60;">Accept</button>
                        <button type="submit" name="action" value="decline" style="padding: 5px 10px; font-size: 12px; background: #c0392b;">Decline</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3 style="margin-top: 25px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Sent Requests</h3>
    <?php if (empty($outgoingRequests)): ?>
        <p style="font-size: 13px; color: #777;">No pending requests sent.</p>
    <?php else: ?>
        <ul style="list-style: none; padding: 0;">
            <?php foreach ($outgoingRequests as $s): ?>
                <li style="padding: 10px 0; border-bottom: 1px solid #ddd; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <a href="user-profile.php?email=<?= urlencode($s['Email']) ?>" style="text-decoration:none; color:#2c3e50;">
                            <strong><?= htmlspecialchars($s['FirstName'] . ' ' . $s['LastName']) ?></strong>
                        </a><br>
                        <small><?= htmlspecialchars($s['Email']) ?></small>
                    </div>
                    <form method="post" style="margin:0;">
                        <input type="hidden" name="target_email" value="<?= htmlspecialchars($s['Email']) ?>">
                        <button type="submit" name="action" value="cancel" style="padding: 5px 10px; font-size: 12px; background: #e67e22;">Cancel Request</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p class="link" style="margin-top: 20px;"><a href="profile.php">Back to Profile</a></p>
</div>
</body>
</html>