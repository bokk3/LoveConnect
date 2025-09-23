<?php
/**
 * Login page with secure authentication
 * Handles both GET (display form) and POST (process login) requests
 */

require_once 'db.php';

// Start session with secure settings
session_start([
    'name' => SESSION_COOKIE_NAME,
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
    'use_strict_mode' => true,
]);

// Redirect if already logged in
if (isset($_SESSION['user_id']) && validateSession()) {
    header('Location: admin.php');
    exit;
}

$error = '';
$username = '';

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $error = processLogin();
}

/**
 * Process login attempt
 * 
 * @return string Error message if login fails, empty string on success
 */
function processLogin(): string {
    global $username;
    
    // Validate CSRF token (in production, implement proper CSRF protection)
    if (!isset($_POST['username']) || !isset($_POST['password'])) {
        return 'Missing required fields.';
    }
    
    // Sanitize inputs
    $username = sanitizeInput($_POST['username'], 255);
    $password = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($username)) {
        return 'Username is required.';
    }
    
    if (empty($password)) {
        return 'Password is required.';
    }
    
    if (strlen($password) > 1000) { // Prevent DoS attacks
        return 'Password too long.';
    }
    
    try {
        // Get user from database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Log failed login attempt
            error_log("Failed login attempt for username: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            
            // Same error message for both invalid username and password (security)
            return 'Invalid username or password.';
        }
        
        // Successful authentication - create session
        createUserSession($user['id'], $user['username']);
        
        // Redirect to admin page
        header('Location: admin.php');
        exit;
        
    } catch (PDOException $e) {
        error_log('Database error during login: ' . $e->getMessage());
        return 'Login system temporarily unavailable. Please try again later.';
    }
}

/**
 * Create user session in database and set session variables
 * 
 * @param int $userId User ID
 * @param string $username Username
 */
function createUserSession(int $userId, string $username): void {
    // Clean up old sessions for this user (optional - limit concurrent sessions)
    cleanupUserSessions($userId);
    
    // Generate new session ID
    session_regenerate_id(true);
    $sessionId = generateSessionId();
    
    try {
        // Insert session into database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_id) VALUES (?, ?)');
        $stmt->execute([$userId, $sessionId]);
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['created_at'] = time();
        
        // Log successful login
        error_log("Successful login for user: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        
    } catch (PDOException $e) {
        error_log('Failed to create session: ' . $e->getMessage());
        throw new Exception('Failed to create session');
    }
}

/**
 * Clean up old sessions for a user (limit to 5 concurrent sessions)
 * 
 * @param int $userId User ID
 */
function cleanupUserSessions(int $userId): void {
    try {
        $pdo = getDbConnection();
        
        // Keep only the 4 most recent sessions, delete the rest
        $stmt = $pdo->prepare('
            DELETE FROM sessions 
            WHERE user_id = ? 
            AND id NOT IN (
                SELECT id FROM (
                    SELECT id FROM sessions 
                    WHERE user_id = ? 
                    ORDER BY last_activity DESC 
                    LIMIT 4
                ) AS recent_sessions
            )
        ');
        $stmt->execute([$userId, $userId]);
        
    } catch (PDOException $e) {
        error_log('Failed to cleanup user sessions: ' . $e->getMessage());
    }
}

/**
 * Validate current session
 * 
 * @return bool True if session is valid
 */
function validateSession(): bool {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
        return false;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT id FROM sessions 
            WHERE user_id = ? AND session_id = ? 
            AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
            LIMIT 1
        ');
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_id'], SESSION_TIMEOUT]);
        
        return $stmt->fetch() !== false;
        
    } catch (PDOException $e) {
        error_log('Session validation error: ' . $e->getMessage());
        return false;
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
    <title>Login - Secure System</title>
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
        
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
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
        
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border: 1px solid #fcc;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .footer {
            text-align: center;
            margin-top: 2rem;
            font-size: 0.8rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Secure Login</h1>
            <p>Please enter your credentials</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    required 
                    autocomplete="username"
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
                    autocomplete="current-password"
                    maxlength="1000"
                >
            </div>
            
            <button type="submit" class="btn">
                Sign In
            </button>
        </form>
        
        <div class="footer">
            <p><strong>Demo credentials:</strong> admin / admin123</p>
            <p>Sessions expire after 30 minutes of inactivity</p>
        </div>
    </div>
</body>
</html>