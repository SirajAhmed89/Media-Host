<?php
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log function
function logError($message) {
    error_log(date('Y-m-d H:i:s') . " - " . $message . "\n", 3, __DIR__ . '/../logs/upload_error.log');
}

// Increase limits for large video files
ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 600);
ini_set('max_input_time', 600);
ini_set('post_max_size', '2048M');
ini_set('upload_max_filesize', '2048M');

// Session and CSRF checks
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_SESSION['user_id'])) {
    logError("Unauthorized access attempt");
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    logError("Invalid CSRF token");
    http_response_code(403);
    die(json_encode(['error' => 'Invalid CSRF token']));
}

// Check file upload
if (empty($_FILES['file'])) {
    logError("No file uploaded");
    die(json_encode(['error' => 'No file uploaded']));
}

// Create necessary directories with proper permissions
$upload_dir = __DIR__ . '/uploads';
if (!file_exists($upload_dir)) {
    if (!mkdir($upload_dir, 0755, true)) {
        logError("Failed to create upload directory: $upload_dir");
        die(json_encode(['error' => 'Failed to create upload directory']));
    }
}

// Ensure directory is writable
if (!is_writable($upload_dir)) {
    logError("Upload directory is not writable: $upload_dir");
    die(json_encode(['error' => 'Upload directory is not writable']));
}

$response = ['success' => true, 'files' => []];

// Handle single/multiple file uploads
$uploads = is_array($_FILES['file']['name']) ? $_FILES['file'] : [
    'name' => [$_FILES['file']['name']],
    'type' => [$_FILES['file']['type']],
    'tmp_name' => [$_FILES['file']['tmp_name']],
    'error' => [$_FILES['file']['error']],
    'size' => [$_FILES['file']['size']]
];

