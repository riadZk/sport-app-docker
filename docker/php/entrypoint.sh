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

exec "$@"
