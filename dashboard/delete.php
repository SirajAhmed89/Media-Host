<?php
require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

// Check if file ID was provided
if (!isset($_POST['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'No file ID provided']));
}

$file_id = (int)$_POST['id'];
$user_id = $_SESSION['user_id'];

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Get file details and verify ownership
    $stmt = $pdo->prepare("
        SELECT file_name, file_size 
        FROM media 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$file_id, $user_id]);
    $file = $stmt->fetch();
    
    if (!$file) {
        $pdo->rollBack();
        http_response_code(404);
        die(json_encode(['error' => 'File not found or access denied']));
    }
    
    // Delete file from storage
    $file_path = __DIR__ . '/uploads/' . $file['file_name'];
    if (file_exists($file_path)) {
        if (!unlink($file_path)) {
            throw new Exception('Failed to delete file from storage');
        }
    }
    
    // Update user's storage usage
    $stmt = $pdo->prepare("
        UPDATE users 
        SET storage_used = GREATEST(0, storage_used - ?) 
        WHERE id = ?
    ");
    $stmt->execute([$file['file_size'], $user_id]);
    
    // Delete file record from database
    $stmt = $pdo->prepare("DELETE FROM media WHERE id = ? AND user_id = ?");
    $stmt->execute([$file_id, $user_id]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Delete error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['error' => 'Delete failed']));
} 