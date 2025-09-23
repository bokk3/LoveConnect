# � LoveConnect - Mobile-First Dating Web App

A modern, secure dating web application built with PHP 8.2, featuring a mobile-first responsive design, comprehensive matching system, and advanced security features. Evolved from a secure login system into a full-featured dating platform.

## 🌟 Features

### Core Dating Functionality
- **User Authentication** - Secure login/registration with Argon2ID password hashing
- **Profile Management** - Comprehensive user profiles with bio, interests, and dating preferences
- **Smart Matching** - Advanced matching algorithm based on age, location, gender preferences, and interests
- **Swipe Interface** - Touch-friendly swipe gestures for mobile devices
- **Real-time Matching** - Instant mutual match detection with visual feedback
- **Session Management** - Secure session handling with automatic timeout

### Modern Architecture
- **Mobile-First Design** - Responsive CSS with touch-optimized interfaces
- **ES6 JavaScript** - Modern JavaScript modules with Fetch API
- **CSRF Protection** - Comprehensive security against cross-site request forgery
- **Docker Ready** - Complete containerization with nginx + PHP-FPM + MariaDB
- **Progressive Enhancement** - Works without JavaScript, enhanced with it

## 🏗️ Architecture

- **Backend**: PHP 8.2 with PDO for database operations
- **Database**: MariaDB 10.11 with dating-optimized schema (users, matches, messages)
- **Web Server**: nginx 1.25 with PHP-FPM
- **Frontend**: Mobile-first CSS framework with ES6 JavaScript modules
- **Containerization**: Docker Compose for easy deployment
- **Security**: Argon2ID password hashing, CSRF protection, session management

## 📁 Project Structure

```
dating-v2/
├── app/
│   ├── assets/
│   │   ├── style.css      # Mobile-first CSS framework
│   │   └── app.js         # ES6 JavaScript modules
│   ├── login.php          # Login/registration with dating app design
│   ├── admin.php          # Dating dashboard with stats and quick actions  
│   ├── profile.php        # Profile management with bio and preferences
│   ├── matches.php        # Discovery interface with swipe functionality
│   ├── logout.php         # Session destruction and logout
│   ├── db.php             # Database connection and utilities
│   ├── functions.php      # Common functions and security helpers
│   └── schema.sql         # Dating app database schema with sample profiles
├── docker-compose.yml     # Docker services configuration
├── Dockerfile             # Custom PHP container with extensions
├── nginx.conf             # nginx web server configuration
└── README.md              # This documentation
```

## ✨ Features

### 🔒 Security Features
- ✅ **Password Hashing**: Argon2ID algorithm with secure defaults
- ✅ **CSRF Protection**: Token-based protection for all forms
- ✅ **SQL Injection Protection**: Prepared statements for all database queries
- ✅ **Session Security**: Regenerated session IDs, secure cookies, timeout protection
- ✅ **Input Validation**: Comprehensive sanitization and validation
- ✅ **Rate Limiting**: Login attempt and registration rate limiting
- ✅ **Error Handling**: Proper error logging without information disclosure
- ✅ **Security Headers**: XSS protection, content type sniffing prevention, CSP
- ✅ **Role-Based Access**: Admin, editor, and user role system

### 🚀 Functional Features
- 🔐 **User Authentication**: Secure login with username/password
- 📝 **User Registration**: Self-service account creation
- 👤 **Session Management**: Database-backed sessions with sliding expiration
- 📊 **Admin Dashboard**: Protected area with session and user information
- 🚪 **Secure Logout**: Complete session destruction (cookie + database)
- 🔄 **Auto-cleanup**: Expired session removal
- 💬 **Flash Messages**: User feedback system for actions
- 📱 **Responsive Design**: Mobile-friendly interface
- 🔑 **Password Reset**: Scaffold for password recovery (demo)

### 👥 User Roles
- **Admin**: Full system access
- **Editor**: Content management access
- **User**: Basic user access

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose installed
- Port 8080 available for the web server
- Port 3307 available for MariaDB (optional, for external access)

### 1. Clone and Setup

```bash
# Navigate to project directory
cd /path/to/dating-v2

# Verify project structure
ls -la
# Should show: app/, docker-compose.yml, nginx.conf, Dockerfile, README.md
```

### 2. Build and Start Services

```bash
# Build custom PHP container and start all services
docker-compose up -d --build

# Check service status
docker-compose ps

# View logs (all services)
docker-compose logs -f

# View logs for specific service
docker-compose logs -f php-fpm
docker-compose logs -f nginx
docker-compose logs -f mariadb
```

### 3. Access Application

