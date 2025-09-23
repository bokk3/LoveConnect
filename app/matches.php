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
                <div class="swipe-container">
                    <div class="profile-stack" id="profileStack">
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
                                    <div class="profile-image">
                                        <?php if ($profile['profile_image']): ?>
                                            <img src="<?php echo htmlspecialchars($profile['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php else: ?>
                                            <div class="profile-placeholder"><?php echo strtoupper(substr($profile['username'], 0, 1)); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="profile-info">
                                        <div class="profile-name"><?php echo htmlspecialchars($profile['username'], ENT_QUOTES, 'UTF-8'); ?><?php echo $age; ?></div>
                                        <div class="profile-details">
                                            <?php echo htmlspecialchars($profile['location'] ?? 'Location not specified', ENT_QUOTES, 'UTF-8'); ?> • <?php echo $activeText; ?>
                                        </div>
                                        <?php if ($profile['bio']): ?>
                                            <div class="profile-bio"><?php echo htmlspecialchars($profile['bio'], ENT_QUOTES, 'UTF-8'); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($interests)): ?>
                                            <div class="tags-container mt-sm">
                                                <?php foreach (array_slice($interests, 0, 5) as $interest): ?>
                                                    <span class="tag tag-secondary"><?php echo htmlspecialchars($interest, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endforeach; ?>
                                                <?php if (count($interests) > 5): ?>
                                                    <span class="tag tag-secondary">+<?php echo count($interests) - 5; ?> more</span>
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
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; background: var(--gradient-primary); border-radius: var(--border-radius-full); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 1.5rem;">
                                                <?php echo strtoupper(substr($match['username'], 0, 1)); ?>
                                            </div>
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
            card.style.transform = `translateX(${translateX}) rotate(${rotation})`;
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
                        currentCard.style.transform = `translateX(${deltaX * 0.1}px) rotate(${rotation}deg)`;
                        currentCard.style.opacity = Math.max(0.3, opacity);
                    }
                },
                onDragEnd: () => {
                    const currentCard = getCurrentProfile();
                    if (currentCard) {
                        currentCard.style.transform = '';
                        currentCard.style.opacity = '';
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