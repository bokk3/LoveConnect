<?php
/**
 * Common functions for authentication, security, and session management
 */

require_once 'db.php';

/**
 * Generate CSRF token and store in session
 * 
 * @return string CSRF token
 */
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token from request
 * 
 * @param string|null $token Token to validate
 * @return bool True if valid
 */
function validateCSRFToken(?string $token): bool {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Start secure session with proper configuration
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start([
            'name' => SESSION_COOKIE_NAME,
            'cookie_httponly' => true,
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'cookie_samesite' => 'Strict',
            'use_strict_mode' => true,
            'use_only_cookies' => true,
            'cookie_lifetime' => 0, // Session cookie
        ]);
    }
}

/**
 * Require valid login session or redirect to login
 * 
 * @param string $redirectTo Login page URL
 */
function requireLogin(string $redirectTo = 'login.php'): void {
    startSecureSession();
    
    if (!isLoggedIn()) {
        // Store intended destination
        $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '';
        
        // Clear invalid session
        session_destroy();
        
        header("Location: {$redirectTo}");
        exit;
    }
    
    // Update session activity for sliding expiration
    updateSessionActivity();
}

/**
 * Check if user is currently logged in with valid session
 * 
 * @return bool True if logged in
 */
function isLoggedIn(): bool {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
        return false;
    }
    
    return validateSessionInDatabase($_SESSION['user_id'], $_SESSION['session_id']);
}

/**
 * Validate session against database with timeout check
 * 
 * @param int $userId User ID
 * @param string $sessionId Session ID
 * @return bool True if session is valid
 */
function validateSessionInDatabase(int $userId, string $sessionId): bool {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            SELECT s.id, s.last_activity, u.username, u.role 
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.user_id = ? AND s.session_id = ? 
            AND s.last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
            LIMIT 1
        ');
        $stmt->execute([$userId, $sessionId, SESSION_TIMEOUT]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return false;
        }
        
        // Update session variables with fresh data
        $_SESSION['username'] = $session['username'];
        $_SESSION['role'] = $session['role'];
        
        return true;
        
    } catch (PDOException $e) {
        error_log('Session validation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Clean up old sessions for a user (limit concurrent sessions)
 * 
 * @param int $userId User ID
 */
function cleanupUserSessions(int $userId): void {
    try {
        $pdo = getDbConnection();
        // Remove old sessions for this user
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
        $stmt->execute([$userId]);
    } catch (Exception $e) {
        error_log("Failed to cleanup user sessions: " . $e->getMessage());
    }
}

/**
 * Create user session with security measures
 * 
 * @param int $userId User ID
 * @param string $username Username
 * @param string $role User role
 */
function createUserSession(int $userId, string $username, string $role): void {
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Generate new session ID for database
    $sessionId = generateSessionId();
    
    // Clean up old sessions for this user
    cleanupUserSessions($userId);
    
    try {
        // Insert session into database
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))');
        $stmt->execute([$userId, $sessionId, SESSION_TIMEOUT]);
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = $role;
        $_SESSION['session_id'] = $sessionId;
        $_SESSION['created_at'] = time();
        $_SESSION['last_regeneration'] = time();
        
        // Generate new CSRF token
        unset($_SESSION['csrf_token']);
        generateCSRFToken();
        
        // Log successful login
        error_log("Successful login for user: {$username} (role: {$role}) from IP: " . getClientIP());
        
    } catch (PDOException $e) {
        error_log('Failed to create session: ' . $e->getMessage());
        throw new Exception('Failed to create session');
    }
}

/**
 * Update session activity timestamp for sliding expiration
 */
function updateSessionActivity(): void {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['session_id'])) {
        return;
    }
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE sessions SET last_activity = NOW() WHERE user_id = ? AND session_id = ?');
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_id']]);
        
        // Regenerate session ID periodically for security (every 30 minutes)
        if (!isset($_SESSION['last_regeneration']) || 
            (time() - $_SESSION['last_regeneration']) > 1800) {
            
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
        
    } catch (PDOException $e) {
        error_log('Failed to update session activity: ' . $e->getMessage());
    }
}

/**
 * Destroy user session completely
 * 
 * @param int|null $userId User ID (optional, will use session if not provided)
 * @param string|null $sessionId Session ID (optional, will use session if not provided)
 */
function destroyUserSession(?int $userId = null, ?string $sessionId = null): void {
    $userId = $userId ?? ($_SESSION['user_id'] ?? null);
    $sessionId = $sessionId ?? ($_SESSION['session_id'] ?? null);
    
    if ($userId && $sessionId) {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND session_id = ?');
            $stmt->execute([$userId, $sessionId]);
            
            error_log("Session destroyed for user ID: {$userId}");
            
        } catch (PDOException $e) {
            error_log('Failed to destroy session in database: ' . $e->getMessage());
        }
    }
    
    // Destroy PHP session
    session_unset();
    session_destroy();
    
    // Clear session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(SESSION_COOKIE_NAME, '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
}

/**
 * Set flash message for next request
 * 
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage(string $message, string $type = 'info'): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    
    $_SESSION['flash_messages'][] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Get and clear flash messages
 * 
 * @return array Flash messages
 */
function getFlashMessages(): array {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Check if user has specific role
 * 
 * @param string $role Required role
 * @return bool True if user has role
 */
function hasRole(string $role): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require specific role or redirect
 * 
 * @param string $role Required role
 * @param string $redirectTo Redirect URL if access denied
 */
function requireRole(string $role, string $redirectTo = 'admin.php'): void {
    if (!hasRole($role)) {
        setFlashMessage('Access denied. Insufficient privileges.', 'error');
        header("Location: {$redirectTo}");
        exit;
    }
}

/**
 * Get client IP address
 * 
 * @return string Client IP
 */
function getClientIP(): string {
    $ipKeys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) && !empty($_SERVER[$key])) {
            $ip = explode(',', $_SERVER[$key])[0];
            $ip = trim($ip);
            
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Rate limiting check
 * 
 * @param string $action Action being rate limited
 * @param int $maxAttempts Maximum attempts allowed
 * @param int $timeWindow Time window in seconds
 * @return bool True if action is allowed
 */
function isRateLimited(string $action, int $maxAttempts = 5, int $timeWindow = 300): bool {
    $ip = getClientIP();
    $key = "rate_limit_{$action}_{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + $timeWindow];
    }
    
    $rateLimitData = $_SESSION[$key];
    
    // Reset if time window expired
    if (time() > $rateLimitData['reset_time']) {
        $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + $timeWindow];
        return false;
    }
    
    // Check if limit exceeded
    if ($rateLimitData['count'] >= $maxAttempts) {
        return true;
    }
    
    // Increment counter
    $_SESSION[$key]['count']++;
    return false;
}

/**
 * Generate HTML for CSRF token input field
 * 
 * @return string HTML input field
 */
function csrfTokenField(): string {
    $token = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
    return "<input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\">";
}

/**
 * Generate HTML for flash messages
 * 
 * @return string HTML for flash messages
 */
function displayFlashMessages(): string {
    $messages = getFlashMessages();
    if (empty($messages)) {
        return '';
    }
    
    $html = '';
    foreach ($messages as $flash) {
        $type = htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8');
        $message = htmlspecialchars($flash['message'], ENT_QUOTES, 'UTF-8');
        
        $html .= "<div class=\"flash-message flash-{$type}\">{$message}</div>\n";
    }
    
    return $html;
}
?>