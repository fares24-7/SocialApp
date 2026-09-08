<?php
require_once '../config/db.php';
require_once '../includes/session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_login();
header('Content-Type: application/json; charset=utf-8');

$currentEmail = current_user_email();
$roomId = (int)($_GET['room'] ?? 0);

$roomStmt = $pdo->prepare('SELECT ChatRoomID FROM `chatroom` WHERE ChatRoomID = ? AND (UserEmail1 = ? OR UserEmail2 = ?) LIMIT 1');
$roomStmt->execute([$roomId, $currentEmail, $currentEmail]);
if (!$roomStmt->fetchColumn()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'messages' => []]);
    exit;
}

$stmt = $pdo->prepare("SELECT m.MessageID, m.UserEmail, m.Message_Content, m.Time_Sent,
           u.FirstName, u.LastName
    FROM `message` m
    JOIN `user` u ON u.Email = m.UserEmail
    WHERE m.ChatRoomID = ?
    ORDER BY m.MessageID ASC");
$stmt->execute([$roomId]);

echo json_encode(['ok' => true, 'messages' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
