#!/bin/sh
set -e

# Read database credentials from config.json
DB_SCHEMA=$(grep -o '"schema": *"[^"]*"' /var/www/html/assets/config.json | cut -d'"' -f4)
DB_USER=$(grep -o '"username": *"[^"]*"' /var/www/html/assets/config.json | cut -d'"' -f4)
DB_PASS=$(grep -o '"password": *"[^"]*"' /var/www/html/assets/config.json | cut -d'"' -f4)

# Use defaults if empty
DB_SCHEMA=${DB_SCHEMA:-kptv}
DB_USER=${DB_USER:-kptv}
DB_PASS=${DB_PASS:-kptv123}

# Initialize MySQL if needed
if [ ! -d "/var/lib/mysql/mysql" ]; then
    echo "Initializing MySQL database..."
    mysql_install_db --user=mysql --datadir=/var/lib/mysql
    
    mysqld --user=mysql --datadir=/var/lib/mysql &
    MYSQL_PID=$!
    sleep 5
    
    mysql -e "CREATE DATABASE IF NOT EXISTS ${DB_SCHEMA};"
    mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_SCHEMA}.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    echo "Importing database schema..."
    mysql ${DB_SCHEMA} < /var/www/html/assets/schema.sql
    
    kill $MYSQL_PID
    wait $MYSQL_PID
fi

# Start all services
echo "Starting services..."
mysqld --user=mysql --datadir=/var/lib/mysql &
crond -f -l 2 &
php-fpm &
exec nginx -g 'daemon off;'