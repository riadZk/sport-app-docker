#!/bin/bash
#
# Safe production deployment script for Sport App v1.
#
# - Operates ONLY on /home/projects/sport-app-v1
# - Uses isolated Compose project name: sport-app-v1
# - Never touches other projects, volumes, or host services
#
set -euo pipefail

PROJECT_DIR="/home/projects/sport-app-v1"
COMPOSE_PROJECT="sport-app-v1"
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.production"

cd "$PROJECT_DIR"

echo "=== [1/7] Checking environment file ==="
if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: $ENV_FILE not found in $PROJECT_DIR"
    exit 1
fi

echo "=== [2/7] Fetching latest code ==="
git fetch origin main
git reset --hard origin/main

echo "=== [3/7] Verifying required secrets are present (values hidden) ==="
# Reverb is disabled for V1 — only core secrets are validated.
for var in APP_KEY DB_PASSWORD MYSQL_ROOT_PASSWORD; do
    if ! grep -q "^${var}=.\+" "$ENV_FILE"; then
        echo "ERROR: ${var} is missing or empty in ${ENV_FILE}"
        exit 1
    fi
done

COMPOSE="docker compose -p ${COMPOSE_PROJECT} --env-file ${ENV_FILE} -f ${COMPOSE_FILE}"

echo "=== [4/7] Building production images ==="
$COMPOSE build

echo "=== [5/7] Starting containers ==="
$COMPOSE up -d

echo "=== [6/7] Waiting for MySQL and Redis health (hard failure on timeout) ==="

# Use docker compose ps -q to get the exact container for each service.
# No credentials needed, no secrets printed.
timeout 180 bash -c "
    until [[ \"\$(${COMPOSE} ps -q mysql 2>/dev/null)\" != '' ]] && \
          docker inspect --format='{{.State.Health.Status}}' \
          \"\$(${COMPOSE} ps -q mysql)\" 2>/dev/null | grep -q healthy; do
        sleep 3
    done
" || { echo "ERROR: MySQL did not become healthy within 180 seconds."; exit 1; }
echo "MySQL is healthy."

timeout 120 bash -c "
    until [[ \"\$(${COMPOSE} ps -q redis 2>/dev/null)\" != '' ]] && \
          docker inspect --format='{{.State.Health.Status}}' \
          \"\$(${COMPOSE} ps -q redis)\" 2>/dev/null | grep -q healthy; do
        sleep 3
    done
" || { echo "ERROR: Redis did not become healthy within 120 seconds."; exit 1; }
echo "Redis is healthy."

echo "=== [7/7] Running migrations and restarting queue workers ==="
$COMPOSE exec -T app php artisan migrate --force
$COMPOSE exec -T app php artisan optimize
$COMPOSE exec -T app php artisan queue:restart

echo "=== Verifying application health ==="

# Retry /up for up to ~120 seconds before failing.
# Each attempt: up to 5s curl timeout + 5s sleep = ~10s per cycle, 12 cycles.
# PHP-FPM and Nginx may take a few seconds to become ready after container start.
HTTP_CODE=""
for i in $(seq 1 12); do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" --max-time 5 http://127.0.0.1:8088/up 2>/dev/null || true)
    if [[ "$HTTP_CODE" == "200" ]]; then
        break
    fi
    sleep 5
done

echo "Health check /up responded with HTTP ${HTTP_CODE:-no response}"
if [[ "$HTTP_CODE" != "200" ]]; then
    echo "ERROR: health check failed after ~120 seconds. Container logs:"
    docker compose -p "${COMPOSE_PROJECT}" --env-file "${ENV_FILE}" -f "${COMPOSE_FILE}" \
        logs --tail=50 app
    exit 1
fi

echo "=== Deployment finished successfully ==="
