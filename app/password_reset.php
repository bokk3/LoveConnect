<?php
/**
 * Password Reset Page (Scaffold)
 * Demonstrates password reset flow structure
 * Note: Email functionality is not implemented in this demo
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

$step = $_GET['step'] ?? 'request';
$token = $_GET['token'] ?? '';
$error = '';
$success = '';
$email = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 'request') {
        $result = processResetRequest();
    } elseif ($step === 'reset') {
        $result = processPasswordReset();
    }
    
    if ($result['success']) {
        $success = $result['message'];
        if ($step === 'reset') {
            // Redirect to login after successful reset
            header('Location: login.php');
            exit;
        }
    } else {
        $error = $result['message'];
    }
}

/**
 * Process password reset request
 * 
 * @return array Result with success status and message
 */
function processResetRequest(): array {
    global $email;
    
    // Rate limiting check
    if (isRateLimited('password_reset', 3, 300)) { // 3 attempts per 5 minutes
        return ['success' => false, 'message' => 'Too many reset requests. Please try again in 5 minutes.'];
    }
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        return ['success' => false, 'message' => 'Security token validation failed. Please try again.'];
    }
    
    $email = sanitizeInput($_POST['email'] ?? '', 255);
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }
    
    try {
        $pdo = getDbConnection();
        
        // Check if email exists (don't reveal if it doesn't exist for security)
        $stmt = $pdo->prepare('SELECT id, username FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate secure reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour expiry
            
            // In a real implementation, you would:
            // 1. Store the token in a password_reset_tokens table
            // 2. Send an email with the reset link
            
            // For demo purposes, we'll just log the token
            error_log("Password reset requested for user {$user['username']} (email: {$email}). Token: {$resetToken}");
            
            // Demo: Show the reset link in the success message
            $resetLink = "password_reset.php?step=reset&token={$resetToken}";
            
            return [
                'success' => true, 
                'message' => "Password reset instructions have been sent to your email. For demo purposes, use this link: <a href='{$resetLink}'>{$resetLink}</a>"
            ];
        } else {
            // Don't reveal if email doesn't exist
            return [
                'success' => true, 
                'message' => 'If an account with that email exists, password reset instructions have been sent.'
            ];
        }
        
    } catch (PDOException $e) {
        error_log('Database error during password reset request: ' . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Process password reset with token
 * 
 * @return array Result with success status and message
 */
function processPasswordReset(): array {
    global $token;
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        return ['success' => false, 'message' => 'Security token validation failed. Please try again.'];
    }
    
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    
    // Validate inputs
    if (empty($token) || strlen($token) !== 64) {
        return ['success' => false, 'message' => 'Invalid reset token.'];
    }
    
    if (empty($password) || strlen($password) < 8) {
        return ['success' => false, 'message' => 'Password must be at least 8 characters long.'];
    }
    
    if ($password !== $passwordConfirm) {
        return ['success' => false, 'message' => 'Passwords do not match.'];
    }
    
    // Check password strength
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        return ['success' => false, 'message' => 'Password must contain at least one uppercase letter, one lowercase letter, and one number.'];
    }
    
    // In a real implementation, you would:
    // 1. Validate the token against the database
    // 2. Check if it's not expired
    // 3. Update the user's password
    // 4. Invalidate the token
    
    // For demo purposes, we'll simulate success
    error_log("Password reset completed for token: {$token}");
    
    return ['success' => true, 'message' => 'Password has been reset successfully. You can now log in with your new password.'];
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
    <title>Password Reset - Secure System</title>
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
        
        .reset-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .reset-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .reset-header p {
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
        
        .demo-notice {
            background: #fff3cd;
            color: #856404;
            padding: 1rem;
            border: 1px solid #ffeaa7;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.85rem;
        }
        
        .demo-notice strong {
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h1>🔑 Password Reset</h1>
            <p>
                <?php if ($step === 'request'): ?>
                    Enter your email to reset your password
                <?php else: ?>
                    Enter your new password
                <?php endif; ?>
            </p>
        </div>
        
        <div class="demo-notice">
            <strong>Demo Notice:</strong>
            This is a scaffold implementation. In production, this would send actual emails and use a proper token system with database storage.
        </div>
        
        <?php echo displayFlashMessages(); ?>
        
        <?php if ($success): ?>
            <div class="success">
                <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step === 'request'): ?>
            <!-- Step 1: Request Password Reset -->
            <form method="POST" action="">
                <?= csrfTokenField() ?>
                
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
                        placeholder="Enter your registered email"
                    >
                </div>
                
                <button type="submit" class="btn">
                    Send Reset Instructions
                </button>
            </form>
            
        <?php else: ?>
            <!-- Step 2: Reset Password -->
            <form method="POST" action="">
                <?= csrfTokenField() ?>
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="1000"
                        placeholder="Enter new password"
                    >
                </div>
                
                <div class="form-group">
                    <label for="password_confirm">Confirm New Password</label>
                    <input 
                        type="password" 
                        id="password_confirm" 
                        name="password_confirm" 
                        required 
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="1000"
                        placeholder="Confirm new password"
                    >
                </div>
                
                <button type="submit" class="btn">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
        
        <div class="footer">
            <p><a href="login.php">Back to Login</a></p>
            <?php if ($step === 'request'): ?>
                <p>Remember your password? <a href="login.php">Sign in</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>