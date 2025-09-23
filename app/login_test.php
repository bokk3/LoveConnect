<?php
// Comprehensive login debugging script
require_once 'db.php';
require_once 'functions.php';

header('Content-Type: text/plain');

echo "=== LOGIN DEBUG TRACE ===\n\n";

// Start session
startSecureSession();
echo "1. Session started: " . session_id() . "\n";

// Test database connection
try {
    $pdo = getDbConnection();
    echo "2. Database connection: OK\n";
} catch (Exception $e) {
    echo "2. Database connection: FAILED - " . $e->getMessage() . "\n";
    exit;
}

// Check if we're in a POST request (simulated login)
if (isset($_GET['test_login'])) {
    echo "\n=== TESTING LOGIN PROCESS ===\n";
    
    // Simulate login data
    $username = 'admin';
    $password = 'password123';
    
    echo "3. Testing credentials: $username / $password\n";
    
    // Check user exists
    $stmt = $pdo->prepare('SELECT id, username, password_hash, role FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "4. User lookup: FAILED - User not found\n";
        exit;
    }
    
    echo "4. User lookup: OK - Found user ID {$user['id']}, role: {$user['role']}\n";
    
    // Test password verification
    $passwordValid = password_verify($password, $user['password_hash']);
    echo "5. Password verification: " . ($passwordValid ? 'OK' : 'FAILED') . "\n";
    
    if (!$passwordValid) {
        exit;
    }
    
    // Test session creation
    echo "6. Creating user session...\n";
    try {
        // Clean up old sessions first
        $cleanup = $pdo->prepare('DELETE FROM sessions WHERE user_id = ?');
        $cleanup->execute([$user['id']]);
        echo "   - Cleaned up old sessions\n";
        
        // Generate session ID
        $sessionId = bin2hex(random_bytes(32));
        echo "   - Generated session ID: $sessionId\n";
        
        // Insert new session
        $stmt = $pdo->prepare('INSERT INTO sessions (user_id, session_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))');
        $result = $stmt->execute([$user['id'], $sessionId, SESSION_TIMEOUT]);
        echo "   - Database insert: " . ($result ? 'OK' : 'FAILED') . "\n";
        
        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['session_id'] = $sessionId;
        
        echo "   - Session variables set\n";
        echo "   - user_id: {$_SESSION['user_id']}\n";
        echo "   - username: {$_SESSION['username']}\n";
        echo "   - role: {$_SESSION['role']}\n";
        echo "   - session_id: {$_SESSION['session_id']}\n";
        
    } catch (Exception $e) {
        echo "6. Session creation: FAILED - " . $e->getMessage() . "\n";
        exit;
    }
    
    echo "7. Testing session validation...\n";
    $isValid = validateSessionInDatabase($_SESSION['user_id'], $_SESSION['session_id']);
    echo "   - Database validation: " . ($isValid ? 'OK' : 'FAILED') . "\n";
    
    $isLoggedIn = isLoggedIn();
    echo "   - isLoggedIn() result: " . ($isLoggedIn ? 'TRUE' : 'FALSE') . "\n";
    
} else {
    echo "\n=== CURRENT SESSION STATUS ===\n";
    echo "3. Session variables:\n";
    if (empty($_SESSION)) {
        echo "   - No session variables set\n";
    } else {
        foreach ($_SESSION as $key => $value) {
            echo "   - $key: $value\n";
        }
    }
    
    echo "4. Cookie status:\n";
    echo "   - Cookie set: " . (isset($_COOKIE[SESSION_COOKIE_NAME]) ? 'YES' : 'NO') . "\n";
    if (isset($_COOKIE[SESSION_COOKIE_NAME])) {
        echo "   - Cookie value: " . $_COOKIE[SESSION_COOKIE_NAME] . "\n";
    }
    
    echo "5. Login status: " . (isLoggedIn() ? 'LOGGED IN' : 'NOT LOGGED IN') . "\n";
    
    echo "\n=== DATABASE SESSION CHECK ===\n";
    $stmt = $pdo->prepare('SELECT s.*, u.username FROM sessions s JOIN users u ON s.user_id = u.id ORDER BY s.created_at DESC LIMIT 3');
    $stmt->execute();
    $sessions = $stmt->fetchAll();
    
    echo "Recent sessions in database:\n";
    foreach ($sessions as $session) {
        $timeLeft = strtotime($session['expires_at']) - time();
        echo "   - User: {$session['username']}, Session: " . substr($session['session_id'], 0, 8) . "..., ";
        echo "Created: {$session['created_at']}, Expires in: " . ($timeLeft > 0 ? $timeLeft . " seconds" : "EXPIRED") . "\n";
    }
}

echo "\n=== LINKS ===\n";
echo "Test login: ?test_login=1\n";
echo "Just status: (current page)\n";
?>