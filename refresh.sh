#!/usr/bin/env bash

# get the user that owns our app here
APP_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/site"
APP_USER=$(stat -c '%U' "$APP_PATH")

# make sure we own it
chown -R $APP_USER:$APP_USER $APP_PATH*;

export COMPOSER_ALLOW_SUPERUSER=1;

# update all packages
composer -d $APP_PATH update;

# dump the composer autoloader and force it to regenerate
composer -d $APP_PATH dumpautoload -o -n;

# Cleanup First
php $APP_PATH/vendor/kevinpirnie/kpt-cache/src/cache/cleaner.php --cleanup

# Full system cleanup
php $APP_PATH/vendor/kevinpirnie/kpt-cache/src/cache/cleaner.php --clear_all
rm -rf /tmp/kpt_* $APP_PATH/.cache/*

rm -rf $APP_PATH/node_modules
npm install --prefix "$APP_PATH"
npm run build --prefix "$APP_PATH"

# just inn case php is caching
service php8.4-fpm restart && service nginx restart

# clear out our redis cache
redis-cli flushall

# make sure we own it one last time
chown -R $APP_USER:$APP_USER $APP_PATH*;

# reset permissions
find $APP_PATH -type d -exec chmod 755 {} \;
find $APP_PATH -type f -exec chmod 644 {} \;
chmod +x refresh.sh

# podman build --network=host -t ghcr.io/kpirnie/kptv-app:latest .
# podman push ghcr.io/kpirnie/kptv-app:latest
