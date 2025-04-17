<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Store the current URL to redirect back after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Set flash message
    $_SESSION['flash_message'] = "Please log in to access this page";
    $_SESSION['flash_type'] = "info";
    
    // Redirect to login page
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        // Set flash message
        $_SESSION['flash_message'] = "Invalid security token. Please try again.";
        $_SESSION['flash_type'] = "error";
        
        // Redirect back
        header('Location: ' . $_SERVER['HTTP_REFERER'] ?? SITE_URL);
        exit;
    }
}

// Function to check if user has required permissions
function checkPermission($permission) {
    // Get user's role from session
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Define permission hierarchy
    $permissions = [
        'admin' => ['manage_users', 'manage_content', 'view_stats', 'upload_media', 'delete_media'],
        'moderator' => ['manage_content', 'view_stats', 'upload_media', 'delete_media'],
        'user' => ['upload_media', 'delete_own_media']
    ];
    
    // Check if user's role has the required permission
    return in_array($permission, $permissions[$user_role] ?? []);
}

// Function to check if user owns a media item
function isMediaOwner($media_id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM media WHERE id = ?");
        $stmt->execute([$media_id]);
        $media = $stmt->fetch();
        
        return $media && $media['user_id'] === $_SESSION['user_id'];
    } catch (PDOException $e) {
        error_log("Error checking media ownership: " . $e->getMessage());
        return false;
    }
}

// Function to get user's storage limit
function getUserStorageLimit() {
    // Get user's role from session
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Define storage limits per role (in bytes)
    $storage_limits = [
        'admin' => 10 * 1024 * 1024 * 1024,     // 10GB
        'moderator' => 5 * 1024 * 1024 * 1024,  // 5GB
        'user' => 1 * 1024 * 1024 * 1024        // 1GB
    ];
    
    return $storage_limits[$user_role] ?? $storage_limits['user'];
}

// Function to check if user has enough storage space
function hasStorageSpace($file_size) {
    global $pdo;
    
    try {
        // Get user's current storage usage
        $stmt = $pdo->prepare("SELECT storage_used FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $storage_used = $stmt->fetchColumn();
        
        // Get user's storage limit
        $storage_limit = getUserStorageLimit();
        
        // Check if adding the new file would exceed the limit
        return ($storage_used + $file_size) <= $storage_limit;
    } catch (PDOException $e) {
        error_log("Error checking storage space: " . $e->getMessage());
        return false;
    }
}

// Update user's last activity timestamp
try {
    $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
} catch (PDOException $e) {
    error_log("Error updating last activity: " . $e->getMessage());
} 