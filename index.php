<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>💕 LoveConnect - Find Your Perfect Match</title>
    <link rel="stylesheet" href="app/assets/style.css">
    <style>
        :root {
            --primary-pink: #ff6b9d;
            --primary-pink-dark: #e55a8a;
            --secondary-purple: #a8e6cf;
            --background-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --text-light: #ffffff;
            --text-dark: #2d3748;
            --shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            line-height: 1.6;
            color: var(--text-dark);
        }

        .hero-section {
            background: var(--background-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--text-light);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><radialGradient id="g" cx="50%" cy="50%" r="50%"><stop offset="0%" stop-color="rgba(255,255,255,0.1)"/><stop offset="100%" stop-color="rgba(255,255,255,0)"/></radialGradient></defs><circle cx="20" cy="20" r="10" fill="url(%23g)"/><circle cx="80" cy="30" r="8" fill="url(%23g)"/><circle cx="30" cy="70" r="12" fill="url(%23g)"/><circle cx="70" cy="80" r="6" fill="url(%23g)"/></svg>') repeat;
            opacity: 0.3;
            animation: float 20s infinite linear;
        }

        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
            100% { transform: translateY(0px) rotate(360deg); }
        }

        .hero-content {
            max-width: 800px;
            padding: 2rem;
            z-index: 1;
            position: relative;
        }

        .hero-title {
            font-size: 4rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(45deg, #ffffff, #f8f9ff);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .hero-subtitle {
            font-size: 1.5rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            font-weight: 300;
        }

        .hero-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--primary-pink);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-pink-dark);
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(255, 107, 157, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .feature {
            background: rgba(255, 255, 255, 0.1);
            padding: 2rem;
            border-radius: 20px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: transform 0.3s ease;
        }

        .feature:hover {
            transform: translateY(-5px);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .feature-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .feature-description {
            opacity: 0.8;
            font-size: 1rem;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
            padding-top: 3rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .stat {
            text-align: center;
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 800;
            color: var(--primary-pink);
            display: block;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            font-size: 1rem;
            opacity: 0.8;
        }

        .floating-hearts {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
        }

        .heart {
            position: absolute;
            color: rgba(255, 255, 255, 0.1);
            font-size: 2rem;
            animation: floatHeart 8s infinite ease-in-out;
        }

        @keyframes floatHeart {
            0%, 100% { 
                transform: translateY(100vh) scale(0); 
                opacity: 0; 
            }
            10%, 90% { 
                opacity: 1; 
            }
            50% { 
                transform: translateY(-10vh) scale(1); 
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
    </style>
</head>
<body>
    <main class="hero-section">
        <div class="floating-hearts">
            <span class="heart" style="left: 10%; animation-delay: 0s;">💕</span>
            <span class="heart" style="left: 20%; animation-delay: 2s;">💖</span>
            <span class="heart" style="left: 30%; animation-delay: 4s;">💗</span>
            <span class="heart" style="left: 40%; animation-delay: 6s;">💝</span>
            <span class="heart" style="left: 50%; animation-delay: 1s;">❤️</span>
            <span class="heart" style="left: 60%; animation-delay: 3s;">💜</span>
            <span class="heart" style="left: 70%; animation-delay: 5s;">💙</span>
            <span class="heart" style="left: 80%; animation-delay: 7s;">🧡</span>
            <span class="heart" style="left: 90%; animation-delay: 1.5s;">💚</span>
        </div>

        <div class="hero-content">
            <h1 class="hero-title">💕 LoveConnect</h1>
            <p class="hero-subtitle">
                Discover meaningful connections and find your perfect match. 
                Join thousands of people finding love every day.
            </p>
            
            <div class="hero-buttons">
                <a href="app/register.php" class="btn btn-primary">💖 Get Started Free</a>
                <a href="app/login.php" class="btn btn-secondary">🔐 Sign In</a>
            </div>

            <div class="features">
                <div class="feature">
                    <div class="feature-icon">🎯</div>
                    <h3 class="feature-title">Smart Matching</h3>
                    <p class="feature-description">Our advanced algorithm finds your perfect match based on compatibility, interests, and preferences.</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">💬</div>
                    <h3 class="feature-title">Real-time Chat</h3>
                    <p class="feature-description">Connect instantly with your matches through our secure, real-time messaging system.</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">🔒</div>
                    <h3 class="feature-title">Safe & Secure</h3>
                    <p class="feature-description">Your privacy and security are our top priority. All data is encrypted and protected.</p>
                </div>
                
                <div class="feature">
                    <div class="feature-icon">✨</div>
                    <h3 class="feature-title">Verified Profiles</h3>
                    <p class="feature-description">Meet real people with verified profiles. No fake accounts, no catfishing.</p>
                </div>
            </div>

            <div class="stats">
                <div class="stat">
                    <span class="stat-number">1M+</span>
                    <span class="stat-label">Happy Couples</span>
                </div>
                <div class="stat">
                    <span class="stat-number">5M+</span>
                    <span class="stat-label">Active Users</span>
                </div>
                <div class="stat">
                    <span class="stat-number">50K+</span>
                    <span class="stat-label">Daily Matches</span>
                </div>
                <div class="stat">
                    <span class="stat-number">98%</span>
                    <span class="stat-label">Success Rate</span>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Add some interactive floating hearts
        function createFloatingHeart() {
            const heart = document.createElement('span');
            heart.innerHTML = ['💕', '💖', '💗', '💝', '❤️', '💜', '💙', '🧡', '💚'][Math.floor(Math.random() * 9)];
            heart.className = 'heart';
            heart.style.left = Math.random() * 100 + '%';
            heart.style.animationDelay = Math.random() * 8 + 's';
            heart.style.animationDuration = (Math.random() * 3 + 5) + 's';
            
            document.querySelector('.floating-hearts').appendChild(heart);
            
            // Remove heart after animation
            setTimeout(() => {
                heart.remove();
            }, 8000);
        }

        // Create floating hearts periodically
        setInterval(createFloatingHeart, 2000);

        // Add entrance animations
        document.addEventListener('DOMContentLoaded', () => {
            const heroContent = document.querySelector('.hero-content');
            heroContent.style.opacity = '0';
            heroContent.style.transform = 'translateY(30px)';
            
            setTimeout(() => {
                heroContent.style.transition = 'all 1s ease';
                heroContent.style.opacity = '1';
                heroContent.style.transform = 'translateY(0)';
            }, 500);
        });
    </script>
</body>
</html>