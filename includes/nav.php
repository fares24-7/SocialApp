<?php
$navEmail = $_SESSION['user_email'] ?? $_SESSION['email'] ?? '';
$badgeCount = 0;

if ($navEmail && isset($pdo)) {
    $badgeStmt = $pdo->prepare('SELECT COUNT(*) FROM `friends_with` WHERE ReceiverEmail = ? AND Status = "pending"');
    $badgeStmt->execute([$navEmail]);
    $badgeCount = (int)$badgeStmt->fetchColumn();
}

$activePage = basename($_SERVER['PHP_SELF']);
?>
<nav style="background: #2c3e50; padding: 10px 15px; border-radius: 6px; margin-bottom: 20px; display: flex; gap: 10px; align-items: center; justify-content: space-between;">
    <div style="display: flex; gap: 15px; align-items: center;">
        <a href="home.php" style="color: <?= $activePage === 'home.php' ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none; font-weight: bold;">Feed</a>
        <a href="user-directory.php" style="color: <?= $activePage === 'user-directory.php' ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none;">Discover</a>
        <a href="friends.php" style="color: <?= $activePage === 'friends.php' ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none;">Friends</a>
        <a href="friend-requests.php" style="color: <?= $activePage === 'friend-requests.php' ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none;">
            Requests <?php if ($badgeCount > 0): ?><span style="background: #e74c3c; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px;"><?= $badgeCount ?></span><?php endif; ?>
        </a>
        <a href="messenger.php" style="color: <?= in_array($activePage, ['messenger.php', 'chat-messages.php']) ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none;">Messenger</a>
    </div>
    <div>
        <a href="profile.php" style="color: <?= $activePage === 'profile.php' ? '#2ecc71' : '#ecf0f1' ?>; text-decoration: none; font-weight: bold;">My Profile</a>
    </div>
</nav>