#!/bin/sh
# No `set -e`: we want nginx to start even if migrations fail, so the
# healthcheck can pass and the error shows in the browser instead of a
# generic "service unavailable".

echo "[start.sh] === Boot $(date) ==="
echo "[start.sh] PORT=$PORT DB_HOST=$DB_HOST DB_DATABASE=$DB_DATABASE"
echo "[start.sh] APP_KEY is $([ -n "$APP_KEY" ] && echo SET || echo MISSING)"

cd /var/www/html

# Artisan needs a .env file to exist (it still reads real env vars from the container).
[ -f .env ] || touch .env

# Generate an app key if none was provided (non-fatal).
if [ -z "$APP_KEY" ]; then
  php artisan key:generate --force || echo "[start.sh] WARN: key:generate failed"
fi

# Cache config so env vars are read once at boot.
php artisan config:cache || echo "[start.sh] WARN: config:cache failed"

# Run migrations + seed the bootstrap admin (seed only creates an admin if the
# users table is empty). Non-fatal so the app still boots to show DB errors.
php artisan migrate --force || echo "[start.sh] WARN: migrations failed — check DB_* env vars"
php artisan db:seed --force || echo "[start.sh] WARN: seed failed"

# Storage permissions (Railway volumes start root-owned).
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Substitute Railway's $PORT into the nginx config.
export PORT="${PORT:-8080}"
envsubst '$PORT' < /etc/nginx/http.d/default.conf.template > /etc/nginx/http.d/default.conf

# Start php-fpm + nginx under supervisor (foreground).
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf
