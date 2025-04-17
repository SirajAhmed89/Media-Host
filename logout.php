<?php
require_once 'config.php';

// Clear session data
$_SESSION = array();

// Delete the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Delete remember me cookie if it exists
if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', time() - 3600, '/');
    
    // Delete token from database if it exists
    try {
        $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE token = ?");
        $stmt->execute([$_COOKIE['remember_me']]);
    } catch (PDOException $e) {
        // Log error but continue with logout
        error_log("Failed to delete auth token: " . $e->getMessage());
    }
}

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit; 