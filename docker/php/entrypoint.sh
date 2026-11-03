#!/bin/sh
set -eu
cd /var/www/html

if [ -n "${DB_HOST:-}" ] && [ "${DB_HOST}" != "127.0.0.1" ]; then
  echo "Waiting for database at ${DB_HOST}:${DB_PORT:-3306}..."
  php -r '
    $host = getenv("DB_HOST");
    $port = (int) (getenv("DB_PORT") ?: 3306);
    for ($i = 0; $i < 60; $i++) {
      $fp = @fsockopen($host, $port, $errno, $errstr, 2);
      if ($fp) { fclose($fp); echo "Database is ready.\n"; exit(0); }
      sleep(2);
    }
    fwrite(STDERR, "Database connection timeout.\n");
    exit(1);
  '
fi

if [ "${APP_ENV:-local}" = "production" ] && [ -z "${APP_KEY:-}" ]; then
  echo "ERROR: APP_KEY is missing in production environment."
  exit 1
fi

# Ensure Laravel runtime directories exist and are writable by www-data.
# The named volume mounts over /var/www/html/storage at container start,
# so any directories created at build time are hidden. Create them here.
mkdir -p \
  storage/framework/cache/data \
  storage/framework/sessions \
  storage/framework/testing \
  storage/framework/views \
  storage/logs \
  bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

# Remove stale config cache if it exists.
# A cached config baked into the image with wrong values (e.g. view.compiled=false)
# causes "Please provide a valid cache path" errors. Always start fresh.
if [ -f bootstrap/cache/config.php ]; then
  echo "Removing stale config cache..."
  rm -f bootstrap/cache/config.php
fi

exec "$@"
