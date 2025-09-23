<?php
/**
 * Database connection configuration for MariaDB
 * Uses PDO with proper error handling and security settings
 */

// Database configuration
define('DB_HOST', $_ENV['DB_HOST'] ?? 'mariadb');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'login_system');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? 'rootpassword');
define('DB_CHARSET', 'utf8mb4');

// Session configuration
define('SESSION_TIMEOUT', 30 * 60); // 30 minutes in seconds
define('SESSION_COOKIE_NAME', 'login_session');

/**
 * Get database connection using PDO
 * 
 * @return PDO Database connection
 * @throws PDOException on connection failure
 */
function getDbConnection(): PDO {
    static $pdo = null;
    
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Database connection failed');
        }
    }
    
    return $pdo;
}

/**
 * Clean up expired sessions from database
 * Should be called periodically
 */
function cleanupExpiredSessions(): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL ? SECOND)');
        $stmt->execute([SESSION_TIMEOUT]);
    } catch (PDOException $e) {
        error_log('Failed to cleanup expired sessions: ' . $e->getMessage());
    }
}

/**
 * Sanitize and validate input data
 * 
 * @param string $input Raw input data
 * @param int $max_length Maximum allowed length
 * @return string Sanitized input
 */
function sanitizeInput(string $input, int $max_length = 255): string {
    // Remove null bytes and trim whitespace
    $input = trim(str_replace("\0", '', $input));
    
    // Limit length
    if (strlen($input) > $max_length) {
        $input = substr($input, 0, $max_length);
    }
    
    return $input;
}

/**
 * Generate cryptographically secure random session ID
 * 
 * @return string Random session ID
 */
function generateSessionId(): string {
    return bin2hex(random_bytes(32));
}

/**
 * Validate session ID format
 * 
 * @param string $sessionId Session ID to validate
 * @return bool True if valid format
 */
function isValidSessionId(string $sessionId): bool {
    return preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1;
}
?>