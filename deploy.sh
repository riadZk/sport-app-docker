#!/usr/bin/env bash
#
# Safe production deployment for Sport App v1.
#
# - Operates only in /home/projects/sport-app-v1
# - Uses the isolated Compose project: sport-app-v1
# - Does not run database migrations
# - Does not delete volumes or run Docker cleanup commands
# - Refuses to deploy if the Git working tree has local changes
#
set -Eeuo pipefail

PROJECT_DIR="/home/projects/sport-app-v1"
BRANCH="main"
COMPOSE_PROJECT="sport-app-v1"
COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.production"
LOCAL_HEALTH_URL="http://127.0.0.1:8088/up"
PUBLIC_HEALTH_URL="https://api-v1.parrotscan.com/up"

cd "$PROJECT_DIR"

echo "=== [1/7] Checking project, environment, and Docker access ==="

if [[ ! -f "$ENV_FILE" ]]; then
    echo "ERROR: $ENV_FILE not found in $PROJECT_DIR"
    exit 1
fi

if [[ ! -f "$COMPOSE_FILE" ]]; then
    echo "ERROR: $COMPOSE_FILE not found in $PROJECT_DIR"
    exit 1
fi

if ! docker info >/dev/null 2>&1; then
    echo "ERROR: Docker is not accessible for the current user."
    echo "Check Docker group membership or run the required Docker commands with sudo."
    exit 1
fi

if [[ "$(git branch --show-current)" != "$BRANCH" ]]; then
    echo "ERROR: Expected Git branch '$BRANCH'; current branch is '$(git branch --show-current)'."
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: Local Git changes detected. Deployment stopped to protect them."
    git status --short
    exit 1
fi

echo "=== [2/7] Fetching and fast-forwarding latest code ==="
git fetch origin "$BRANCH"
git pull --ff-only origin "$BRANCH"

echo "=== [3/7] Verifying required secrets are present (values hidden) ==="
for var in APP_KEY DB_PASSWORD MYSQL_ROOT_PASSWORD; do
    if ! grep -Eq "^${var}=.+$" "$ENV_FILE"; then
        echo "ERROR: ${var} is missing or empty in ${ENV_FILE}"
        exit 1
    fi
done

COMPOSE=(docker compose
    -p "$COMPOSE_PROJECT"
    --env-file "$ENV_FILE"
    -f "$COMPOSE_FILE"
)

echo "=== [4/7] Validating Compose configuration and building images ==="
"${COMPOSE[@]}" config --quiet
"${COMPOSE[@]}" build app nginx queue

echo "=== [5/7] Updating containers ==="
"${COMPOSE[@]}" up -d --remove-orphans

echo "=== [6/7] Waiting for MySQL and Redis health ==="

wait_for_healthy() {
    local service="$1"
    local max_seconds="$2"
    local elapsed=0
    local container_id=""

    while (( elapsed < max_seconds )); do
        container_id="$("${COMPOSE[@]}" ps -q "$service" 2>/dev/null || true)"

        if [[ -n "$container_id" ]]; then
            if [[ "$(docker inspect --format='{{.State.Health.Status}}' "$container_id" 2>/dev/null || true)" == "healthy" ]]; then
                echo "$service is healthy."
                return 0
            fi
        fi

        sleep 3
        elapsed=$((elapsed + 3))
    done

    echo "ERROR: $service did not become healthy within ${max_seconds} seconds."
    "${COMPOSE[@]}" ps
    return 1
}

wait_for_healthy mysql 180
wait_for_healthy redis 120

echo "=== [7/7] Optimizing Laravel and restarting queue workers (no migrations) ==="
"${COMPOSE[@]}" exec -T app php artisan optimize
"${COMPOSE[@]}" exec -T app php artisan queue:restart

echo "=== Verifying application health ==="

check_health() {
    local url="$1"
    local label="$2"
    local http_code=""
    local attempt

    for attempt in $(seq 1 12); do
        http_code="$(curl --silent --output /dev/null --write-out '%{http_code}' \
            --max-time 5 "$url" 2>/dev/null || true)"

        if [[ "$http_code" == "200" ]]; then
            echo "OK: $label returned HTTP 200."
            return 0
        fi

        sleep 5
    done

    echo "ERROR: $label health check failed. Last HTTP status: ${http_code:-no response}"
    return 1
}

if ! check_health "$LOCAL_HEALTH_URL" "Local"; then
    echo "Recent application and Nginx logs:"
    "${COMPOSE[@]}" logs --tail=50 app nginx
    exit 1
fi

if ! check_health "$PUBLIC_HEALTH_URL" "Public HTTPS"; then
    echo "Local endpoint is healthy, but the public endpoint did not pass."
    echo "Check Cloudflare, host Nginx, and DNS. Recent Nginx logs:"
    "${COMPOSE[@]}" logs --tail=50 nginx
    exit 1
fi

echo
echo "=== Deployment finished successfully ==="
echo "No database migrations were run."
echo "No Docker volumes were deleted."
