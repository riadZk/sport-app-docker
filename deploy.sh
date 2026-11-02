#!/bin/bash

set -e

cd /home/projects/sport-app

echo "Pulling latest code..."

git fetch origin main

git reset --hard origin/main

echo "Building Docker image..."

docker compose -f docker-compose.prod.yml build

echo "Starting containers..."

docker compose -f docker-compose.prod.yml up -d

echo "Running migrations..."

docker compose -f docker-compose.prod.yml exec -T app \
    php artisan migrate --force

echo "Clearing Laravel cache..."

docker compose -f docker-compose.prod.yml exec -T app \
    php artisan optimize

echo "Restarting queue workers..."

docker compose -f docker-compose.prod.yml exec -T queue \
    php artisan queue:restart

echo "Deployment completed!"