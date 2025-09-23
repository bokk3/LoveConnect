<?php
/**
 * Logout page
 * Destroys session both in database and browser cookie
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session
startSecureSession();

// Log the logout attempt
$username = $_SESSION['username'] ?? 'unknown';
$userId = $_SESSION['user_id'] ?? null;
$sessionId = $_SESSION['session_id'] ?? null;

error_log("Logout attempt for user: {$username} from IP: " . getClientIP());

// Destroy user session completely
destroyUserSession($userId, $sessionId);

// Clean up expired sessions (10% chance)
if (random_int(1, 10) === 1) {
    cleanupExpiredSessions();
}

// Start new session for flash message
startSecureSession();
setFlashMessage('You have been successfully logged out.', 'info');

// Redirect to login page
header('Location: login.php');
exit;
?>