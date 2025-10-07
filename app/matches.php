<?php
/**
 * Matches Page - Mobile-First Dating App
 * Swipe-like interface for browsing and matching with other users
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session and check authentication
startSecureSession();
requireLogin();

$error = '';
$success = '';
$currentUser = null;
$availableProfiles = [];
$userMatches = [];

// Get current user data
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        header('Location: logout.php');
        exit;
    }
} catch (Exception $e) {
    error_log("User load error: " . $e->getMessage());
    $error = 'Failed to load user data.';
}

// Process match actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        switch ($_POST['action']) {
            case 'like':
                $result = likeUser((int)($_POST['user_id'] ?? 0));
                echo json_encode($result);
                exit;
            case 'pass':
                $result = passUser((int)($_POST['user_id'] ?? 0));
                echo json_encode($result);
                exit;
        }
    }
}

// Load available profiles for matching
try {
    $userLookingFor = json_decode($currentUser['looking_for'] ?? '[]', true) ?: [];
    $ageMin = $currentUser['age_min'] ?? 18;
    $ageMax = $currentUser['age_max'] ?? 100;
    
    // Build gender filter
    $genderPlaceholders = '';
    $genderParams = [];
    if (!empty($userLookingFor)) {
        $genderPlaceholders = str_repeat('?,', count($userLookingFor) - 1) . '?';
        $genderParams = $userLookingFor;
    }
    
    $sql = '
        SELECT u.id, u.username, u.gender, u.age, u.bio, u.interests, u.location, u.profile_image,
               u.last_active
        FROM users u
        WHERE u.id != ? 
          AND u.is_active = TRUE 
          AND u.last_active > DATE_SUB(NOW(), INTERVAL 30 DAY)
          AND u.age BETWEEN ? AND ?
          AND u.id NOT IN (
              SELECT matched_user_id 
              FROM matches 
              WHERE user_id = ? AND status IN ("liked", "passed", "matched", "unmatched")
          )
    ';
    
    $params = [$_SESSION['user_id'], $ageMin, $ageMax, $_SESSION['user_id']];
    
    if (!empty($userLookingFor)) {
        $sql .= ' AND u.gender IN (' . $genderPlaceholders . ')';
        $params = array_merge($params, $genderParams);
    }
    
    $sql .= ' ORDER BY u.last_active DESC, RAND() LIMIT 20';
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $availableProfiles = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Profiles load error: " . $e->getMessage());
    $error = 'Failed to load profiles.';
}

// Load user's existing matches
try {
    $stmt = $pdo->prepare('
        SELECT u.id, u.username, u.gender, u.age, u.bio, u.interests, u.location, u.profile_image,
               m.created_at as match_date, m.status
        FROM matches m
        JOIN users u ON u.id = m.matched_user_id
        WHERE m.user_id = ? AND m.status = "liked"
          AND EXISTS (
              SELECT 1 FROM matches m2 
              WHERE m2.user_id = m.matched_user_id 
                AND m2.matched_user_id = m.user_id 
                AND m2.status = "liked"
          )
        ORDER BY m.created_at DESC
    ');
    $stmt->execute([$_SESSION['user_id']]);
    $userMatches = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log("Matches load error: " . $e->getMessage());
}

/**
 * Like a user and check for mutual match
 * @param int $userId User ID to like
 * @return array Result with success status and match info
 */