- **Web Application**: http://localhost:8080
- **Demo Dating Profiles**: 
  - **admin** / password123 (Admin user)
  - **alex_tech** / password123 (Software Developer, 28, interests: coding, hiking)
  - **sarah_artist** / password123 (Artist, 25, interests: art, music, travel)
  - **mike_chef** / password123 (Chef, 32, interests: cooking, wine, fitness)
  - **emma_doctor** / password123 (Doctor, 29, interests: medicine, yoga, reading)
  - **jordan_nb** / password123 (Writer, 26, interests: writing, coffee, films)

### 4. Database Access (Optional)

```bash
# Connect to MariaDB directly
docker exec -it login_mariadb mysql -u root -prootpassword login_system

# View tables
SHOW TABLES;

# Check users and roles
SELECT id, username, email, role, created_at FROM users;

# Check active sessions
SELECT s.id, u.username, u.role, s.created_at, s.last_activity 
FROM sessions s 
JOIN users u ON s.user_id = u.id 
WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE);
```

## 🧪 Testing the System

### 1. Test Authentication Flow

```bash
# Test successful login
curl -X POST http://localhost:8080/login.php \
  -d "username=admin&password=admin123&csrf_token=test" \
  -c cookies.txt -L

# Test failed login
curl -X POST http://localhost:8080/login.php \
  -d "username=admin&password=wrongpass&csrf_token=test" \
  -v

# Test registration
curl -X POST http://localhost:8080/register.php \
  -d "username=testuser&email=test@example.com&password=Test123&password_confirm=Test123&csrf_token=test" \
  -v
```

### 2. Test Session Protection

```bash
# Try accessing admin without session
curl http://localhost:8080/admin.php -L

# Access admin with valid session
curl http://localhost:8080/admin.php -b cookies.txt

# Test logout
curl http://localhost:8080/logout.php -b cookies.txt -L
```

### 3. Test Role-Based Access

1. Login as different users (admin, editor, user)
2. Observe different role badges and access levels
3. Test session management across different roles

## 🛠️ Development Workflow

### Database Migrations

If you need to update the database schema:

```bash
# Stop services
docker-compose down

# Remove database volume (WARNING: This deletes all data)
docker volume rm login_system_db

# Restart services (will recreate database)
docker-compose up -d --build
```

### Adding New Users

#### Via Registration Page
1. Go to http://localhost:8080/register.php
2. Fill out the form
3. New users get 'user' role by default

#### Via Database
```bash
# Connect to database
docker exec -it login_mariadb mysql -u root -prootpassword login_system

# Generate password hash
docker exec -it login_php php -r "echo password_hash('newpassword', PASSWORD_ARGON2ID);"

# Insert new user
INSERT INTO users (username, email, password_hash, role) VALUES 
('newuser', 'newuser@example.com', '$argon2id$...', 'user');
```

### Monitoring Sessions

```sql
-- Query active sessions with user details
SELECT 
    u.username,
    u.role,
    s.session_id,
    s.created_at,
    s.last_activity,
    TIMESTAMPDIFF(MINUTE, s.last_activity, NOW()) as minutes_idle
FROM sessions s 
JOIN users u ON s.user_id = u.id 
WHERE s.last_activity > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
ORDER BY s.last_activity DESC;

-- Clean up expired sessions manually
DELETE FROM sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 30 MINUTE);
```

### Application Logs

```bash
# PHP application logs
docker-compose logs php-fpm | grep "login\|error\|session"

# nginx access logs
docker-compose logs nginx | grep "POST\|login\|admin"

# Database logs
docker-compose logs mariadb | grep "error\|warning"

# All logs with timestamps
docker-compose logs -f -t
```

## � Configuration

### Environment Variables

The application supports the following environment variables (configured in `docker-compose.yml`):

```yaml
environment:
  - DB_HOST=mariadb              # Database host
  - DB_NAME=login_system         # Database name
  - DB_USER=root                 # Database user
  - DB_PASS=rootpassword         # Database password
  # PHP production settings
  - PHP_OPCACHE_ENABLE=1
  - PHP_MEMORY_LIMIT=256M
  - PHP_MAX_EXECUTION_TIME=30
```

### Session Configuration

Edit `app/db.php` to modify session settings:

```php
define('SESSION_TIMEOUT', 30 * 60);        // 30 minutes
define('SESSION_COOKIE_NAME', 'login_session');
```

### Security Headers

nginx security headers are configured in `nginx.conf`:

```nginx
add_header X-Frame-Options DENY always;
add_header X-Content-Type-Options nosniff always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "strict-origin-when-cross-origin" always;
add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
add_header Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline';" always;
```

## 🚀 Production Deployment

### HTTPS Configuration

