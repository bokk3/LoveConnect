<?php
/**
 * Admin dashboard page
 * Requires valid session to access
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

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !validateSession()) {
    // Destroy invalid session
    session_destroy();
    header('Location: login.php');
    exit;
}

// Update session activity
updateSessionActivity();

/**
 * Validate current session against database
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
            SELECT s.id, s.last_activity, u.username 
            FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE s.user_id = ? AND s.session_id = ? 
            AND s.last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
            LIMIT 1
        ');
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_id'], SESSION_TIMEOUT]);
        $session = $stmt->fetch();
        
        if (!$session) {
            return false;
        }
        
        // Update username in session if it changed
        $_SESSION['username'] = $session['username'];
        
        return true;
        
    } catch (PDOException $e) {
        error_log('Session validation error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update session last activity timestamp
 */
function updateSessionActivity(): void {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE sessions SET last_activity = NOW() WHERE user_id = ? AND session_id = ?');
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_id']]);
    } catch (PDOException $e) {
        error_log('Failed to update session activity: ' . $e->getMessage());
    }
}

/**
 * Get user session statistics
 * 
 * @return array Session statistics
 */
function getUserSessionStats(): array {
    try {
        $pdo = getDbConnection();
        
        // Get active sessions count for current user
        $stmt = $pdo->prepare('
            SELECT COUNT(*) as active_sessions
            FROM sessions 
            WHERE user_id = ? 
            AND last_activity > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ');
        $stmt->execute([$_SESSION['user_id'], SESSION_TIMEOUT]);
        $activeResult = $stmt->fetch();
        
        // Get current session info
        $stmt = $pdo->prepare('
            SELECT created_at, last_activity
            FROM sessions 
            WHERE user_id = ? AND session_id = ?
        ');
        $stmt->execute([$_SESSION['user_id'], $_SESSION['session_id']]);
        $currentSession = $stmt->fetch();
        
        return [
            'active_sessions' => $activeResult['active_sessions'] ?? 0,
            'session_created' => $currentSession['created_at'] ?? null,
            'last_activity' => $currentSession['last_activity'] ?? null,
        ];
        
    } catch (PDOException $e) {
        error_log('Failed to get session stats: ' . $e->getMessage());
        return [
            'active_sessions' => 0,
            'session_created' => null,
            'last_activity' => null,
        ];
    }
}

$sessionStats = getUserSessionStats();
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$sessionTimeout = SESSION_TIMEOUT / 60; // Convert to minutes
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Secure System</title>
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
            padding: 2rem;
        }
        
        .header {
            background: white;
            padding: 1.5rem 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .header .user-info {
            color: #666;
            font-size: 0.9rem;
        }
        
        .header .actions {
            display: flex;
            gap: 1rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .card {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .card h2 {
            color: #333;
            font-size: 1.3rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card .icon {
            font-size: 1.5rem;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .stat-item:last-child {
            border-bottom: none;
        }
        
        .stat-label {
            color: #666;
            font-weight: 500;
        }
        
        .stat-value {
            color: #333;
            font-weight: 600;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a5a 100%);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .welcome-section {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .welcome-section h3 {
            color: #333;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
        
        .welcome-section p {
            color: #666;
            font-size: 1rem;
        }
        
        .alert {
            background: #e8f4fd;
            color: #2c5282;
            padding: 1rem;
            border: 1px solid #bee3f8;
            border-radius: 5px;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .header .actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>🛡️ Admin Dashboard</h1>
                <div class="user-info">
                    Welcome back, <strong><?= $username ?></strong>
                </div>
            </div>
            <div class="actions">
                <a href="logout.php" class="btn btn-danger">
                    🚪 Logout
                </a>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="card">
                <h2>
                    <span class="icon">📊</span>
                    Session Information
                </h2>
                
                <div class="stat-item">
                    <span class="stat-label">Username</span>
                    <span class="stat-value"><?= $username ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">Active Sessions</span>
                    <span class="stat-value"><?= $sessionStats['active_sessions'] ?></span>
                </div>
                
                <div class="stat-item">
                    <span class="stat-label">Session Timeout</span>
                    <span class="stat-value"><?= $sessionTimeout ?> minutes</span>
                </div>
                
                <?php if ($sessionStats['session_created']): ?>
                <div class="stat-item">
                    <span class="stat-label">Session Created</span>
                    <span class="stat-value">
                        <?= date('Y-m-d H:i:s', strtotime($sessionStats['session_created'])) ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if ($sessionStats['last_activity']): ?>
                <div class="stat-item">
                    <span class="stat-label">Last Activity</span>
                    <span class="stat-value">
                        <?= date('Y-m-d H:i:s', strtotime($sessionStats['last_activity'])) ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>
                    <span class="icon">🔐</span>
                    Security Features
                </h2>
                
                <div class="welcome-section">
                    <h3>System is Secure!</h3>
                    <p>This login system includes all modern security practices</p>
                </div>
                
                <div class="alert">
                    <strong>Security Features Active:</strong><br>
                    ✅ Password hashing with Argon2ID<br>
                    ✅ Prepared statements for SQL injection protection<br>
                    ✅ Session regeneration on login<br>
                    ✅ Input sanitization and validation<br>
                    ✅ Session timeout protection<br>
                    ✅ Secure cookie settings<br>
                    ✅ Error logging and monitoring
                </div>
            </div>
            
            <div class="card">
                <h2>
                    <span class="icon">⚙️</span>
                    Quick Actions
                </h2>
                
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <a href="logout.php" class="btn btn-danger">
                        🚪 Logout from this session
                    </a>
                    
                    <button onclick="location.reload()" class="btn btn-primary">
                        🔄 Refresh page
                    </button>
                </div>
                
                <div style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee; font-size: 0.85rem; color: #666;">
                    <p><strong>Note:</strong> This is a demonstration admin panel. In a real application, this would contain actual administrative functions.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh session activity every 5 minutes
        setInterval(function() {
            fetch(window.location.href, {
                method: 'HEAD',
                credentials: 'same-origin'
            }).catch(function(error) {
                console.log('Session refresh failed:', error);
            });
        }, 5 * 60 * 1000);
        
        // Show session timeout warning
        setTimeout(function() {
            if (confirm('Your session will expire in 5 minutes. Do you want to refresh it?')) {
                location.reload();
            }
        }, (<?= SESSION_TIMEOUT ?> - 300) * 1000); // 5 minutes before expiry
    </script>
</body>
</html>