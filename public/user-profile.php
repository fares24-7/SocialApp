<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$currentEmail = current_user_email();
$targetEmail  = trim($_GET['email'] ?? '');

if (empty($targetEmail)) {
    header('Location: home.php');
    exit;
}

if ($targetEmail === $currentEmail) {
    header('Location: profile.php');
    exit;
}

// Fetch Target User Profile
$stmt = $pdo->prepare('SELECT Email, FirstName, LastName, BirthDate FROM `user` WHERE Email = ?');
$stmt->execute([$targetEmail]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    die('User not found.');
}

// Handle Relationship Action Buttons
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_request') {
        // Do not create another request if a pending request or friendship
        // already exists in either direction.
        $checkStmt = $pdo->prepare(
            'SELECT RequesterEmail, ReceiverEmail, Status
             FROM `friends_with`
             WHERE (RequesterEmail = ? AND ReceiverEmail = ?)
                OR (RequesterEmail = ? AND ReceiverEmail = ?)'
        );
        $checkStmt->execute([$currentEmail, $targetEmail, $targetEmail, $currentEmail]);
        $existing = $checkStmt->fetch();

        if ($existing) {
            $success = $existing['Status'] === 'accepted'
                ? 'You are already friends with this user.'
                : 'A friend request already exists with this user.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO `friends_with` (RequesterEmail, ReceiverEmail, Status) VALUES (?, ?, 'pending')");
            $stmt->execute([$currentEmail, $targetEmail]);
            $success = 'Friend request sent!';
        }
    } elseif ($action === 'accept') {
        $stmt = $pdo->prepare('UPDATE `friends_with` SET Status = "accepted" WHERE RequesterEmail = ? AND ReceiverEmail = ?');
        $stmt->execute([$targetEmail, $currentEmail]);
        $success = 'Friend request accepted!';
    } elseif ($action === 'unfriend' || $action === 'cancel' || $action === 'decline') {
        $stmt = $pdo->prepare('DELETE FROM `friends_with` WHERE (RequesterEmail = ? AND ReceiverEmail = ?) OR (RequesterEmail = ? AND ReceiverEmail = ?)');
        $stmt->execute([$currentEmail, $targetEmail, $targetEmail, $currentEmail]);
        $success = 'Relationship updated.';
    }
}

// Determine Current Relationship Status
$relStmt = $pdo->prepare('SELECT RequesterEmail, ReceiverEmail, Status FROM `friends_with` 
                           WHERE (RequesterEmail = ? AND ReceiverEmail = ?) 
                              OR (RequesterEmail = ? AND ReceiverEmail = ?)');
$relStmt->execute([$currentEmail, $targetEmail, $targetEmail, $currentEmail]);
$relationship = $relStmt->fetch();

// Fetch Public Posts from this User
$postsStmt = $pdo->prepare('SELECT Post_Content AS PostContent, P_UploadTime AS PUploadTime FROM `text_posts` WHERE UserEmail = ? ORDER BY P_UploadTime DESC');
$postsStmt->execute([$targetEmail]);
$userPosts = $postsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($profileUser['FirstName']) ?>'s Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="card" style="width: 480px;">
    <?php include '../includes/nav.php'; ?>

    <h2><?= htmlspecialchars($profileUser['FirstName'] . ' ' . $profileUser['LastName']) ?></h2>
    <p><strong>Email:</strong> <?= htmlspecialchars($profileUser['Email']) ?></p>
    <p><strong>Birth Date:</strong> <?= htmlspecialchars($profileUser['BirthDate']) ?></p>

    <?php if ($success): ?><p class="success" style="color:green; font-size:13px;"><?= htmlspecialchars($success) ?></p><?php endif; ?>

    <div style="margin: 15px 0; padding: 12px; background: #f8f9fa; border-radius: 6px; border: 1px solid #e9ecef;">
        <?php if (!$relationship): ?>
            <form method="post" style="margin:0;">
                <button type="submit" name="action" value="send_request" style="width:100%; background:#27ae60;">+ Add Friend</button>
            </form>
        <?php elseif ($relationship['Status'] === 'pending' && $relationship['RequesterEmail'] === $currentEmail): ?>
            <form method="post" style="margin:0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:13px; color:#e67e22;">Friend Request Pending</span>
                <button type="submit" name="action" value="cancel" style="padding: 4px 8px; font-size:12px; background:#c0392b;">Cancel Request</button>
            </form>
        <?php elseif ($relationship['Status'] === 'pending' && $relationship['ReceiverEmail'] === $currentEmail): ?>
            <form method="post" style="margin:0; display:flex; gap: 10px;">
                <button type="submit" name="action" value="accept" style="flex:1; background:#27ae60;">Accept Request</button>
                <button type="submit" name="action" value="decline" style="flex:1; background:#c0392b;">Decline</button>
            </form>
        <?php elseif ($relationship['Status'] === 'accepted'): ?>
            <form method="post" style="margin:0; display:flex; justify-content:space-between; align-items:center;">
                <span style="font-size:13px; color:#27ae60; font-weight:bold;">✓ Friends</span>
                <button type="submit" name="action" value="unfriend" style="padding: 4px 8px; font-size:12px; background:#c0392b;">Unfriend</button>
            </form>
        <?php endif; ?>
    </div>

    <h3>Posts</h3>
    <?php if (empty($userPosts)): ?>
        <p><small>This user has not posted anything yet.</small></p>
    <?php else: ?>
        <?php foreach ($userPosts as $p): ?>
            <div style="padding: 10px; border-bottom: 1px solid #eee;">
                <p style="margin: 0 0 5px 0; font-size: 13px;"><?= nl2br(htmlspecialchars($p['PostContent'])) ?></p>
                <small style="color: #999; font-size: 10px;"><?= htmlspecialchars($p['PUploadTime']) ?></small>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>