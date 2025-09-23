<?php
/**
 * Logout page
 * Destroys session both in database and browser cookie
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

// Log the logout attempt
$username = $_SESSION['username'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? null;
$sessionId = $_SESSION['session_id'] ?? null;

error_log("Logout attempt for user: {$username} from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

// Remove session from database if it exists
if ($userId && $sessionId) {
    destroyUserSession($userId, $sessionId);
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

/**
 * Remove session from database
 * 
 * @param int $userId User ID
 * @param string $sessionId Session ID
 */
function destroyUserSession(int $userId, string $sessionId): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE user_id = ? AND session_id = ?');
        $stmt->execute([$userId, $sessionId]);
        
        $deletedRows = $stmt->rowCount();
        if ($deletedRows > 0) {
            error_log("Successfully destroyed session for user ID: {$userId}");
        } else {
            error_log("No session found to destroy for user ID: {$userId}");
        }
        
    } catch (PDOException $e) {
        error_log('Failed to destroy session in database: ' . $e->getMessage());
    }
}

// Clean up expired sessions (10% chance)
if (random_int(1, 10) === 1) {
    cleanupExpiredSessions();
}

// Redirect to login page
header('Location: login.php');
exit;
?>