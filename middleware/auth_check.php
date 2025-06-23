<?php
session_start();
require_once __DIR__ . '/../config/db.php'; // Adjust path to match actual db.php location

if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];

    $stmt = getPDO()->prepare("SELECT * FROM users WHERE remember_token = ? AND token_expiry > NOW()");
    $stmt->execute([$token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Restore session from cookie
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
    } else {
        // Clear expired/invalid token
        setcookie('remember_token', '', time() - 3600, '/', '', true, true);
    }
}
