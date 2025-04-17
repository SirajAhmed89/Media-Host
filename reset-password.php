<?php
require_once 'config.php';

// Set timezone
date_default_timezone_set('Europe/Berlin');

$errors = [];
$success = false;

if (!isset($_GET['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_GET['token'];

try {
    // Check if token exists and is valid
    $stmt = $pdo->prepare("
        SELECT prt.*, u.email, u.username 
        FROM password_reset_tokens prt
        JOIN users u ON u.id = prt.user_id
        WHERE prt.token = ? 
        AND prt.expires_at > NOW()
        AND prt.used = FALSE
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $errors['token'] = 'This password reset link has expired or is invalid. Please request a new one.';
    }
} catch (PDOException $e) {
    error_log("Reset token verification error: " . $e->getMessage());
    $errors['general'] = 'An error occurred. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    // Validate password confirmation
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    if (empty($errors)) {
        try {
            // Start transaction
            $pdo->beginTransaction();
            
            // Update password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$password_hash, $reset['user_id']]);
            
            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = TRUE WHERE id = ?");
            $stmt->execute([$reset['id']]);
            
            $pdo->commit();
            $success = true;
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Password reset error: " . $e->getMessage());
            $errors['general'] = 'Failed to reset password. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - MediaHost</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/reset-password.css">
</head>
<body>
    <div class="reset-password-container">
        <?php if ($success): ?>
            <div class="success-message">
                <h2>Password Reset Successful</h2>
                <p>Your password has been successfully reset.</p>
                <a href="login.php" class="cta-button">Log In</a>
            </div>
        <?php elseif (isset($errors['token'])): ?>
            <div class="error-message">
                <h2>Invalid Reset Link</h2>
                <p><?php echo htmlspecialchars($errors['token']); ?></p>
                <a href="forgot-password.php" class="cta-button">Request New Reset Link</a>
            </div>
        <?php else: ?>
            <form class="reset-password-form" method="POST" action="?token=<?php echo htmlspecialchars($token); ?>" novalidate>
                <h2>Reset Password</h2>
                
                <?php if (isset($errors['general'])): ?>
                    <div class="error"><?php echo htmlspecialchars($errors['general']); ?></div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" required>
                    <?php if (isset($errors['password'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['password']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="cta-button">Reset Password</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html> 