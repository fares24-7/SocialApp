<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return !empty($_SESSION['email']) || !empty($_SESSION['user_email']);
}

function current_user_email() {
    return $_SESSION['email'] ?? $_SESSION['user_email'] ?? '';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit();
    }
}

// One-time message stored in session, read (and cleared) by the next page load —
// lets a page redirect after a successful POST without losing the confirmation text.
function flash_set($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

function flash_get() {
    if (!isset($_SESSION['flash_message'])) {
        return null;
    }
    $flash = ['message' => $_SESSION['flash_message'], 'type' => $_SESSION['flash_type'] ?? 'success'];
    unset($_SESSION['flash_message'], $_SESSION['flash_type']);
    return $flash;
}