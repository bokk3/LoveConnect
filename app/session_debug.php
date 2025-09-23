<?php
require_once 'db.php';
require_once 'functions.php';

startSecureSession();

// Set a test variable if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['test_var'] = 'Session is working!';
    $_SESSION['test_time'] = time();
}

echo "Session Debug Information:\n";
echo "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Name: " . session_name() . "\n";
echo "Cookie Set: " . (isset($_COOKIE[SESSION_COOKIE_NAME]) ? 'Yes' : 'No') . "\n";

if (isset($_COOKIE[SESSION_COOKIE_NAME])) {
    echo "Cookie Value: " . $_COOKIE[SESSION_COOKIE_NAME] . "\n";
}

echo "\nSession Variables:\n";
if (empty($_SESSION)) {
    echo "  (No session variables set)\n";
} else {
    foreach ($_SESSION as $key => $value) {
        echo "  $key: $value\n";
    }
}

echo "\nIs Logged In: " . (isLoggedIn() ? 'Yes' : 'No') . "\n";

if (isset($_SESSION['user_id']) && isset($_SESSION['session_id'])) {
    echo "Validating session {$_SESSION['session_id']} for user {$_SESSION['user_id']}\n";
    $result = validateSessionInDatabase($_SESSION['user_id'], $_SESSION['session_id']);
    echo "Database validation result: " . ($result ? 'Valid' : 'Invalid') . "\n";
}
?>