<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once '../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Ensure CSRF token exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user's storage usage
$stmt = $pdo->prepare("SELECT storage_used FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$storage = $stmt->fetch();
$storage_used = $storage['storage_used'] ?? 0;
$storage_limit = MAX_STORAGE_SIZE;
$storage_percentage = ($storage_used / $storage_limit) * 100;

// Get user's media files
$stmt = $pdo->prepare("
    SELECT id, file_name, file_type, file_size, description, upload_date, thumbnail 
    FROM media 
    WHERE user_id = ? 
    ORDER BY upload_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$media_files = $stmt->fetchAll();

function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - MediaHost</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <script src="../assets/js/fileUploader.js"></script>
</head>
<body>
    <div class="dashboard">
        <header class="dashboard-header">
            <div class="container">
                <div class="header-content">
                    <h1 id="topheading">My Media</h1>
                    <div>
                        <a href="../logout.php" id="logoutdiv">Logout</a>
                    </div>
                </div>
                <div>
                    <div id="storage-info">
                        <span>Storage Used</span>
                        <span><?php echo formatSize($storage_used) . ' / ' . formatSize($storage_limit); ?></span>
                    </div>
                    <div class="storage-meter">
                        <div class="storage-used" style="width: <?php echo min($storage_percentage, 100); ?>%"></div>
                    </div>
                </div>
            </div>
        </header>
        
        <section class="upload-section">
            <form id="upload-form" class="dropzone" action="upload.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            </form>
        </section>
        
        <section class="media-gallery">
            <div class="search-container">
                <input type="text" class="search-input" placeholder="Search your media..." id="search">
            </div>
            
            <?php if (empty($media_files)): ?>
                <div class="empty-state">
                    <h3>No media files yet</h3>
                    <p>Upload your first file to get started!</p>
                </div>
            <?php else: ?>
                <div class="media-grid">
                    <?php foreach ($media_files as $file): ?>
                        <div class="media-item" data-name="<?php echo htmlspecialchars($file['file_name']); ?>">
                            <div class="media-preview <?php echo $file['file_type']; ?>">
                                <?php if ($file['file_type'] === 'image'): ?>
                                    <img src="uploads/<?php echo htmlspecialchars($file['file_name']); ?>" 
                                         alt="<?php echo htmlspecialchars($file['file_name']); ?>"
                                         loading="lazy">
                                <?php else: ?>
                                    <div class="video-container">
                                        <video id="video-previewthis"
                                            src="uploads/<?php echo htmlspecialchars($file['file_name']); ?>"
                                            poster="uploads/<?php echo $file['thumbnail'] ? htmlspecialchars($file['thumbnail']) : '../assets/images/default_video_thumb.jpg'; ?>?v=<?php echo time(); ?>"
                                            preload="none"
                                            controls
                                            controlsList="nodownload"
                                            type="video/mp4"
                                            playsinline>
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="media-info">
                                <div class="media-name"><?php echo htmlspecialchars(pathinfo($file['file_name'], PATHINFO_FILENAME)); ?></div>
                                <div class="media-meta">
                                    <span><?php echo formatSize($file['file_size']); ?></span>
                                    <span><?php echo date('M j, Y', strtotime($file['upload_date'])); ?></span>
                                </div>
                            </div>
                            <div class="media-actions">
                                <a href="uploads/<?php echo htmlspecialchars($file['file_name']); ?>" 
                                   download="<?php echo htmlspecialchars($file['file_name']); ?>"
                                   class="download-button">Download</a>
                                <button class="delete-button" 
                                        onclick="deleteFile(<?php echo $file['id']; ?>)">Delete</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    
    <script>
        // Initialize FileUploader
        const uploader = new FileUploader({
            form: document.getElementById('upload-form'),
            url: 'upload.php',
            maxFileSize: <?php echo MAX_STORAGE_SIZE / (1024 * 1024); ?>,
            acceptedFiles: '.jpg,.jpeg,.png,.webp,.mp4,.mov',
            csrfToken: '<?php echo $_SESSION['csrf_token']; ?>',
            onComplete: function() {
                location.reload();
            },
            onError: function(file, message) {
                alert(message);
            }
        });
        
        // Search functionality
        document.getElementById('search').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            document.querySelectorAll('.media-item').forEach(item => {
                const fileName = item.dataset.name.toLowerCase();
                item.style.display = fileName.includes(searchTerm) ? 'block' : 'none';
            });
        });
        
        // Delete functionality
        function deleteFile(id) {
            if (confirm('Are you sure you want to delete this file?')) {
                fetch('delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-CSRF-TOKEN': '<?php echo $_SESSION['csrf_token']; ?>'
                    },
                    body: 'id=' + id
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.error);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to delete file. Please try again.');
                });
            }
        }

        // Helper function to format file size
        function formatSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            return `${size.toFixed(2)} ${units[unitIndex]}`;
        }
    </script>
</body>
</html> 