1. **Obtain SSL certificates** (Let's Encrypt, commercial CA, etc.)

2. **Update nginx.conf** - uncomment and configure the HTTPS server block:

```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    # SSL Configuration
    ssl_certificate /path/to/your/certificate.crt;
    ssl_certificate_key /path/to/your/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    
    # Rest of configuration...
}
```

3. **Update docker-compose.yml** to mount SSL certificates:

```yaml
nginx:
  volumes:
    - ./ssl:/etc/nginx/ssl:ro
    - ./app:/var/www/html
    - ./nginx.conf:/etc/nginx/nginx.conf:ro
  ports:
    - "80:80"
    - "443:443"
```

### Environment Security

1. **Change default passwords**:
```yaml
environment:
  - DB_PASS=your_secure_database_password
```

2. **Use environment files**:
```bash
# Create .env file
echo "DB_PASS=your_secure_password" > .env

# Update docker-compose.yml
env_file:
  - .env
```

3. **Restrict database access**:
```yaml
mariadb:
  ports: []  # Remove external port exposure
```

### Performance Tuning

1. **nginx optimization** (edit `nginx.conf`):
```nginx
worker_processes auto;
worker_connections 2048;
```

2. **MariaDB optimization** (edit `docker-compose.yml`):
```yaml
command: >
  --innodb-buffer-pool-size=512M
  --max-connections=200
  --innodb-log-file-size=256M
```

3. **PHP-FPM optimization**:
```yaml
environment:
  - PHP_FPM_PM_MAX_CHILDREN=50
  - PHP_FPM_PM_START_SERVERS=10
  - PHP_MEMORY_LIMIT=512M
```

## � Troubleshooting

### Common Issues

1. **Port conflicts**:
```bash
# Check what's using the ports
sudo netstat -tulpn | grep :8080
sudo netstat -tulpn | grep :3307

# Change ports in docker-compose.yml if needed
```

2. **Database connection fails**:
```bash
# Check MariaDB status
docker-compose ps mariadb

# View MariaDB logs
docker-compose logs mariadb

# Connect manually
docker exec -it login_mariadb mysql -u root -prootpassword
```

3. **PHP errors**:
```bash
# Check PHP-FPM logs
docker-compose logs php-fpm

# Enter PHP container for debugging
docker exec -it login_php sh

# Check PHP configuration
docker exec -it login_php php -i | grep -E "(pdo|session|opcache)"
```

4. **Permission issues**:
```bash
# Fix file permissions
sudo chown -R $USER:$USER app/
chmod -R 755 app/
```

5. **Session issues**:
```bash
# Clear all sessions
docker exec -it login_mariadb mysql -u root -prootpassword -e "DELETE FROM login_system.sessions;"

# Check session table
docker exec -it login_mariadb mysql -u root -prootpassword -e "SELECT * FROM login_system.sessions;"
```

### Development Tips

1. **Hot reload for development**:
```bash
# The app/ directory is mounted, so changes are immediate
# No need to rebuild containers for PHP/HTML/CSS changes
```

2. **Database reset**:
```bash
# Quick database reset
docker-compose down
docker volume rm login_system_db
docker-compose up -d
```

3. **View all environment variables**:
```bash
docker exec -it login_php env | grep -E "(DB_|PHP_)"
```

## 📊 Performance Metrics

Expected performance benchmarks:

- **Login requests**: ~2000 req/s (with opcache)
- **Session validation**: ~5000 req/s 
- **Database queries**: <5ms average
- **Memory usage**: ~100MB per container
- **SSL handshake**: <100ms

## 🔒 Security Considerations

### Production Checklist

- [ ] Change all default passwords
- [ ] Enable HTTPS with valid certificates
- [ ] Configure firewall rules
- [ ] Set up log monitoring and alerting
- [ ] Implement rate limiting at web server level
- [ ] Regular security updates
- [ ] Database backup strategy
- [ ] Remove development/demo accounts
- [ ] Configure proper error pages
- [ ] Set up intrusion detection

### Security Features Summary

1. **Authentication Security**:
   - Argon2ID password hashing
   - Rate limiting on login attempts
   - Session regeneration on login
   - Secure session management

2. **Input Security**:
   - CSRF token protection
   - Input sanitization and validation
   - Prepared statements for SQL injection protection
   - XSS protection headers

3. **Session Security**:
   - Sliding session expiration
   - Secure cookie settings
   - Database-backed sessions
   - Automatic cleanup of expired sessions

4. **Infrastructure Security**:
   - nginx security headers
   - Rate limiting capabilities
   - Error logging without information disclosure
   - Container isolation

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Test your changes thoroughly
4. Update documentation as needed
5. Submit a pull request

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

---

**⚠️ Important Security Notes**:
- This system includes comprehensive security measures but should be reviewed by security professionals before production use
- For high-security applications, consider implementing additional measures such as:
  - Multi-factor authentication
  - Account lockout policies
  - Advanced intrusion detection
  - Security monitoring and alerts
  - Regular security audits

**🎯 Demo Status**: This is a fully functional system suitable for learning and development. For production use, customize according to your specific security requirements.