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
    
    # Start MySQL temporarily
    mysqld_safe --datadir=/var/lib/mysql --user=mysql &
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
    # Set up database and user using environment variables
    mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'%' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'%';"
    mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Import schema if it exists
    if [ -f "/var/www/html/schema.sql" ]; then
        echo "Importing database schema..."
        mysql "${DB_NAME}" < /var/www/html/schema.sql
    fi
    
    # Stop temporary MySQL
    echo "Database initialization complete. Stopping temporary MySQL..."
    kill $MYSQL_PID
    wait $MYSQL_PID
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