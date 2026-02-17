#!/bin/sh
set -e

# Suppress all output unless DEBUG is enabled
if [ "${DEBUG:-false}" != "true" ]; then
    exec > /dev/null 2>&1
fi

# Read database credentials from config.json
DB_SCHEMA=$(sed -n '/"database":/,/"smtp":/p' /var/www/html/config.json | grep '"schema"' | head -1 | sed 's/.*"schema"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')
DB_USER=$(sed -n '/"database":/,/"smtp":/p' /var/www/html/config.json | grep '"username"' | head -1 | sed 's/.*"username"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')
DB_PASS=$(sed -n '/"database":/,/"smtp":/p' /var/www/html/config.json | grep '"password"' | head -1 | sed 's/.*"password"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')

# Use defaults if empty
DB_SCHEMA=${DB_SCHEMA:-kptv}
DB_USER=${DB_USER:-kptv}
DB_PASS=${DB_PASS:-kptv123}

# Initialize MySQL if needed
if [ ! -d "/var/lib/mysql/mysql" ]; then

    # make sure we have the right permissions on the data directory
    chown -R mysql:mysql /var/lib/mysql

    # Initialize the database
    /usr/bin/mariadb-install-db --user=mysql --datadir=/var/lib/mysql
    
    # Start MySQL in the background to set up the database and user
    /usr/bin/mariadbd --user=mysql --datadir=/var/lib/mysql &
    MYSQL_PID=$!
    sleep 5

    # create database and user with proper permissions    
    /usr/bin/mariadb -e "CREATE DATABASE IF NOT EXISTS ${DB_SCHEMA};"
    /usr/bin/mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
    /usr/bin/mariadb -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
    /usr/bin/mariadb -e "GRANT ALL PRIVILEGES ON ${DB_SCHEMA}.* TO '${DB_USER}'@'localhost';"
    /usr/bin/mariadb -e "GRANT ALL PRIVILEGES ON ${DB_SCHEMA}.* TO '${DB_USER}'@'127.0.0.1';"
    /usr/bin/mariadb -e "FLUSH PRIVILEGES;"
    
    # Import the schema
    /usr/bin/mariadb ${DB_SCHEMA} < /schema.sql
    
    # Stop MySQL after setup
    kill $MYSQL_PID
    wait $MYSQL_PID
fi

# make sure we have the right permissions on the data directory
chown -R mysql:mysql /var/lib/mysql

# Start all services
/usr/bin/mariadbd --user=mysql --datadir=/var/lib/mysql &
crond -f -l 2 &
php-fpm &
exec nginx -g 'daemon off;'