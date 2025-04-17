<?php
// Cache Control Headers
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

// Database Configuration
define('DB_HOST', 'localhost'); // Hostinger MySQL host
define('DB_NAME', 'mediahost'); // Your Hostinger database name
define('DB_USER', 'root'); // Your Hostinger database username
define('DB_PASS', '');      // Your Hostinger database password

// Mail Configuration
define('MAIL_HOST', 'smtp.hostinger.com'); // Hostinger SMTP server
define('MAIL_PORT', 465); // SSL port
define('MAIL_USERNAME', 'Hello@mediahost.sirajahmed.site'); // Update with your email
define('MAIL_PASSWORD', 'Siraj@110'); // Your email password
define('MAIL_FROM', 'Hello@mediahost.sirajahmed.site'); // Update with your email
define('MAIL_FROM_NAME', 'MediaHost');
define('MAIL_ENCRYPTION', 'ssl');

// Application Configuration
define('SITE_URL', 'https://mediahost.sirajahmed.site'); // Your domain with HTTPS
define('UPLOAD_DIR', __DIR__ . '/uploads/');
define('MAX_STORAGE_SIZE', 524288000); // 500MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/webp']);
define('ALLOWED_VIDEO_TYPES', ['video/mp4', 'video/quicktime']);

// Security Configuration
define('CSRF_TOKEN_SECRET', bin2hex(random_bytes(32)));
define('SESSION_LIFETIME', 3600); // 1 hour

// Error Reporting (Production settings)
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

// Initialize Database Connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Session Configuration (Production settings)
ini_set('session.cookie_httponly', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_secure', '1');

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Strict',
    'cookie_secure' => true, // Enabled for HTTPS
    'gc_maxlifetime' => SESSION_LIFETIME
]); 