<?php
/**
 * User Profile Page - Mobile-First Dating App
 * Editable profile with bio, interests, gender, and preferences
 */

require_once 'db.php';
require_once 'functions.php';

// Start secure session and check authentication
startSecureSession();
requireLogin();

$error = '';
$success = '';
$currentUser = null;

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
    error_log("Profile load error: " . $e->getMessage());
    $error = 'Failed to load profile data.';
}

// Process profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Security token validation failed.';
    } else {
        switch ($_POST['action']) {
            case 'update_profile':
                $error = updateUserProfile();
                break;
            case 'update_preferences':
                $error = updateUserPreferences();
                break;
            case 'update_theme':
                $error = updateUserTheme();
                break;
        }
        
        if (empty($error)) {
            $success = 'Profile updated successfully!';
            // Reload user data
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $currentUser = $stmt->fetch();
        }
    }
}

/**
 * Update user profile information
 * @return string Error message or empty string on success
 */
function updateUserProfile(): string {
    try {
        // Validate and sanitize input
        $bio = trim($_POST['bio'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $age = (int)($_POST['age'] ?? 0);
        $location = trim($_POST['location'] ?? '');
        $interests = array_filter(array_map('trim', explode(',', $_POST['interests'] ?? '')));
        
        // Validation
        if (strlen($bio) > 500) {
            return 'Bio must be 500 characters or less.';
        }
        
        if (!in_array($gender, ['male', 'female', 'non-binary', 'prefer-not-to-say'])) {
            return 'Invalid gender selection.';
        }
        
        if ($age < 18 || $age > 120) {
            return 'Age must be between 18 and 120.';
        }
        
        if (strlen($location) > 100) {
            return 'Location must be 100 characters or less.';
        }
        
        if (count($interests) > 10) {
            return 'Maximum 10 interests allowed.';
        }
        
        // Clean interests
        $interests = array_slice(array_unique($interests), 0, 10);
        $interestsJson = json_encode($interests);
        
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            UPDATE users 
            SET bio = ?, gender = ?, age = ?, location = ?, interests = ?, updated_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            $bio,
            $gender,
            $age,
            $location,
            $interestsJson,
            $_SESSION['user_id']
        ]);
        
        return '';
    } catch (Exception $e) {
        error_log("Profile update error: " . $e->getMessage());
        return 'Failed to update profile. Please try again.';
    }
}

/**
 * Update user dating preferences
 * @return string Error message or empty string on success
 */
function updateUserPreferences(): string {
    try {
        $lookingFor = $_POST['looking_for'] ?? [];
        $ageMin = (int)($_POST['age_min'] ?? 18);
        $ageMax = (int)($_POST['age_max'] ?? 100);
        $maxDistance = (int)($_POST['max_distance'] ?? 50);
        
        // Validation
        if (!is_array($lookingFor)) {
            return 'Invalid preferences selection.';
        }
        
        $validGenders = ['male', 'female', 'non-binary'];
        $lookingFor = array_intersect($lookingFor, $validGenders);
        
        if ($ageMin < 18 || $ageMin > 120 || $ageMax < 18 || $ageMax > 120 || $ageMin > $ageMax) {
            return 'Invalid age range.';
        }
        
        if ($maxDistance < 1 || $maxDistance > 500) {
            return 'Distance must be between 1 and 500 km.';
        }
        
        $lookingForJson = json_encode($lookingFor);
        
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            UPDATE users 
            SET looking_for = ?, age_min = ?, age_max = ?, max_distance = ?, updated_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            $lookingForJson,
            $ageMin,
            $ageMax,
            $maxDistance,
            $_SESSION['user_id']
        ]);
        
        return '';
    } catch (Exception $e) {
        error_log("Preferences update error: " . $e->getMessage());
        return 'Failed to update preferences. Please try again.';
    }
}

/**
 * Update user theme preference
 * @return string Error message or empty string on success
 */