// Process each file
foreach ($uploads['name'] as $i => $name) {
    $file = [
        'name' => $uploads['name'][$i],
        'type' => $uploads['type'][$i],
        'tmp_name' => $uploads['tmp_name'][$i],
        'error' => $uploads['error'][$i],
        'size' => $uploads['size'][$i]
    ];

    logError("Processing file: " . $file['name'] . ", Size: " . $file['size'] . ", Type: " . $file['type']);

    // Check upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = match($file['error']) {
            UPLOAD_ERR_INI_SIZE => 'File exceeds PHP upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE in form',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
            default => 'Unknown upload error'
        };
        logError("Upload error for {$file['name']}: $error_message");
        die(json_encode(['error' => $error_message]));
    }

    try {
        $user_id = $_SESSION['user_id'];

        // Check if file exists and is uploaded
        if (!file_exists($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            throw new Exception("Invalid upload attempt");
        }
        
        // Validate file type
        $mime_type = mime_content_type($file['tmp_name']);
        $allowed_types = array_merge(ALLOWED_IMAGE_TYPES, ALLOWED_VIDEO_TYPES);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception("Invalid file type: $mime_type. Allowed types: " . implode(', ', $allowed_types));
        }

        // Check file size
        if ($file['size'] > MAX_STORAGE_SIZE) {
            throw new Exception("File too large: {$file['name']} (max size: " . formatSize(MAX_STORAGE_SIZE) . ")");
        }

        // Check storage usage
        $stmt = $pdo->prepare("SELECT storage_used FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $storage = $stmt->fetch();
        $storage_used = $storage['storage_used'] ?? 0;
        
        if ($storage_used + $file['size'] > MAX_STORAGE_SIZE) {
            throw new Exception('Storage limit exceeded. Please free up some space.');
        }

        // Generate unique filename
        $original_name = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $new_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name) . '_' . uniqid() . '.' . $extension;
        $upload_path = $upload_dir . '/' . $new_filename;
        
        // Move file with error checking
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            $move_error = error_get_last();
            logError("Failed to move file: " . ($move_error['message'] ?? 'Unknown error'));
            throw new Exception('Failed to save file. Please try again.');
        }

        // Set proper permissions
        chmod($upload_path, 0644);
        
        // Handle video thumbnail
        $thumbnail_filename = null;
        if (strpos($mime_type, 'video/') === 0) {
            // Ensure default thumbnail exists first
            $default_thumb_dir = __DIR__ . '/../assets/images';
            $default_thumb_path = $default_thumb_dir . '/default_video_thumb.jpg';
            
            if (!file_exists($default_thumb_path)) {
                if (!file_exists($default_thumb_dir)) {
                    mkdir($default_thumb_dir, 0755, true);
                }
                
                // Create a simple default thumbnail
                $def_width = 640;
                $def_height = 360;
                $def_image = imagecreatetruecolor($def_width, $def_height);
                $def_bg_color = imagecolorallocate($def_image, 40, 40, 40);
                $def_text_color = imagecolorallocate($def_image, 255, 255, 255);
                
                imagefill($def_image, 0, 0, $def_bg_color);
                imagestring($def_image, 5, $def_width/2 - 50, $def_height/2 - 10, "Video", $def_text_color);
                
                imagejpeg($def_image, $default_thumb_path, 90);
                imagedestroy($def_image);
                chmod($default_thumb_path, 0644);
            }

            $thumbnail_filename = preg_replace('/[^a-zA-Z0-9_-]/', '', $original_name) . '_thumb_' . uniqid() . '.jpg';
            $thumbnail_path = $upload_dir . '/' . $thumbnail_filename;
            
            try {
                // Create a custom thumbnail using GD
                $width = 640;
                $height = 360;
                
                $image = imagecreatetruecolor($width, $height);
                if ($image === false) {
                    throw new Exception("Failed to create GD image");
                }
                
                // Create a dark gray background
                $bg_color = imagecolorallocate($image, 40, 40, 40);
                imagefill($image, 0, 0, $bg_color);
                
                // Add play button overlay
                $play_color = imagecolorallocate($image, 255, 255, 255);
                $center_x = $width / 2;
                $center_y = $height / 2;
                $triangle_size = 50;
                
                // Draw play triangle
                $points = [
                    $center_x - $triangle_size/2, $center_y - $triangle_size/2,
                    $center_x + $triangle_size/2, $center_y,
                    $center_x - $triangle_size/2, $center_y + $triangle_size/2
                ];
                imagefilledpolygon($image, $points, 3, $play_color);
                
                // Add video title text
                $font_size = 3;
                $text_color = imagecolorallocate($image, 255, 255, 255);
                $text = pathinfo($file['name'], PATHINFO_FILENAME);
                $text = strlen($text) > 30 ? substr($text, 0, 27) . '...' : $text;
                
                $char_width = imagefontwidth($font_size);
                $text_width = $char_width * strlen($text);
                $text_x = ($width - $text_width) / 2;
                
                imagestring($image, $font_size, $text_x, $height - 30, $text, $text_color);
                
                if (!imagejpeg($image, $thumbnail_path, 90)) {
                    throw new Exception("Failed to save thumbnail");
                }
                
                imagedestroy($image);
                chmod($thumbnail_path, 0644);
                
                logError("Generated custom video thumbnail: $thumbnail_filename");
                
            } catch (Exception $e) {
                logError("Error generating thumbnail: " . $e->getMessage());
                // Copy the default thumbnail
                if (!copy($default_thumb_path, $thumbnail_path)) {
                    logError("Failed to copy default thumbnail, using direct path");
                    $thumbnail_filename = 'default_video_thumb.jpg';
                } else {
                    // Keep the unique thumbnail filename if copy succeeded
                    chmod($thumbnail_path, 0644);
                }
                logError("Using thumbnail: $thumbnail_filename");
            }
        }
        
        // Database transaction
        $pdo->beginTransaction();
        
        // Update storage usage
        $stmt = $pdo->prepare("UPDATE users SET storage_used = storage_used + ? WHERE id = ?");
        $stmt->execute([$file['size'], $user_id]);
        
        // Insert media record
        $stmt = $pdo->prepare("
            INSERT INTO media (user_id, file_name, file_type, file_size, thumbnail, upload_date)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $file_type = strpos($mime_type, 'image/') === 0 ? 'image' : 'video';
        $stmt->execute([$user_id, $new_filename, $file_type, $file['size'], $thumbnail_filename]);
        
        $pdo->commit();
        
        logError("Successfully processed file: $new_filename");
        
        // Add to response
        $response['files'][] = [
            'name' => $new_filename,
            'size' => $file['size'],
            'type' => $file_type,
            'thumbnail' => $thumbnail_filename
        ];
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Cleanup files
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }
        if (isset($thumbnail_path) && file_exists($thumbnail_path)) {
            unlink($thumbnail_path);
        }
        
        logError("Error processing {$file['name']}: " . $e->getMessage());
        die(json_encode(['error' => $e->getMessage()]));
    }
}

// Helper function
function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Return success response
echo json_encode($response);