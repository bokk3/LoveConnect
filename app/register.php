<?php
require_once 'db.php';
require_once 'functions.php';

startSecureSession();

if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processRegistration();
    if ($result['success']) {
        $success = $result['message'];
        // Clear form on success
        $username = '';
        $email = '';
    } else {
        $error = $result['message'];
        // Keep form data on error
        $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
        $email = htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8');
    }
}

function processRegistration(): array {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        return ['success' => false, 'message' => 'Security token validation failed. Please try again.'];
    }
    
    // Validate input presence
    $requiredFields = ['username', 'email', 'password', 'password_confirm'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            return ['success' => false, 'message' => 'All fields are required.'];
        }
    }
    
    // Sanitize inputs
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $passwordConfirm = $_POST['password_confirm'];
    
    // Validate username
    if (strlen($username) < 3) {
        return ['success' => false, 'message' => 'Username must be at least 3 characters long.'];
    }
    
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        return ['success' => false, 'message' => 'Username can only contain letters, numbers, and underscores.'];
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    
    // Validate password
    if (strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }
    
    if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    try {
        $pdo = getDbConnection();
        
        // Check if username exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username already exists. Please choose another.'];
        }
        
        // Check if email exists
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Email already registered. Please use another email or login.'];
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        
        // Insert new user
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
        $result = $stmt->execute([$username, $email, $passwordHash]);
        
        if ($result) {
            error_log("New user registered: $username ($email)");
            return ['success' => true, 'message' => 'Registration successful! You can now login with your credentials.'];
        } else {
            return ['success' => false, 'message' => 'Registration failed. Please try again.'];
        }
        
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        return ['success' => false, 'message' => 'A system error occurred. Please try again.'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join LoveConnect - Find Your Perfect Match</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .register-page {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #ff6b9d 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-lg);
        }
        
        .register-container {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            padding: var(--spacing-xxl);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 450px;
            animation: slideInUp 0.6s ease-out;
            backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .dark-theme .register-container {
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.85));
            border: 1px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 8px 16px rgba(102, 126, 234, 0.1);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: var(--spacing-xxl);
        }
        
        .app-logo {
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
            background: linear-gradient(135deg, #ff6b9d, #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .dark-theme .app-logo {
            background: linear-gradient(135deg, #ff8fb3, #8fa4f3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .app-title {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: var(--spacing-sm);
        }
        
        .app-subtitle {
            color: var(--text-secondary);
            font-size: var(--font-size-base);
            margin-bottom: var(--spacing-md);
        }
        
        .benefits-list {
            background: rgba(255, 107, 122, 0.1);
            padding: var(--spacing-md);
            border-radius: var(--border-radius-lg);
            margin-bottom: var(--spacing-lg);
            border: 1px solid rgba(255, 107, 122, 0.2);
        }
        
        .benefits-list h3 {
            color: var(--primary-color);
            font-size: var(--font-size-sm);
            margin-bottom: var(--spacing-xs);
            font-weight: 600;
        }
        
        .benefits-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .benefits-list li {
            font-size: var(--font-size-sm);
            color: var(--text-secondary);
            margin-bottom: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .benefits-list li::before {
            content: '✨';
            font-size: 1rem;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.2);
            color: #d32f2f;
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
            font-size: var(--font-size-sm);
        }
        
        .success-message {
            background: rgba(76, 175, 80, 0.1);
            border: 1px solid rgba(76, 175, 80, 0.2);
            color: #2e7d32;
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
            font-size: var(--font-size-sm);
        }
        
        .password-requirements {
            font-size: var(--font-size-xs);
            color: var(--text-secondary);
            margin-top: var(--spacing-xs);
            line-height: var(--line-height-normal);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: var(--spacing-md);
        }
        
        @media (max-width: 640px) {
            .register-container {
                padding: var(--spacing-xl);
                margin: var(--spacing-md);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .login-link {
            text-align: center;
            margin-top: var(--spacing-lg);
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .login-link a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body class="register-page">
    <div class="register-container">
        <div class="register-header">
            <div class="app-logo">💕</div>
            <h1 class="app-title">Join LoveConnect</h1>
            <p class="app-subtitle">Start your journey to find true love</p>
        </div>
        
        <div class="benefits-list">
            <h3>🌟 Why Join LoveConnect?</h3>
            <ul>
                <li>Smart matching algorithm</li>
                <li>Real-time messaging</li>
                <li>Safe & secure platform</li>
                <li>Mobile-first experience</li>
            </ul>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
                <br><br>
                <a href="login.php" class="btn btn-primary" style="display: inline-block; margin-top: var(--spacing-sm);">
                    Continue to Login →
                </a>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username" class="form-label">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            class="form-input" 
                            value="<?= $username ?>" 
                            required 
                            autocomplete="username"
                            minlength="3"
                            maxlength="50"
                            pattern="[a-zA-Z0-9_]+"
                            placeholder="Choose a username"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email Address</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="form-input" 
                            value="<?= $email ?>" 
                            required 
                            autocomplete="email"
                            placeholder="your@email.com"
                        >
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password" class="form-label">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            required 
                            autocomplete="new-password"
                            minlength="8"
                            placeholder="Create password"
                        >
                        <div class="password-requirements">
                            Must be at least 8 characters long
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password_confirm" class="form-label">Confirm Password</label>
                        <input 
                            type="password" 
                            id="password_confirm" 
                            name="password_confirm" 
                            class="form-input" 
                            required 
                            autocomplete="new-password"
                            minlength="8"
                            placeholder="Confirm password"
                        >
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-large">
                    🚀 Create My Account
                </button>
                
                <div class="login-link">
                    Already have an account? <a href="login.php">Sign in here</a>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Password confirmation validation
        document.getElementById('password_confirm').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Username validation
        document.getElementById('username').addEventListener('input', function() {
            const username = this.value;
            const regex = /^[a-zA-Z0-9_]+$/;
            
            if (username && !regex.test(username)) {
                this.setCustomValidity('Username can only contain letters, numbers, and underscores');
            } else if (username.length > 0 && username.length < 3) {
                this.setCustomValidity('Username must be at least 3 characters long');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Auto-focus first empty field
        document.addEventListener('DOMContentLoaded', function() {
            const firstInput = document.querySelector('input[type="text"], input[type="email"]');
            if (firstInput) {
                firstInput.focus();
            }
        });
    </script>
</body>
</html>