function updateUserTheme(): string {
    try {
        $theme = $_POST['theme'] ?? '';
        
        // Validation
        if (!in_array($theme, ['light', 'dark'])) {
            return 'Invalid theme selection.';
        }
        
        $darkMode = ($theme === 'dark') ? 1 : 0;
        
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('
            UPDATE users 
            SET dark_mode = ?, updated_at = NOW()
            WHERE id = ?
        ');
        
        $stmt->execute([
            $darkMode,
            $_SESSION['user_id']
        ]);
        
        // Return JSON response for AJAX calls
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Theme updated successfully']);
            exit;
        }
        
        return '';
    } catch (Exception $e) {
        error_log("Theme update error: " . $e->getMessage());
        
        // Return JSON response for AJAX calls
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Failed to update theme']);
            exit;
        }
        
        return 'Failed to update theme. Please try again.';
    }
}

// Parse user data for display
$userInterests = json_decode($currentUser['interests'] ?? '[]', true) ?: [];
$userLookingFor = json_decode($currentUser['looking_for'] ?? '[]', true) ?: [];
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en"<?php if (isset($currentUser['dark_mode']) && $currentUser['dark_mode']): ?> class="dark-theme"<?php endif; ?>>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Dating App</title>
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
                    <li><a href="matches.php" class="nav-link">Matches</a></li>
                    <li><a href="profile.php" class="nav-link active">Profile</a></li>
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
        
        <!-- Page Header -->
        <div class="text-center mt-lg mb-lg">
            <h1>My Profile</h1>
            <p class="text-secondary">Update your profile to find better matches</p>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($error)): ?>
            <div class="flash-message flash-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="flash-message flash-success"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <div class="grid grid-lg-2" style="gap: 2rem;">
            <!-- Profile Information -->
            <div class="card">
                <div class="card-header">
                    <h2>Profile Information</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="ajax-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <!-- Profile Picture Placeholder -->
                        <div class="form-group">
                            <label class="form-label">Profile Photo</label>
                            <div class="profile-image" style="width: 120px; height: 120px; margin: 0 auto; display: flex; align-items: center; justify-content: center; background: var(--gradient-primary); border-radius: var(--border-radius-full); color: white; font-size: 3rem; font-weight: bold;">
                                <?php echo strtoupper(substr($currentUser['username'], 0, 1)); ?>
                            </div>
                            <small class="text-secondary">Photo upload feature coming soon!</small>
                        </div>

                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" class="form-input" value="<?php echo htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            <small class="text-secondary">Username cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" class="form-input" value="<?php echo htmlspecialchars($currentUser['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled>
                            <small class="text-secondary">Email cannot be changed</small>
                        </div>

                        <div class="form-group">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select" required>
                                <option value="male" <?php echo $currentUser['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $currentUser['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="non-binary" <?php echo $currentUser['gender'] === 'non-binary' ? 'selected' : ''; ?>>Non-binary</option>
                                <option value="prefer-not-to-say" <?php echo $currentUser['gender'] === 'prefer-not-to-say' ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="age" class="form-label">Age</label>
                            <input type="number" id="age" name="age" min="18" max="120" class="form-input" value="<?php echo htmlspecialchars($currentUser['age'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="location" class="form-label">Location</label>
                            <input type="text" id="location" name="location" class="form-input" placeholder="City, State" value="<?php echo htmlspecialchars($currentUser['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" maxlength="100">
                        </div>

                        <div class="form-group">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea id="bio" name="bio" class="form-textarea" placeholder="Tell people about yourself..." maxlength="500"><?php echo htmlspecialchars($currentUser['bio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <small class="text-secondary">Max 500 characters</small>
                        </div>

                        <div class="form-group">
                            <label for="interests" class="form-label">Interests</label>
                            <input type="text" id="interests" name="interests" class="form-input" placeholder="hiking, movies, cooking (separate with commas)" value="<?php echo htmlspecialchars(implode(', ', $userInterests), ENT_QUOTES, 'UTF-8'); ?>">
                            <small class="text-secondary">Max 10 interests, separate with commas</small>
                            
                            <?php if (!empty($userInterests)): ?>
                                <div class="tags-container">
                                    <?php foreach ($userInterests as $interest): ?>
                                        <span class="tag"><?php echo htmlspecialchars($interest, ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">
                            Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Dating Preferences -->
            <div class="card">
                <div class="card-header">
                    <h2>Dating Preferences</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="" class="ajax-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="action" value="update_preferences">
                        
                        <div class="form-group">
                            <label class="form-label">Looking for</label>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                                    <input type="checkbox" name="looking_for[]" value="male" <?php echo in_array('male', $userLookingFor) ? 'checked' : ''; ?>>
                                    Men
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                                    <input type="checkbox" name="looking_for[]" value="female" <?php echo in_array('female', $userLookingFor) ? 'checked' : ''; ?>>
                                    Women
                                </label>
                                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: normal;">
                                    <input type="checkbox" name="looking_for[]" value="non-binary" <?php echo in_array('non-binary', $userLookingFor) ? 'checked' : ''; ?>>
                                    Non-binary people
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Age Range</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                                <div>
                                    <label for="age_min" class="form-label" style="font-size: 0.9rem;">Minimum Age</label>
                                    <input type="number" id="age_min" name="age_min" min="18" max="120" class="form-input" value="<?php echo htmlspecialchars($currentUser['age_min'] ?? '18', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                                <div>
                                    <label for="age_max" class="form-label" style="font-size: 0.9rem;">Maximum Age</label>
                                    <input type="number" id="age_max" name="age_max" min="18" max="120" class="form-input" value="<?php echo htmlspecialchars($currentUser['age_max'] ?? '100', ENT_QUOTES, 'UTF-8'); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="max_distance" class="form-label">Maximum Distance (km)</label>
                            <input type="range" id="max_distance" name="max_distance" min="1" max="500" value="<?php echo htmlspecialchars($currentUser['max_distance'] ?? '50', ENT_QUOTES, 'UTF-8'); ?>" oninput="this.nextElementSibling.textContent = this.value + ' km'">
                            <div style="text-align: center; margin-top: 0.5rem; font-weight: bold; color: var(--primary-color);">
                                <?php echo htmlspecialchars($currentUser['max_distance'] ?? '50', ENT_QUOTES, 'UTF-8'); ?> km
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">
                            Update Preferences
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Account Settings -->
        <div class="card mt-lg">
            <div class="card-header">
                <h2>Account Settings</h2>
            </div>
            <div class="card-body">
                <div class="grid grid-md-3" style="gap: 1rem;">
                    <div class="stat-item">
                        <span class="stat-label">Member since</span>
                        <span class="stat-value"><?php echo date('M Y', strtotime($currentUser['created_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Last updated</span>
                        <span class="stat-value"><?php echo date('M j, Y', strtotime($currentUser['updated_at'])); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Account status</span>
                        <span class="stat-value text-success">Active</span>
                    </div>
                </div>
                
                <div class="mt-lg text-center">
                    <button class="btn btn-danger" onclick="if(confirm('Are you sure you want to delete your account? This cannot be undone.')) { alert('Account deletion feature coming soon!'); }">
                        Delete Account
                    </button>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/app.js"></script>
    <script>
        // Update distance slider display
        document.getElementById('max_distance').addEventListener('input', function() {
            this.nextElementSibling.textContent = this.value + ' km';
        });

        // Character counter for bio
        const bioTextarea = document.getElementById('bio');
        const charCount = document.createElement('small');
        charCount.className = 'text-secondary';
        charCount.style.float = 'right';
        bioTextarea.parentNode.appendChild(charCount);

        function updateCharCount() {
            const remaining = 500 - bioTextarea.value.length;
            charCount.textContent = remaining + ' characters remaining';
            charCount.style.color = remaining < 50 ? 'var(--error-color)' : 'var(--text-secondary)';
        }

        bioTextarea.addEventListener('input', updateCharCount);
        updateCharCount();

        // Form submission handling
        document.querySelectorAll('.ajax-form').forEach(form => {
            form.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const success = await FormHandler.submit(form, {
                    onSuccess: (result) => {
                        Flash.success('Profile updated successfully!');
                        setTimeout(() => window.location.reload(), 1500);
                    },
                    onError: (error) => {
                        Flash.error(error.message || 'Update failed. Please try again.');
                    }
                });
            });
        });
    </script>
</body>
</html>