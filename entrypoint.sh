#!/bin/sh
set -e

# Suppress all output unless DEBUG is enabled
if [ "${DEBUG:-false}" != "true" ]; then
    exec > /dev/null 2>&1
fi

CONFIG_FILE="/var/www/html/config.json"
SQLITE_SCHEMA_FILE="/schema.sqlite.sql"
DEFAULT_SQLITE_DB="/var/lib/data/kptv.sqlite"

read_json_value() {
    key="$1"
    awk -v k="\"$key\"" 'index($0,k){match($0, /:[[:space:]]*"([^"]*)"/, a); if (a[1] != "") {print a[1]; exit}}' "$CONFIG_FILE"
}

DB_DRIVER=$(read_json_value "driver")
DB_DRIVER=${DB_DRIVER:-sqlite}

if [ "$DB_DRIVER" = "sqlite" ]; then
    SQLITE_PATH=$(read_json_value "sqlite_path")

    if [ -z "$SQLITE_PATH" ]; then
        SQLITE_DB="$DEFAULT_SQLITE_DB"
    else
        case "$SQLITE_PATH" in
            /*) SQLITE_DB="$SQLITE_PATH" ;;
            *) SQLITE_DB="/var/www/html/${SQLITE_PATH#./}" ;;
        esac
    fi

    SQLITE_DIR=$(dirname "$SQLITE_DB")
    mkdir -p "$SQLITE_DIR"

    if [ ! -f "$SQLITE_DB" ]; then
        sqlite3 "$SQLITE_DB" < "$SQLITE_SCHEMA_FILE"
    fi

    chown -R www-data:www-data "$SQLITE_DIR" || true
fi

# Start all services
crond -f -l 2 &
php-fpm &
exec nginx -g 'daemon off;'