function likeUser(int $userId): array {
    try {
        $pdo = getDbConnection();
        
        // Check if we already have an action for this user
        $stmt = $pdo->prepare('SELECT status FROM matches WHERE user_id = ? AND matched_user_id = ?');
        $stmt->execute([$_SESSION['user_id'], $userId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return ['success' => false, 'message' => 'Already rated this user'];
        }
        
        // Insert like
        $stmt = $pdo->prepare('INSERT INTO matches (user_id, matched_user_id, status, match_score) VALUES (?, ?, "liked", ?)');
        $matchScore = calculateMatchScore($_SESSION['user_id'], $userId);
        $stmt->execute([$_SESSION['user_id'], $userId, $matchScore]);
        
        // Check if it's a mutual match
        $stmt = $pdo->prepare('SELECT id FROM matches WHERE user_id = ? AND matched_user_id = ? AND status = "liked"');
        $stmt->execute([$userId, $_SESSION['user_id']]);
        $mutualLike = $stmt->fetch();
        
        if ($mutualLike) {
            // Update both matches to "matched" status
            $stmt = $pdo->prepare('UPDATE matches SET status = "matched" WHERE (user_id = ? AND matched_user_id = ?) OR (user_id = ? AND matched_user_id = ?)');
            $stmt->execute([$_SESSION['user_id'], $userId, $userId, $_SESSION['user_id']]);
            
            return ['success' => true, 'match' => true, 'message' => "It's a match! 🎉"];
        }
        
        return ['success' => true, 'match' => false, 'message' => 'Profile liked!'];
        
    } catch (Exception $e) {
        error_log("Like user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to like user'];
    }
}

/**
 * Pass on a user
 * @param int $userId User ID to pass
 * @return array Result with success status
 */
function passUser(int $userId): array {
    try {
        $pdo = getDbConnection();
        
        // Check if we already have an action for this user
        $stmt = $pdo->prepare('SELECT status FROM matches WHERE user_id = ? AND matched_user_id = ?');
        $stmt->execute([$_SESSION['user_id'], $userId]);
        $existing = $stmt->fetch();
        
        if ($existing) {
            return ['success' => false, 'message' => 'Already rated this user'];
        }
        
        // Insert pass
        $stmt = $pdo->prepare('INSERT INTO matches (user_id, matched_user_id, status, match_score) VALUES (?, ?, "passed", ?)');
        $matchScore = calculateMatchScore($_SESSION['user_id'], $userId);
        $stmt->execute([$_SESSION['user_id'], $userId, $matchScore]);
        
        return ['success' => true, 'message' => 'Profile passed'];
        
    } catch (Exception $e) {
        error_log("Pass user error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to pass user'];
    }
}

/**
 * Calculate basic match score based on common interests
 * @param int $userId1 First user ID
 * @param int $userId2 Second user ID
 * @return float Match score between 0.0 and 1.0
 */
function calculateMatchScore(int $userId1, int $userId2): float {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT interests FROM users WHERE id IN (?, ?)');
        $stmt->execute([$userId1, $userId2]);
        $users = $stmt->fetchAll();
        
        if (count($users) !== 2) return 0.5;
        
        $interests1 = json_decode($users[0]['interests'] ?? '[]', true) ?: [];
        $interests2 = json_decode($users[1]['interests'] ?? '[]', true) ?: [];
        
        if (empty($interests1) || empty($interests2)) return 0.5;
        
        $common = array_intersect($interests1, $interests2);
        $total = array_unique(array_merge($interests1, $interests2));
        
        return count($total) > 0 ? count($common) / count($total) : 0.5;
        
    } catch (Exception $e) {
        return 0.5;
    }
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Discover - Dating App</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="assets/style.css">
    <style>
                .swipe-card {
            position: absolute;
            width: 100%;
            max-width: 400px;
            height: 600px;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            transform-origin: center center;
            transition: all 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
            animation: cardEntry 0.6s ease-out forwards;
        }
        
        @keyframes cardEntry {
            from {
                opacity: 0;
                transform: translate(-50%, -50%) translateY(30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%) translateY(0) scale(1);
            }
        }
        
        .swipe-card:nth-child(1) { animation-delay: 0s; }
        .swipe-card:nth-child(2) { animation-delay: 0.1s; }
        .swipe-card:nth-child(3) { animation-delay: 0.2s; }
        
        /* Responsive alignment fixes */
        @media (max-width: 768px) {
            .swipe-card {
                max-width: 350px;
            }
            
            .swipe-container {
                padding: 1rem 0.5rem !important;
            }
            
            .profile-stack {
                max-width: 350px !important;
            }
        }
        
        @media (max-width: 480px) {
            .swipe-card {
                max-width: 320px;
            }
            
            .profile-stack {
                max-width: 320px !important;
                height: 550px !important;
            }
        }
        
        .profile-card-shimmer {
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(255, 255, 255, 0.2),
                transparent
            );
            animation: shimmer 3s infinite;
            pointer-events: none;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .profile-card {
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.95), rgba(255, 255, 255, 0.9));
            border: 3px solid transparent;
            background-clip: padding-box;
            border-radius: 28px;
            box-shadow: 
                0 25px 50px rgba(255, 107, 157, 0.25),
                0 15px 35px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.6);
            position: relative;
            overflow: hidden;
            height: 100%;
            width: 100%;
            cursor: grab;
            user-select: none;
            transition: all 0.4s cubic-bezier(0.4, 0.0, 0.2, 1);
        }
        
        .profile-card:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: 
                0 35px 70px rgba(255, 107, 157, 0.3),
                0 20px 45px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.8);
        }
        
        .profile-card:active {
            cursor: grabbing;
        }
        
        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 25px;
            padding: 3px;
            background: linear-gradient(135deg, #ff6b9d, #a8e6cf, #667eea);
            mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
            mask-composite: exclude;
            z-index: -1;
        }
        
        .profile-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            opacity: 0.8;
        }
        
        .dark-theme .profile-card {
            background: linear-gradient(145deg, rgba(26, 26, 46, 0.95), rgba(22, 33, 62, 0.85)) !important;
            border-color: rgba(102, 126, 234, 0.3) !important;
            box-shadow: 0 15px 35px rgba(102, 126, 234, 0.2), 0 8px 20px rgba(0, 0, 0, 0.3) !important;
        }
        
        .swipe-actions {
            display: flex;
            justify-content: center;
            gap: 3rem;
            margin-top: 2.5rem;
            position: relative;
        }
        
        .swipe-actions .btn {
            width: 75px;
            height: 75px;
            border-radius: 50% !important;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            font-weight: 600 !important;
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 
                0 10px 30px rgba(0, 0, 0, 0.25),
                0 5px 15px rgba(0, 0, 0, 0.15),
                inset 0 1px 0 rgba(255, 255, 255, 0.3) !important;
            transition: all 0.4s cubic-bezier(0.4, 0.0, 0.2, 1) !important;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .swipe-actions .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: 50%;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2), transparent 50%);
            pointer-events: none;
        }
        
        .swipe-actions .btn:hover {
            transform: translateY(-8px) scale(1.15) !important;
            box-shadow: 
                0 15px 40px rgba(0, 0, 0, 0.3),
                0 8px 20px rgba(0, 0, 0, 0.2),
                inset 0 1px 0 rgba(255, 255, 255, 0.4) !important;
        }
        
        .swipe-actions .btn:active {
            transform: translateY(-5px) scale(1.05) !important;
        }
        
        .swipe-actions .btn-secondary {
            background: linear-gradient(135deg, #ff6b6b, #ff5252);
        }
        
        .swipe-actions .btn-secondary:hover {
            background: linear-gradient(135deg, #ff5252, #ff1744);
        }
        
        .swipe-actions .btn-primary {
            background: linear-gradient(135deg, #4ecdc4, #26c6da);
        }
        
        .swipe-actions .btn-primary:hover {
            background: linear-gradient(135deg, #26c6da, #00bcd4);
        }
        
        .match-stats {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.1), rgba(102, 126, 234, 0.1)) !important;
            border: 2px solid rgba(255, 107, 157, 0.2) !important;
            border-radius: 20px !important;
            backdrop-filter: blur(10px) !important;
        }
        
        .interest-tag {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.15), rgba(168, 230, 207, 0.15)) !important;
            border: 1px solid rgba(255, 107, 157, 0.3) !important;
            border-radius: 20px !important;
            color: var(--primary-color) !important;
            font-weight: 500 !important;
        }
        
        .interest-tag:hover {
            background: linear-gradient(135deg, rgba(255, 107, 157, 0.25), rgba(168, 230, 207, 0.25)) !important;
            transform: translateY(-1px) !important;
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
                    <li><a href="admin.php" class="nav-link">Dashboard</a></li>
                    <li><a href="matches.php" class="nav-link active">Discover</a></li>
                    <li><a href="profile.php" class="nav-link">Profile</a></li>
                    <li><a href="messages.php" class="nav-link">Messages</a></li>
                    <li class="theme-toggle-container">
                        <button type="button" class="theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
                            <div class="theme-toggle-slider"></div>
                        </button>
                    </li>
                    <li><a href="logout.php" class="nav-link">Logout</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="flash-container"></div>
        
        <!-- Page Header -->
        <div class="text-center mt-lg mb-md">
            <h1>Discover People</h1>
            <p class="text-secondary">Swipe right to like, left to pass</p>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($error)): ?>
            <div class="flash-message flash-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="flex justify-center mb-lg">
            <div class="nav-tabs" style="display: flex; background: var(--surface-color); border-radius: var(--border-radius-lg); padding: 4px; box-shadow: var(--shadow-md);">
                <button class="tab-btn active" data-tab="discover" style="padding: 12px 24px; border: none; background: var(--primary-color); color: white; border-radius: var(--border-radius-md); cursor: pointer; font-weight: 500;">
                    Discover
                </button>
                <button class="tab-btn" data-tab="matches" style="padding: 12px 24px; border: none; background: transparent; color: var(--text-secondary); border-radius: var(--border-radius-md); cursor: pointer; font-weight: 500;">
                    Matches (<?php echo count($userMatches); ?>)
                </button>
            </div>
        </div>

        <!-- Discover Tab -->
        <div id="discover-tab" class="tab-content">
            <?php if (empty($availableProfiles)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>No more profiles</h3>
                        <p class="text-secondary">You've seen all available profiles in your area. Check back later for new people!</p>
                        <a href="profile.php" class="btn btn-primary mt-md">Update Preferences</a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Swipe Container -->
                <div class="swipe-container" style="display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 2rem 1rem;">
                    <div class="profile-stack" id="profileStack" style="
                        position: relative; 
                        width: 100%; 
                        max-width: 400px; 
                        height: 600px; 
                        margin: 0 auto;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    ">
                        <?php foreach (array_reverse($availableProfiles) as $index => $profile): ?>
                            <?php
                            $interests = json_decode($profile['interests'] ?? '[]', true) ?: [];
                            $age = $profile['age'] ? ", {$profile['age']}" : '';
                            $lastActive = new DateTime($profile['last_active']);
                            $now = new DateTime();
                            $interval = $now->diff($lastActive);
                            
                            if ($interval->days == 0) {
                                $activeText = "Active today";
                            } elseif ($interval->days == 1) {
                                $activeText = "Active yesterday";
                            } else {
                                $activeText = "Active {$interval->days} days ago";
                            }
                            ?>
                            <div class="swipe-card" data-user-id="<?php echo $profile['id']; ?>" style="z-index: <?php echo count($availableProfiles) - $index; ?>;">
                                <div class="profile-card">
                                    <div class="profile-image" style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: 1; overflow: hidden; border-radius: 25px;">
                                        <?php if ($profile['profile_image']): ?>
                                            <img src="<?php echo htmlspecialchars($profile['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;">
                                        <?php else: 
                                            // Generate a unique seed based on user ID for consistent images
                                            $seed = $profile['id'] * 123 + 456;
                                            $imageUrl = "https://picsum.photos/seed/{$seed}/400/600";
                                        ?>
                                            <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                                        <?php endif; ?>
                                        <!-- Subtle shimmer effect -->
                                        <div class="profile-card-shimmer"></div>
                                    </div>
                                    <!-- Enhanced overlay for better text readability -->
                                    <div style="
                                        position: absolute; 
                                        bottom: 0; 
                                        left: 0; 
                                        right: 0; 
                                        background: linear-gradient(
                                            transparent 0%, 
                                            rgba(0,0,0,0.1) 20%,
                                            rgba(0,0,0,0.4) 50%,
                                            rgba(0,0,0,0.8) 100%
                                        ); 
                                        height: 65%; 
                                        pointer-events: none;
                                        backdrop-filter: blur(1px);
                                    "></div>
                                    
                                    <div class="profile-info" style="
                                        position: absolute; 
                                        bottom: 0; 
                                        left: 0; 
                                        right: 0; 
                                        padding: 2.5rem 2rem 2rem; 
                                        color: white; 
                                        z-index: 2;
                                    ">
                                        <div class="profile-name" style="
                                            font-size: 2.2rem; 
                                            font-weight: 800; 
                                            margin-bottom: 0.75rem; 
                                            text-shadow: 0 3px 6px rgba(0,0,0,0.6);
                                            letter-spacing: -0.5px;
                                            line-height: 1.1;
                                        "><?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?><span style="font-weight: 400; opacity: 0.9;"><?php echo $age; ?></span></div>
                                        <div class="profile-details" style="
                                            font-size: 1.1rem; 
                                            margin-bottom: 1rem; 
                                            opacity: 0.95; 
                                            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
                                            font-weight: 500;
                                            display: flex;
                                            align-items: center;
                                            gap: 0.5rem;
                                        ">
                                            <span style="
                                                background: rgba(255,255,255,0.2); 
                                                backdrop-filter: blur(10px);
                                                padding: 0.3rem 0.8rem;
                                                border-radius: 15px;
                                                font-size: 0.9rem;
                                                border: 1px solid rgba(255,255,255,0.3);
                                            ">📍 <?php echo htmlspecialchars($profile['location'] ?? 'Location not specified', ENT_QUOTES, 'UTF-8'); ?></span>
                                            <span style="
                                                background: rgba(76, 217, 100, 0.3);
                                                backdrop-filter: blur(10px);
                                                padding: 0.3rem 0.8rem;
                                                border-radius: 15px;
                                                font-size: 0.9rem;
                                                border: 1px solid rgba(76, 217, 100, 0.4);
                                            ">🟢 <?php echo $activeText; ?></span>
                                        </div>
                                        <?php if ($profile['bio']): ?>
                                            <div class="profile-bio" style="
                                                font-size: 1rem; 
                                                line-height: 1.5; 
                                                margin-bottom: 1.25rem; 
                                                opacity: 0.95; 
                                                text-shadow: 0 2px 4px rgba(0,0,0,0.5);
                                                font-weight: 400;
                                                max-height: 3.5rem;
                                                overflow: hidden;
                                                display: -webkit-box;
                                                -webkit-line-clamp: 2;
                                                -webkit-box-orient: vertical;
                                            "><?php echo htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($interests)): ?>
                                            <div class="tags-container" style="display: flex; flex-wrap: wrap; gap: 0.6rem; max-height: 4rem; overflow: hidden;">
                                                <?php 
                                                $tagColors = [
                                                    'rgba(255, 107, 157, 0.3)',
                                                    'rgba(168, 230, 207, 0.3)', 
                                                    'rgba(102, 126, 234, 0.3)',
                                                    'rgba(255, 193, 77, 0.3)',
                                                    'rgba(255, 121, 121, 0.3)'
                                                ];
                                                foreach (array_slice($interests, 0, 4) as $index => $interest): 
                                                    $bgColor = $tagColors[$index % count($tagColors)];
                                                ?>
                                                    <span class="interest-tag-premium" style="
                                                        background: <?php echo $bgColor; ?>; 
                                                        backdrop-filter: blur(15px); 
                                                        border: 1px solid rgba(255,255,255,0.4); 
                                                        color: white; 
                                                        padding: 0.5rem 1rem; 
                                                        border-radius: 25px; 
                                                        font-size: 0.9rem; 
                                                        font-weight: 600;
                                                        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                                                        transition: all 0.3s ease;
                                                        cursor: pointer;
                                                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                                    " 
                                                    onmouseover="this.style.transform='translateY(-2px) scale(1.05)'; this.style.boxShadow='0 4px 15px rgba(0,0,0,0.3)';" 
                                                    onmouseout="this.style.transform='translateY(0) scale(1)'; this.style.boxShadow='0 2px 8px rgba(0,0,0,0.2)';"><?php echo htmlspecialchars($interest, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($interests) > 4): ?>
                                                    <span style="
                                                        background: rgba(255,255,255,0.2); 
                                                        backdrop-filter: blur(15px); 
                                                        border: 1px solid rgba(255,255,255,0.4); 
                                                        color: white; 
                                                        padding: 0.5rem 1rem; 
                                                        border-radius: 25px; 
                                                        font-size: 0.9rem; 
                                                        font-weight: 600;
                                                        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
                                                        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                                                    ">+<?php echo count($interests) - 4; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Swipe Actions -->
                <div class="swipe-actions">
                    <button class="btn btn-icon btn-secondary" id="passBtn" data-action="pass">
                        ❌
                    </button>
                    <button class="btn btn-icon btn-primary" id="likeBtn" data-action="like">
                        ❤️
                    </button>
                </div>

                <!-- Instructions -->
                <div class="text-center mt-lg">
                    <small class="text-secondary">
                        Swipe left to pass, right to like<br>
                        Or use the buttons below
                    </small>
                </div>
            <?php endif; ?>
        </div>

        <!-- Matches Tab -->
        <div id="matches-tab" class="tab-content hidden">
            <?php if (empty($userMatches)): ?>
                <div class="card text-center">
                    <div class="card-body">
                        <h3>No matches yet</h3>
                        <p class="text-secondary">Start swiping to find your matches!</p>
                        <button class="btn btn-primary mt-md" data-tab="discover">Discover People</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="grid grid-md-2" style="gap: 1rem;">
                    <?php foreach ($userMatches as $match): ?>
                        <?php
                        $interests = json_decode($match['interests'] ?? '[]', true) ?: [];
                        $age = $match['age'] ? ", {$match['age']}" : '';
                        ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="flex items-center" style="gap: 1rem;">
                                    <div class="profile-image" style="width: 60px; height: 60px; flex-shrink: 0;">
                                        <?php if ($match['profile_image']): ?>
                                            <img src="<?php echo htmlspecialchars($match['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($match['username'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--border-radius-full);">
                                        <?php else: 
                                            // Generate a unique seed based on user ID for consistent images
                                            $seed = $match['id'] * 123 + 456;
                                            $imageUrl = "https://picsum.photos/seed/{$seed}/200/200";
                                        ?>
                                            <img src="<?php echo $imageUrl; ?>" alt="<?php echo htmlspecialchars($match['username'], ENT_QUOTES, 'UTF-8'); ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: var(--border-radius-full);">
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1">
                                        <h3 style="margin: 0; font-size: var(--font-size-lg);"><?php echo htmlspecialchars($match['username'], ENT_QUOTES, 'UTF-8'); ?><?php echo $age; ?></h3>
                                        <p class="text-secondary" style="margin: 0; font-size: var(--font-size-sm);">
                                            <?php echo htmlspecialchars($match['location'] ?? 'Location not specified', ENT_QUOTES, 'UTF-8'); ?>
                                        </p>
                                        <small class="text-secondary">
                                            Matched <?php echo date('M j', strtotime($match['match_date'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php if ($match['bio']): ?>
                                    <p class="mt-sm" style="font-size: var(--font-size-sm);"><?php echo htmlspecialchars($match['bio'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($interests)): ?>
                                    <div class="tags-container mt-sm">
                                        <?php foreach (array_slice($interests, 0, 3) as $interest): ?>
                                            <span class="tag tag-secondary"><?php echo htmlspecialchars($interest, ENT_QUOTES, 'UTF-8'); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-md">
                                    <button class="btn btn-primary btn-small" onclick="alert('Messaging feature coming soon!')">
                                        💬 Message
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/app.js"></script>
    <script>
        // Tab functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetTab = btn.dataset.tab;
                
                // Update tab buttons
                document.querySelectorAll('.tab-btn').forEach(b => {
                    b.classList.remove('active');
                    b.style.background = 'transparent';
                    b.style.color = 'var(--text-secondary)';
                });
                btn.classList.add('active');
                btn.style.background = 'var(--primary-color)';
                btn.style.color = 'white';
                
                // Update tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                });
                document.getElementById(targetTab + '-tab').classList.remove('hidden');
            });
        });

        // Swipe functionality
        let currentProfileIndex = 0;
        const profileStack = document.getElementById('profileStack');
        const profiles = profileStack ? profileStack.querySelectorAll('.swipe-card') : [];

        function getCurrentProfile() {
            return profiles[currentProfileIndex];
        }

        function nextProfile() {
            const currentCard = getCurrentProfile();
            if (currentCard) {
                currentCard.style.display = 'none';
                currentProfileIndex++;
                
                if (currentProfileIndex >= profiles.length) {
                    // No more profiles
                    profileStack.innerHTML = `
                        <div class="card text-center" style="margin: 2rem auto; max-width: 400px;">
                            <div class="card-body">
                                <h3>That's everyone!</h3>
                                <p class="text-secondary">Check back later for new profiles</p>
                                <button class="btn btn-primary mt-md" onclick="location.reload()">Refresh</button>
                            </div>
                        </div>
                    `;
                    document.querySelector('.swipe-actions').style.display = 'none';
                }
            }
        }

        async function handleAction(action) {
            const currentCard = getCurrentProfile();
            if (!currentCard) return;

            const userId = currentCard.dataset.userId;
            
            try {
                const response = await Http.post(window.location.href, {
                    action: action,
                    user_id: userId,
                    csrf_token: Utils.getCSRFToken()
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.match) {
                        Flash.success("🎉 It's a match!");
                        // Add celebration effect
                        confettiEffect();
                    } else if (action === 'like') {
                        Flash.info('Profile liked!');
                    }
                    
                    // Animate card out
                    animateCardOut(currentCard, action);
                    setTimeout(nextProfile, 300);
                } else {
                    Flash.error(result.message || 'Action failed');
                }
            } catch (error) {
                Flash.error('Network error. Please try again.');
                console.error('Action error:', error);
            }
        }

        function animateCardOut(card, action) {
            const direction = action === 'like' ? 'right' : 'left';
            const translateX = direction === 'right' ? '100vw' : '-100vw';
            const rotation = direction === 'right' ? '30deg' : '-30deg';
            
            card.style.transition = 'transform 0.3s ease-out, opacity 0.3s ease-out';
            card.style.transform = `translate(-50%, -50%) translateX(${translateX}) rotate(${rotation})`;
            card.style.opacity = '0';
        }

        function confettiEffect() {
            // Simple confetti effect
            const colors = ['#ff6b7a', '#4ecdc4', '#45b7d1', '#f39c12', '#e74c3c'];
            
            for (let i = 0; i < 50; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.cssText = `
                        position: fixed;
                        top: -10px;
                        left: ${Math.random() * 100}vw;
                        width: 10px;
                        height: 10px;
                        background: ${colors[Math.floor(Math.random() * colors.length)]};
                        z-index: 10000;
                        animation: confetti-fall 3s linear forwards;
                        border-radius: 50%;
                    `;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), 3000);
                }, i * 50);
            }
        }

        // Add confetti animation CSS
        const style = document.createElement('style');
        style.textContent = `
            @keyframes confetti-fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);

        // Button event listeners
        document.getElementById('passBtn')?.addEventListener('click', () => handleAction('pass'));
        document.getElementById('likeBtn')?.addEventListener('click', () => handleAction('like'));

        // Swipe gesture handling
        if (profileStack) {
            SwipeHandler.init(profileStack, {
                onSwipeLeft: () => handleAction('pass'),
                onSwipeRight: () => handleAction('like'),
                onDragMove: ({ deltaX }) => {
                    const currentCard = getCurrentProfile();
                    if (currentCard) {
                        const rotation = deltaX * 0.02;
                        const opacity = 1 - Math.abs(deltaX) * 0.001;
                        currentCard.style.transform = `translate(-50%, -50%) translateX(${deltaX * 0.5}px) rotate(${rotation}deg)`;
                        currentCard.style.opacity = Math.max(0.3, opacity);
                    }
                },
                onDragEnd: () => {
                    const currentCard = getCurrentProfile();
                    if (currentCard) {
                        currentCard.style.transition = 'transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1), opacity 0.3s ease-out';
                        currentCard.style.transform = 'translate(-50%, -50%)';
                        currentCard.style.opacity = '1';
                        setTimeout(() => {
                            if (currentCard && currentCard.style.transform === 'translate(-50%, -50%)') {
                                currentCard.style.transition = '';
                            }
                        }, 300);
                    }
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                handleAction('pass');
            } else if (e.key === 'ArrowRight') {
                handleAction('like');
            }
        });
    </script>
</body>
</html>