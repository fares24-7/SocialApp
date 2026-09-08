<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();

$currentEmail = current_user_email();
$error = '';
$roomId = isset($_GET['room']) ? (int)$_GET['room'] : 0;

function areFriends(PDO $pdo, string $a, string $b): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM `friends_with`
                           WHERE Status = "accepted"
                             AND ((RequesterEmail = ? AND ReceiverEmail = ?)
                               OR (RequesterEmail = ? AND ReceiverEmail = ?))
                           LIMIT 1');
    $stmt->execute([$a, $b, $b, $a]);
    return (bool)$stmt->fetchColumn();
}

function findOrCreateRoom(PDO $pdo, string $currentEmail, string $friendEmail): int {
    if ($currentEmail === $friendEmail) {
        throw new RuntimeException('You cannot start a conversation with yourself.');
    }
    if (!areFriends($pdo, $currentEmail, $friendEmail)) {
        throw new RuntimeException('You can only message your friends.');
    }

    if (strcmp($currentEmail, $friendEmail) < 0) {
        $email1 = $currentEmail;
        $email2 = $friendEmail;
    } else {
        $email1 = $friendEmail;
        $email2 = $currentEmail;
    }

    $stmt = $pdo->prepare('SELECT ChatRoomID FROM `chatroom` WHERE UserEmail1 = ? AND UserEmail2 = ? LIMIT 1');
    $stmt->execute([$email1, $email2]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        return (int)$existing;
    }

    $stmt = $pdo->prepare('INSERT INTO `chatroom` (UserEmail1, UserEmail2) VALUES (?, ?)');
    $stmt->execute([$email1, $email2]);
    return (int)$pdo->lastInsertId();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'start_chat') {
    $friendEmail = trim($_POST['friend_email'] ?? '');
    try {
        $roomId = findOrCreateRoom($pdo, $currentEmail, $friendEmail);
        header('Location: messenger.php?room=' . $roomId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send_message') {
    $postedRoomId = (int)($_POST['room_id'] ?? 0);
    $content = trim($_POST['message_content'] ?? '');

    $accessStmt = $pdo->prepare('SELECT ChatRoomID FROM `chatroom`
                                 WHERE ChatRoomID = ? AND (UserEmail1 = ? OR UserEmail2 = ?)
                                 LIMIT 1');
    $accessStmt->execute([$postedRoomId, $currentEmail, $currentEmail]);

    if (!$accessStmt->fetchColumn()) {
        $error = 'Conversation not found.';
    } elseif ($content === '') {
        $error = 'Message cannot be empty.';
    } else {
        $stmt = $pdo->prepare('INSERT INTO `message` (UserEmail, ChatRoomID, Message_Content) VALUES (?, ?, ?)');
        $stmt->execute([$currentEmail, $postedRoomId, $content]);
        header('Location: messenger.php?room=' . $postedRoomId);
        exit;
    }
}

$roomsStmt = $pdo->prepare("SELECT c.ChatRoomID,
           CASE WHEN c.UserEmail1 = ? THEN c.UserEmail2 ELSE c.UserEmail1 END AS OtherEmail,
           u.FirstName, u.LastName,
           (SELECT m.Message_Content FROM `message` m WHERE m.ChatRoomID = c.ChatRoomID ORDER BY m.MessageID DESC LIMIT 1) AS LastMessage,
           (SELECT m.Time_Sent FROM `message` m WHERE m.ChatRoomID = c.ChatRoomID ORDER BY m.MessageID DESC LIMIT 1) AS LastTime
    FROM `chatroom` c
    JOIN `user` u ON u.Email = CASE WHEN c.UserEmail1 = ? THEN c.UserEmail2 ELSE c.UserEmail1 END
    WHERE c.UserEmail1 = ? OR c.UserEmail2 = ?
    ORDER BY LastTime DESC, c.ChatRoomID DESC");
$roomsStmt->execute([$currentEmail, $currentEmail, $currentEmail, $currentEmail]);
$rooms = $roomsStmt->fetchAll(PDO::FETCH_ASSOC);

$friendsStmt = $pdo->prepare("SELECT u.Email, u.FirstName, u.LastName
    FROM `user` u
    INNER JOIN `friends_with` f
      ON (f.RequesterEmail = u.Email AND f.ReceiverEmail = ?)
      OR (f.ReceiverEmail = u.Email AND f.RequesterEmail = ?)
    WHERE f.Status = \"accepted\"
    ORDER BY u.FirstName, u.LastName");
$friendsStmt->execute([$currentEmail, $currentEmail]);
$friends = $friendsStmt->fetchAll(PDO::FETCH_ASSOC);

// Do not show friends who already have a chat room with the current user
// in the "Start a conversation" section. They are already available in
// the Conversations list above, even when the room has no messages yet.
$existingConversationEmails = [];
foreach ($rooms as $room) {
    $existingConversationEmails[$room['OtherEmail']] = true;
}
$friendsWithoutConversation = array_values(array_filter($friends, function ($friend) use ($existingConversationEmails) {
    return !isset($existingConversationEmails[$friend['Email']]);
}));

$selectedRoom = null;
$selectedOther = null;
$selectedMessages = [];

if ($roomId > 0) {
    $roomStmt = $pdo->prepare("SELECT c.ChatRoomID,
               c.UserEmail1, c.UserEmail2,
               u.Email AS OtherEmail, u.FirstName, u.LastName
        FROM `chatroom` c
        JOIN `user` u ON u.Email = CASE WHEN c.UserEmail1 = ? THEN c.UserEmail2 ELSE c.UserEmail1 END
        WHERE c.ChatRoomID = ? AND (c.UserEmail1 = ? OR c.UserEmail2 = ?)
        LIMIT 1");
    $roomStmt->execute([$currentEmail, $roomId, $currentEmail, $currentEmail]);
    $selectedRoom = $roomStmt->fetch(PDO::FETCH_ASSOC);

    if (!$selectedRoom) {
        $error = 'Conversation not found.';
        $roomId = 0;
    } else {
        $selectedOther = $selectedRoom;
        $msgStmt = $pdo->prepare("SELECT m.MessageID, m.UserEmail, m.Message_Content, m.Time_Sent,
                   u.FirstName, u.LastName
            FROM `message` m
            JOIN `user` u ON u.Email = m.UserEmail
            WHERE m.ChatRoomID = ?
            ORDER BY m.MessageID ASC");
        $msgStmt->execute([$roomId]);
        $selectedMessages = $msgStmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messenger</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .messenger-layout { display:grid; grid-template-columns: 280px minmax(0, 1fr); gap:18px; min-height:650px; }
        .conversation-list { border:1px solid #ddd; border-radius:6px; padding:10px; background:#fafafa; }
        .conversation-item { display:block; padding:9px; border-radius:5px; text-decoration:none; color:#2c3e50; margin-bottom:5px; }
        .conversation-item:hover, .conversation-item.active { background:#eaf2f8; }
        .conversation-item small { color:#777; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:3px; }
        .chat-panel { border:1px solid #ddd; border-radius:6px; display:flex; flex-direction:column; min-width:0; min-height:650px; overflow:hidden; }
        .chat-header { padding:12px 15px; border-bottom:1px solid #ddd; font-weight:bold; }
        .messages { flex:1 1 auto; min-height:520px; max-height:none; overflow-y:auto; padding:18px; background:#f7f8fa; }
        .message-row { display:flex; margin-bottom:9px; }
        .message-row.mine { justify-content:flex-end; }
        .message-bubble { max-width:75%; padding:8px 11px; border-radius:12px; background:#e5e7eb; color:#222; word-break:break-word; }
        .message-row.mine .message-bubble { background:#3498db; color:#fff; }
        .message-time { display:block; font-size:10px; opacity:.7; margin-top:3px; }
        .message-form { display:flex; flex-direction:row; align-items:center; gap:10px; width:100%; padding:14px; border-top:1px solid #ddd; background:#fff; box-sizing:border-box; }
        .message-form input[type="text"] { flex:1 1 0%; width:100%; min-width:0; height:46px; padding:10px 12px; border:1px solid #ccc; border-radius:6px; font-size:14px; box-sizing:border-box; }
        .message-form button { flex:0 0 90px; width:90px; height:46px; padding:10px 14px; background:#2980b9; box-sizing:border-box; }
        .message-form button:disabled { opacity:.65; cursor:wait; }
        .new-chat { border-top:1px solid #ddd; margin-top:12px; padding-top:10px; }
        .friend-chat-form { margin:0 0 5px; }
        .friend-chat-form button { width:100%; padding:6px; background:#27ae60; font-size:12px; }
        @media (max-width:700px) { .messenger-layout { grid-template-columns:1fr; min-height:0; } .conversation-list { min-height:0; } .chat-panel { min-height:520px; } .messages { min-height:380px; } .message-form { gap:7px; padding:10px; } .message-form button { flex-basis:78px; width:78px; } }
    </style>
</head>
<body>
<div class="card" style="width:760px; max-width:100%;">
    <?php include '../includes/nav.php'; ?>
    <h2>Messenger</h2>
    <?php if ($error): ?><p style="color:#c0392b; font-size:13px;"><?= htmlspecialchars($error) ?></p><?php endif; ?>

    <div class="messenger-layout">
        <aside class="conversation-list">
            <strong>Conversations</strong>
            <div style="margin-top:8px;">
                <?php if (empty($rooms)): ?>
                    <p style="font-size:12px; color:#777;">No conversations yet.</p>
                <?php else: ?>
                    <?php foreach ($rooms as $room): ?>
                        <a class="conversation-item <?= $roomId === (int)$room['ChatRoomID'] ? 'active' : '' ?>" href="messenger.php?room=<?= (int)$room['ChatRoomID'] ?>">
                            <strong><?= htmlspecialchars($room['FirstName'] . ' ' . $room['LastName']) ?></strong>
                            <small><?= htmlspecialchars($room['LastMessage'] ?? 'No messages yet') ?></small>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="new-chat">
                <strong style="font-size:13px;">Start a conversation</strong>
                <?php if (empty($friends)): ?>
                    <p style="font-size:12px; color:#777;">You need friends first.</p>
                <?php elseif (empty($friendsWithoutConversation)): ?>
                    <p style="font-size:12px; color:#777;">All your friends already have conversations. Select one from the list above.</p>
                <?php else: ?>
                    <div style="margin-top:7px;">
                        <?php foreach ($friendsWithoutConversation as $friend): ?>
                            <form method="post" class="friend-chat-form">
                                <input type="hidden" name="action" value="start_chat">
                                <input type="hidden" name="friend_email" value="<?= htmlspecialchars($friend['Email']) ?>">
                                <button type="submit"><?= htmlspecialchars($friend['FirstName'] . ' ' . $friend['LastName']) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </aside>

        <section class="chat-panel">
            <?php if (!$selectedOther): ?>
                <div style="padding:40px 20px; text-align:center; color:#777;">
                    Select a conversation or start a new one with a friend.
                </div>
            <?php else: ?>
                <div class="chat-header">
                    <?= htmlspecialchars($selectedOther['FirstName'] . ' ' . $selectedOther['LastName']) ?>
                    <small style="font-weight:normal; color:#777;">(<?= htmlspecialchars($selectedOther['OtherEmail']) ?>)</small>
                </div>

                <div id="messages" class="messages">
                    <?php foreach ($selectedMessages as $message): ?>
                        <div class="message-row <?= $message['UserEmail'] === $currentEmail ? 'mine' : '' ?>">
                            <div class="message-bubble">
                                <?= nl2br(htmlspecialchars($message['Message_Content'])) ?>
                                <span class="message-time"><?= htmlspecialchars($message['Time_Sent']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" class="message-form" onsubmit="return prepareMessage(this);">
                    <input type="hidden" name="action" value="send_message">
                    <input type="hidden" name="room_id" value="<?= $roomId ?>">
                    <input id="message-input" class="message-text-input" type="text" name="message_content" maxlength="5000" placeholder="Write a message..." autocomplete="off" required>
                    <button id="send-button" class="message-send-button" type="submit">Send</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>

<?php if ($selectedOther): ?>
<script>
const roomId = <?= (int)$roomId ?>;
const currentEmail = <?= json_encode($currentEmail) ?>;
const messagesBox = document.getElementById('messages');
const messageInput = document.getElementById('message-input');
const sendButton = document.getElementById('send-button');
let firstLoad = true;

function renderMessages(messages) {
    const wasNearBottom = messagesBox.scrollHeight - messagesBox.scrollTop - messagesBox.clientHeight < 80;
    messagesBox.innerHTML = '';
    messages.forEach(function (message) {
        const row = document.createElement('div');
        row.className = 'message-row' + (message.UserEmail === currentEmail ? ' mine' : '');
        const bubble = document.createElement('div');
        bubble.className = 'message-bubble';
        bubble.appendChild(document.createTextNode(message.Message_Content));
        const time = document.createElement('span');
        time.className = 'message-time';
        time.textContent = message.Time_Sent;
        bubble.appendChild(time);
        row.appendChild(bubble);
        messagesBox.appendChild(row);
    });
    if (firstLoad || wasNearBottom) messagesBox.scrollTop = messagesBox.scrollHeight;
    firstLoad = false;
}

async function refreshMessages() {
    try {
        const response = await fetch('chat-messages.php?room=' + encodeURIComponent(roomId), { cache: 'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (data.ok) renderMessages(data.messages);
    } catch (e) {}
}

function prepareMessage() {
    if (!messageInput.value.trim()) return false;
    sendButton.disabled = true;
    sendButton.textContent = 'Sending...';
    return true;
}

refreshMessages();
setInterval(refreshMessages, 5000);
</script>
<?php endif; ?>
</body>
</html>
