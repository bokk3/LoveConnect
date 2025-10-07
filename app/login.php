<?php
require_once 'db.php';
require_once 'functions.php';

startSecureSession();

if (isLoggedIn()) {
    header("Location: admin.php");
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = processLogin();
    if (empty($error)) {
        header("Location: admin.php");
        exit;
    }
    $username = htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES, 'UTF-8');
}

function processLogin(): string {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        return 'Security token validation failed. Please try again.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        return 'Username and password are required.';
    }

    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return 'Invalid username or password.';
        }

        // Create session
        createUserSession((int)$user['id'], $user['username']);
        return '';

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return 'A system error occurred. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Login - LoveConnect</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .simple-login-page {
            min-height: 100vh;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: var(--spacing-lg);
            position: relative;
        }
        
        .theme-toggle-login {
            position: absolute;
            top: 20px;
            right: 20px;
            z-index: 100;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px;
            border-radius: 15px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }
        
        .theme-toggle-login:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .theme-toggle-login .theme-toggle-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 12px;
            margin-bottom: 8px;
            text-align: center;
            font-weight: 600;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
        }
        
        .simple-login-container {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.85));
            padding: var(--spacing-xxl);
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1), 0 8px 16px rgba(0, 0, 0, 0.05);
            width: 100%;
            max-width: 400px;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .dark-theme .simple-login-container {
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.85));
            border: 1px solid rgba(102, 126, 234, 0.3);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3), 0 8px 16px rgba(102, 126, 234, 0.1);
        }
        
        .simple-login-header {
            text-align: center;
            margin-bottom: var(--spacing-xxl);
        }
        
        .simple-login-header h1 {
            background: linear-gradient(135deg, #ff6b9d, #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
        }
        
        .dark-theme .simple-login-header h1 {
            background: linear-gradient(135deg, #ff8fb3, #8fa4f3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .form-input {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7)) !important;
            border: 2px solid rgba(255, 107, 157, 0.2) !important;
            border-radius: 12px !important;
            padding: 12px 16px !important;
            transition: all 0.3s ease !important;
        }
        
        .form-input:focus {
            border-color: #ff6b9d !important;
            box-shadow: 0 0 0 4px rgba(255, 107, 157, 0.1) !important;
            background: rgba(255, 255, 255, 0.95) !important;
        }
        
        .dark-theme .form-input {
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.9), rgba(22, 33, 62, 0.7)) !important;
            border-color: rgba(102, 126, 234, 0.3) !important;
            color: white !important;
        }
        
        .dark-theme .form-input:focus {
            border-color: #667eea !important;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.2) !important;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff6b9d, #667eea) !important;
            border: none !important;
            border-radius: 12px !important;
            padding: 14px 24px !important;
            font-weight: 600 !important;
            transition: all 0.3s ease !important;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #e55a8a, #4c63d2) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 20px rgba(255, 107, 157, 0.3) !important;
        }
        
        .error-message {
            background: rgba(244, 67, 54, 0.1);
            border: 1px solid rgba(244, 67, 54, 0.2);
            color: #d32f2f;
            padding: var(--spacing-md);
            border-radius: var(--border-radius-md);
            margin-bottom: var(--spacing-lg);
        }
    </style>
</head>
<body class="simple-login-page">
    <!-- Theme toggle for testing -->
    <div class="theme-toggle-login">
        <div class="theme-toggle-container">
            <button type="button" class="theme-toggle" aria-label="Toggle dark mode" title="Click to toggle between light and dark theme">
                <div class="theme-toggle-slider"></div>
            </button>
            <div class="theme-toggle-label">Theme</div>
        </div>
    </div>
    
    <div class="simple-login-container">
        <div class="simple-login-header">
            <h1>💕 LoveConnect</h1>
            <p>Simple Login (No AJAX)</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
            
            <div class="form-group">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" value="<?= $username ?>" required autocomplete="username" placeholder="Enter your username">
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password" placeholder="Enter your password">
            </div>
            
            <button type="submit" class="btn btn-primary btn-large">Sign In</button>
        </form>
        
        <div style="margin-top: 1rem; padding: 1rem; background: rgba(255, 107, 122, 0.1); border-radius: 8px;">
            <p><strong>Demo Accounts:</strong></p>
            <p>Username: <code>admin</code> / Password: <code>admin123</code></p>
            <p>Username: <code>alex_tech</code> / Password: <code>editor123</code></p>
            <p>Username: <code>sarah_artist</code> / Password: <code>user123</code></p>
            <p>Username: <code>mike_chef</code> / Password: <code>chef123</code></p>
            <p>Username: <code>emma_doctor</code> / Password: <code>doctor123</code></p>
            <p>Username: <code>jordan_nb</code> / Password: <code>designer123</code></p>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="login.php" class="btn btn-secondary">Back to Full Login Page</a>
        </div>
    </div>
    
    <script src="assets/app.js"></script>
</body>
</html>