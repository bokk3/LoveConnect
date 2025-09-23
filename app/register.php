<?php
/**
 * User registration page
 * Allows creation of new user accounts with role assignment
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session
startSecureSession();

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: admin.php');
    exit;
}

$error = '';
$success = '';
$username = '';
$email = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = processRegistration();
    if ($result['success']) {
        $success = $result['message'];
        $username = '';
        $email = '';
    } else {
        $error = $result['message'];
    }
}

/**
 * Process user registration
 * 
 * @return array Result with success status and message
 */
function processRegistration(): array {
    global $username, $email;
    
    // Rate limiting check
    if (isRateLimited('register', 3, 300)) { // 3 attempts per 5 minutes
        error_log('Rate limit exceeded for registration from IP: ' . getClientIP());
        return ['success' => false, 'message' => 'Too many registration attempts. Please try again in 5 minutes.'];
    }
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        error_log('CSRF token validation failed for registration from IP: ' . getClientIP());
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
    $username = sanitizeInput($_POST['username'], 50);
    $email = sanitizeInput($_POST['email'], 255);
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
    
    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.'];
    }
    
    try {
        $pdo = getDbConnection();
        
        // Check if username or email already exists
        $stmt = $pdo->prepare('SELECT username, email FROM users WHERE username = ? OR email = ? LIMIT 1');
        $stmt->execute([$username, $email]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            if ($existing['username'] === $username) {
                return ['success' => false, 'message' => 'Username already exists. Please choose a different username.'];
            } else {
                return ['success' => false, 'message' => 'Email address already registered. Please use a different email.'];
            }
        }
        
        // Hash password
        $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
        
        // Insert new user (default role is 'user')
        $stmt = $pdo->prepare('INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, ?)');
        $stmt->execute([$username, $email, $passwordHash, 'user']);
        
        // Log successful registration
        error_log("New user registered: {$username} ({$email}) from IP: " . getClientIP());
        
        return ['success' => true, 'message' => 'Registration successful! You can now log in with your credentials.'];
        
    } catch (PDOException $e) {
        error_log('Database error during registration: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Registration failed due to a system error. Please try again later.'];
    }
}

// Clean up expired sessions periodically (1% chance)
if (random_int(1, 100) === 1) {
    cleanupExpiredSessions();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Secure System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 450px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e1e5e9;
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .flash-message {
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .flash-success, .success {
            background: #eef;
            color: #363;
            border: 1px solid #cfc;
        }
        
        .flash-error, .error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #666;
        }
        
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        
        .footer a:hover {
            text-decoration: underline;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
        }
        
        .password-requirements ul {
            margin: 0.5rem 0 0 1rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1>📝 Create Account</h1>
            <p>Join our secure platform</p>
        </div>
        
        <?php echo displayFlashMessages(); ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <?= csrfTokenField() ?>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    required 
                    autocomplete="username"
                    minlength="3"
                    maxlength="50"
                    pattern="[a-zA-Z0-9_]+"
                    title="Username can only contain letters, numbers, and underscores"
                >
            </div>
            
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                    required 
                    autocomplete="email"
                    maxlength="255"
                >
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    required 
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="1000"
                >
                <div class="password-requirements">
                    Password requirements:
                    <ul>
                        <li>At least 8 characters long</li>
                        <li>Contains uppercase and lowercase letters</li>
                        <li>Contains at least one number</li>
                    </ul>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password_confirm">Confirm Password</label>
                <input 
                    type="password" 
                    id="password_confirm" 
                    name="password_confirm" 
                    required 
                    autocomplete="new-password"
                    minlength="8"
                    maxlength="1000"
                >
            </div>
            
            <button type="submit" class="btn">
                Create Account
            </button>
        </form>
        
        <div class="footer">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
            <p>By registering, you'll be assigned the 'user' role by default</p>
        </div>
    </div>
</body>
</html>