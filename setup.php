<?php
// Enable error reporting for setup
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';

echo "<h1>MediaHost Setup</h1>";
echo "<pre>";

// Create necessary directories
$directories = [
    'logs',
    'uploads',
    'dashboard/uploads',
    'assets/images'  // Add this directory for default thumbnails
];

echo "Creating directories...\n";
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✓ Created directory: $dir\n";
        } else {
            echo "✗ Failed to create directory: $dir\n";
        }
    } else {
        echo "• Directory already exists: $dir\n";
    }
}

// Database setup
try {
    echo "\nSetting up database...\n";
    
    // Connect to MySQL without selecting a database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '" . DB_NAME . "' created or already exists\n";
    
    // Select the database
    $pdo->exec("USE " . DB_NAME);
    
    // Create tables
    echo "\nCreating tables...\n";
    
    // Users table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        storage_used BIGINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_email (email),
        INDEX idx_username (username)
    ) ENGINE=InnoDB");
    echo "✓ Created users table\n";
    
    // Media table
    $pdo->exec("CREATE TABLE IF NOT EXISTS media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_type ENUM('image', 'video') NOT NULL,
        file_size BIGINT NOT NULL,
        description TEXT,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_upload (user_id, upload_date),
        INDEX idx_filename (file_name)
    ) ENGINE=InnoDB");
    echo "✓ Created media table\n";

    // Add thumbnail column if it doesn't exist
    try {
        $pdo->query("SELECT thumbnail FROM media LIMIT 1");
    } catch (PDOException $e) {
        $pdo->exec("ALTER TABLE media ADD COLUMN thumbnail VARCHAR(255) DEFAULT NULL AFTER file_size");
        echo "✓ Added thumbnail column to media table\n";
    }
    
    // Auth tokens table
    $pdo->exec("CREATE TABLE IF NOT EXISTS auth_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(64) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expiry (expires_at)
    ) ENGINE=InnoDB");
    echo "✓ Created auth_tokens table\n";

    // Drop the existing password_reset_tokens table if it exists
    $pdo->exec("DROP TABLE IF EXISTS password_reset_tokens");
    echo "✓ Dropped existing password_reset_tokens table\n";
    
    // Create password reset tokens table with correct structure
    $pdo->exec("CREATE TABLE password_reset_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        expires_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        used BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_token (token),
        INDEX idx_expiry (expires_at)
    ) ENGINE=InnoDB");
    echo "✓ Created password_reset_tokens table with updated structure\n";
    
    // Create fulltext indexes
    try {
        $pdo->exec("ALTER TABLE users ADD FULLTEXT INDEX ft_username_email (username, email)");
        echo "✓ Created fulltext index on users\n";
    } catch (PDOException $e) {
        echo "• Fulltext index on users already exists\n";
    }
    
    try {
        $pdo->exec("ALTER TABLE media ADD FULLTEXT INDEX ft_filename_description (file_name, description)");
        echo "✓ Created fulltext index on media\n";
    } catch (PDOException $e) {
        echo "• Fulltext index on media already exists\n";
    }
    
    echo "\n✓ Setup completed successfully!\n";
    echo "\nYou can now access the website at: " . SITE_URL . "\n";
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?> 