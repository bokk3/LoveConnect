<?php
/**
 * Admin dashboard page
 * Requires valid session to access
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session and require login
startSecureSession();
requireLogin();

// Get user session statistics
$sessionStats = getUserSessionStats();
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$role = htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8');
$sessionTimeout = SESSION_TIMEOUT / 60; // Convert to minutes

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

// Get user data for theme preference
$currentUser = null;
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT dark_mode FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
} catch (Exception $e) {
    error_log("User load error: " . $e->getMessage());
    $currentUser = ['dark_mode' => false];
}

$sessionStats = getUserSessionStats();
$username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
$sessionTimeout = SESSION_TIMEOUT / 60; // Convert to minutes
?>
<!DOCTYPE html>
<html lang="en"<?php if (isset($currentUser['dark_mode']) && $currentUser['dark_mode']): ?> class="dark-theme"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LoveConnect Dating App</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
        .welcome-hero {
            background: var(--gradient-primary);
            color: white;
            text-align: center;
            padding: var(--spacing-xxl) var(--spacing-lg);
            border-radius: var(--border-radius-xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .hero-title {
            font-size: var(--font-size-3xl);
            font-weight: 700;
            margin-bottom: var(--spacing-sm);
        }
        
        .hero-subtitle {
            font-size: var(--font-size-lg);
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--surface-color);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            border: 1px solid var(--border-color);
            transition: transform var(--transition-fast);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: var(--spacing-sm);
        }
        
        .stat-number {
            font-size: var(--font-size-2xl);
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: var(--font-size-sm);
            font-weight: 500;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-lg);
        }
        
        .action-card {
            background: var(--surface-color);
            padding: var(--spacing-lg);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            border: 1px solid var(--border-color);
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .action-card:hover {
            transform: translateY(-2px);
            border-color: var(--primary-color);
        }
        
        .action-icon {
            font-size: 2rem;
            margin-bottom: var(--spacing-sm);
        }
        
        .recent-activity {
            background: var(--surface-color);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .activity-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            background: var(--bg-color);
        }
        
        .activity-item {
            padding: var(--spacing-md) var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius-full);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
    </style>
</head>
<body>
    <!-- App Header -->
    <header class="app-header">
        <div class="header-content">
            <h1 class="logo">💕 LoveConnect</h1>
            <nav>
                <ul class="nav-menu">
                    <li><a href="admin.php" class="nav-link active">Dashboard</a></li>
                    <li><a href="matches.php" class="nav-link">Discover</a></li>
                    <li><a href="profile.php" class="nav-link">Profile</a></li>
                    <li><a href="messages.php" class="nav-link">Messages</a></li>
                    <li><a href="logout.php" class="nav-link">Logout</a></li>
                    <li class="theme-toggle-container">
                        <button type="button" class="theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
                            <div class="theme-toggle-slider"></div>
                        </button>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="flash-container"></div>
        
        <!-- Welcome Hero -->
        <div class="welcome-hero">
            <h1 class="hero-title">Welcome back, <?= $username ?>! 😊</h1>
            <p class="hero-subtitle">Ready to find your perfect match?</p>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">💖</div>
                <div class="stat-number"><?= rand(5, 25) ?></div>
                <div class="stat-label">Total Matches</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">👀</div>
                <div class="stat-number"><?= rand(50, 150) ?></div>
                <div class="stat-label">Profiles Viewed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?= rand(10, 40) ?></div>
                <div class="stat-label">Messages Sent</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number"><?= rand(15, 55) ?></div>
                <div class="stat-label">Likes Received</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h2>🚀 Quick Actions</h2>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <div class="action-card" onclick="window.location.href='matches.php'">
                        <div class="action-icon">�</div>
                        <h3>Discover People</h3>
                        <p class="text-secondary">Start swiping to find matches</p>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='profile.php'">
                        <div class="action-icon">⚙️</div>
                        <h3>Edit Profile</h3>
                        <p class="text-secondary">Update your bio and preferences</p>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='matches.php#matches'">
                        <div class="action-icon">💑</div>
                        <h3>View Matches</h3>
                        <p class="text-secondary">See who you've matched with</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Account Information -->
        <div class="grid grid-lg-2" style="gap: 2rem; margin-top: 2rem;">
            <div class="card">
                <div class="card-header">
                    <h2>📊 Session Information</h2>
                </div>
                <div class="card-body">
                    <div class="stat-item">
                        <span class="stat-label">Username</span>
                        <span class="stat-value"><?= $username ?></span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Role</span>
                        <span class="stat-value">
                            <span class="role-badge role-<?= $role ?>">
                                <?= ucfirst($role) ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="stat-item">
                        <span class="stat-label">Active Sessions</span>
                        <span class="stat-value"><?= $sessionStats['active_sessions'] ?></span>
                    </div>
                    
                    <?php if ($sessionStats['session_created']): ?>
                    <div class="stat-item">
                        <span class="stat-label">Session Created</span>
                        <span class="stat-value">
                            <?= date('M j, g:i A', strtotime($sessionStats['session_created'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($sessionStats['last_activity']): ?>
                    <div class="stat-item">
                        <span class="stat-label">Last Activity</span>
                        <span class="stat-value">
                            <?= date('M j, g:i A', strtotime($sessionStats['last_activity'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <span class="stat-label">Session Timeout</span>
                        <span class="stat-value"><?= $sessionTimeout ?> minutes</span>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h2>🔒 Security Features</h2>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success-color);">✅</span>
                            <span>Argon2ID password hashing</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success-color);">✅</span>
                            <span>CSRF protection active</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success-color);">✅</span>
                            <span>Secure session management</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success-color);">✅</span>
                            <span>SQL injection protection</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span style="color: var(--success-color);">✅</span>
                            <span>Input validation & sanitization</span>
                        </div>
                    </div>
                    
                    <div class="mt-lg">
                        <a href="logout.php" class="btn btn-danger" style="width: 100%;">
                            � Logout Securely
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity mt-lg">
            <div class="activity-header">
                <h2>� Recent Activity</h2>
            </div>
            <div class="activity-item">
                <div class="activity-icon">🔑</div>
                <div>
                    <div style="font-weight: 500;">Logged in successfully</div>
                    <div class="text-secondary" style="font-size: var(--font-size-sm);">Just now</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">👤</div>
                <div>
                    <div style="font-weight: 500;">Profile updated</div>
                    <div class="text-secondary" style="font-size: var(--font-size-sm);">2 hours ago</div>
                </div>
            </div>
            <div class="activity-item">
                <div class="activity-icon">❤️</div>
                <div>
                    <div style="font-weight: 500;">New match found</div>
                    <div class="text-secondary" style="font-size: var(--font-size-sm);">Yesterday</div>
                </div>
            </div>
        </div>
    </main>
    
    <script src="assets/app.js"></script>
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
        
        // Initialize the Dating App
        if (typeof DatingApp !== 'undefined') {
            window.app = new DatingApp();
        }
    </script>
</body>
</html>