<?php
require_once 'config.php';
require_once 'includes/PHPMailer/PHPMailer.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

use PHPMailer\PHPMailer\PHPMailer;

function sendEmail($to, $subject, $message, $from_name, $from_email) {
    $mail = new PHPMailer();
    $mail->SMTPDebug = 0; // Disable debug output
    $mail->Host = MAIL_HOST;
    $mail->Port = MAIL_PORT;
    $mail->SMTPAuth = true;
    $mail->Username = MAIL_USERNAME;
    $mail->Password = MAIL_PASSWORD;
    $mail->SMTPSecure = MAIL_ENCRYPTION;
    
    $mail->setFrom($from_email, $from_name);
    $mail->addAddress($to);
    $mail->Subject = $subject;
    $mail->Body = $message;
    
    if (!$mail->send()) {
        error_log("Mailer Error: " . $mail->getError());
        return false;
    }
    
    return true;
}

// Set timezone
date_default_timezone_set('Europe/Berlin');

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        try {
            // Check if user exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Delete any existing tokens for this user
                $stmt = $pdo->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
                $stmt->execute([$user['id']]);
                
                // Insert new token with proper expiration
                $stmt = $pdo->prepare("
                    INSERT INTO password_reset_tokens (user_id, token, expires_at)
                    VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))
                ");
                $stmt->execute([$user['id'], $token]);
                
                // Generate reset link
                $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
                
                // Email content
                $subject = "Password Reset Request - " . MAIL_FROM_NAME;
                $message = "Hello {$user['username']},\n\n";
                $message .= "You have requested to reset your password. Click the link below to proceed:\n\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you did not request this password reset, please ignore this email.\n\n";
                $message .= "Best regards,\n" . MAIL_FROM_NAME;
                
                // Send email
                if (sendEmail($email, $subject, $message, MAIL_FROM_NAME, MAIL_FROM)) {
                    $success = true;
                } else {
                    error_log("Failed to send password reset email to: " . $email);
                    $errors['general'] = 'Failed to send reset email. Please try again later.';
                }
            } else {
                // Don't reveal if email exists or not
                $success = true;
            }
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $errors['general'] = 'An error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - MediaHost</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/forgot-password.css">
</head>
<body>
    <div class="forgot-password-container">
        <form class="forgot-password-form" method="POST" action="" novalidate>
            <h2>Reset Password</h2>
            <p class="instructions">
                Enter your email address and we'll send you a link to reset your password.
            </p>
            
            <?php if ($success): ?>
                <div class="success-message">
                    If an account exists with this email address, you will receive a password reset link shortly.
                    Please check your email inbox and spam folder.
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
                <?php if (isset($errors['email'])): ?>
                    <div class="error"><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="cta-button">Send Reset Link</button>
            
            <a href="login.php" class="back-to-login">Back to Login</a>
        </form>
    </div>
</body>
</html> 