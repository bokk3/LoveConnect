#!/bin/sh
set -e

# Validate required environment variables
if [ -z "$DB_PASS" ]; then
    echo "ERROR: DB_PASS environment variable is required"
    exit 1
fi

# Set defaults
DB_HOST=${DB_HOST:-localhost}
DB_NAME=${DB_NAME:-login_system}
DB_USER=${DB_USER:-root}

echo "Starting LoveConnect Dating App..."
echo "Database: $DB_NAME on $DB_HOST"

# Initialize MySQL data directory if it doesn't exist
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "Initializing MariaDB..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql --auth-root-authentication-method=normal
    
    # Start MySQL temporarily with skip-grant-tables for initial setup
    mysqld_safe --datadir=/var/lib/mysql --user=mysql --skip-grant-tables &
    MYSQL_PID=$!
    
    # Wait for MySQL to be ready
    echo "Waiting for MySQL to start..."
    sleep 15
    
    # Test connection before proceeding
    retries=30
    while [ $retries -gt 0 ]; do
        if mysqladmin ping -h localhost --silent; then
            break
        fi
        echo "Waiting for MySQL to be ready... ($retries retries left)"
        sleep 2
        retries=$((retries-1))
    done
    
    if [ $retries -eq 0 ]; then
        echo "ERROR: MySQL failed to start"
        exit 1
    fi
    
    echo "Setting up database and user..."
    # First flush privileges to enable grant tables
    mysql -e "FLUSH PRIVILEGES;"
    # Create database and set root password
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
    mysql -e "SET PASSWORD FOR 'root'@'localhost' = PASSWORD('${DB_PASS}');"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO 'root'@'%' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Import schema if it exists
    if [ -f "/var/www/html/schema.sql" ]; then
        echo "Importing database schema..."
        mysql "${DB_NAME}" < /var/www/html/schema.sql
    fi
    
    # Stop temporary MySQL
    echo "Database initialization complete. Stopping temporary MySQL..."
    mysqladmin shutdown -h localhost
    sleep 5
else
    echo "Database already initialized"
fi

# Set proper permissions
chown -R nginx:nginx /var/www/html
chown -R mysql:mysql /var/lib/mysql
chmod -R 755 /var/www/html

echo "Starting all services..."
# Start supervisor
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf