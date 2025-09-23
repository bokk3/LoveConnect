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
        $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return 'Invalid username or password.';
        }

        // Create session
        createUserSession((int)$user['id'], $user['username'], $user['role']);
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
        }
        
        .simple-login-container {
            background: var(--surface-color);
            padding: var(--spacing-xxl);
            border-radius: var(--border-radius-xl);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 400px;
        }
        
        .simple-login-header {
            text-align: center;
            margin-bottom: var(--spacing-xxl);
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
            <p>Username: <code>admin</code> / Password: <code>password123</code></p>
            <p>Username: <code>alex_tech</code> / Password: <code>password123</code></p>
        </div>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="login.php" class="btn btn-secondary">Back to Full Login Page</a>
        </div>
    </div>
</body>
</html>