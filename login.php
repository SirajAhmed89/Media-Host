<?php
require_once 'config.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard/');
    exit;
}

$errors = [];

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
    $token = $_COOKIE['remember_me'];
    
    // Verify remember me token
    $stmt = $pdo->prepare("SELECT user_id FROM auth_tokens WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if ($result) {
        // Get user details
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
        $stmt->execute([$result['user_id']]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            header('Location: dashboard/');
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $errors['general'] = 'Please enter both email and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                
                // Handle remember me
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                    
                    $stmt = $pdo->prepare("INSERT INTO auth_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $stmt->execute([$user['id'], $token, $expires]);
                    
                    setcookie('remember_me', $token, strtotime('+30 days'), '/', '', true, true);
                }
                
                header('Location: dashboard/');
                exit;
            } else {
                $errors['general'] = 'Invalid email or password';
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Login failed. Please try again later.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - MediaHost</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>
<body>
    <div class="login-container">
        <form class="login-form" method="POST" action="" novalidate>
            <h2>Welcome Back</h2>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
            </div>
            
            <div class="remember-forgot">
                <label class="remember-me">
                    <input type="checkbox" name="remember" value="1"
                           <?php echo isset($_POST['remember']) ? 'checked' : ''; ?>>
                    Remember me
                </label>
                <a href="forgot-password.php" class="forgot-password">Forgot password?</a>
            </div>
            
            <button type="submit" class="cta-button">Log In</button>
            
            <div class="signup-link">
                <p>Don't have an account? <a href="register.php">Sign up</a></p>
            </div>
        </form>
    </div>
</body>
</html> 