<?php
require_once 'config.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate username
    if (empty($username)) {
        $errors['username'] = 'Username is required';
    } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $errors['username'] = 'Username must be 3-20 characters and contain only letters, numbers, and underscores';
    }
    
    // Validate email
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }
    
    // Validate password
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->rowCount() > 0) {
                $errors['general'] = 'Username or email already exists';
            } else {
                // Hash password and insert user
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
                $stmt->execute([$username, $email, $password_hash]);
                
                $success = true;
                
                // Start session and redirect to dashboard
                session_start();
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                
                header('Location: dashboard/');
                exit;
            }
        } catch (PDOException $e) {
            $errors['general'] = 'Registration failed. Please try again later.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MediaHost</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/register.css">
</head>
<body>
    <div class="register-container">
        <form class="register-form" method="POST" action="" novalidate>
            <h2>Create Account</h2>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                       required>
                <?php if (isset($errors['username'])): ?>
                    <div class="error"><?php echo htmlspecialchars($errors['username']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email"
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                       required>
                <?php if (isset($errors['email'])): ?>
                    <div class="error"><?php echo htmlspecialchars($errors['email']); ?></div>
                <?php endif; ?>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="password-strength">
                    <div class="password-strength-meter"></div>
                </div>
                <?php if (isset($errors['password'])): ?>
                    <div class="error"><?php echo htmlspecialchars($errors['password']); ?></div>
                <?php endif; ?>
            </div>
            
            <button type="submit" class="cta-button">Create Account</button>
            
            <div class="login-link">
                <p>Already have an account? <a href="login.php">Log in</a></p>
            </div>
        </form>
    </div>
    
    <script>
        // Password strength meter
        const passwordInput = document.getElementById('password');
        const strengthMeter = document.querySelector('.password-strength-meter');
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            // Length check
            if (password.length >= 8) strength += 1;
            
            // Contains number
            if (/\d/.test(password)) strength += 1;
            
            // Contains special character
            if (/[!@#$%^&*]/.test(password)) strength += 1;
            
            // Update strength meter
            strengthMeter.className = 'password-strength-meter';
            if (strength === 1) strengthMeter.classList.add('strength-weak');
            else if (strength === 2) strengthMeter.classList.add('strength-medium');
            else if (strength === 3) strengthMeter.classList.add('strength-strong');
        });
    </script>
</body>
</html> 