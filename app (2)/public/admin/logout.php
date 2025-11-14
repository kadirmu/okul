<?php
require_once '../../config/config.php';

if (Helper::isLoggedIn()) {
    // Logout log
    Helper::createLog($db, $_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);

    // Session'ı güncelle
    $stmt = $db->prepare("UPDATE user_sessions SET logged_out_at = NOW() WHERE session_token = ?");
    $stmt->execute([session_id()]);
}

session_destroy();
Helper::redirect('/public/login.php');