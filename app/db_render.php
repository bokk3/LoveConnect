<?php
// Database configuration for Render (PostgreSQL)
function getDbConnection(): PDO {
    $host = getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_HOST) : (getenv('DB_HOST') ?: 'localhost');
    $port = getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_PORT) : 5432;
    $dbname = getenv('DATABASE_URL') ? ltrim(parse_url(getenv('DATABASE_URL'), PHP_URL_PATH), '/') : (getenv('DB_NAME') ?: 'loveconnect');
    $username = getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_USER) : (getenv('DB_USER') ?: 'postgres');
    $password = getenv('DATABASE_URL') ? parse_url(getenv('DATABASE_URL'), PHP_URL_PASS) : (getenv('DB_PASS') ?: '');

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    
    try {
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        throw new Exception("Database connection failed");
    }
}

// For local development compatibility
if (!function_exists('getenv') || getenv('APP_ENV') !== 'production') {
    // Fallback to MySQL for local development
    function getDbConnection(): PDO {
        $host = getenv('DB_HOST') ?: 'localhost';
        $dbname = getenv('DB_NAME') ?: 'login_system';  
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASS') ?: 'rootpassword';

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
}
?>