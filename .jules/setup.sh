#!/usr/bin/env bash
set -euo pipefail

echo "==> [Jules Setup] Initializing Pop Mart Tracker Environment..."

# 1. Verify PHP & Composer
if ! command -v php &> /dev/null; then
    echo "ERROR: PHP is not installed or not in PATH."
    exit 1
fi

php -v | head -n 1
composer -V | head -n 1

# 2. Setup Environment Variables
if [ ! -f .env ]; then
    echo "==> Creating .env from .env.example..."
    cp .env.example .env
    php artisan key:generate --no-interaction
fi

# 3. Install PHP Dependencies
if [ ! -d "vendor" ]; then
    echo "==> Installing Composer dependencies..."
    composer install --no-interaction --prefer-dist --no-progress
fi

# 4. Check Database Connection & Run Migrations
echo "==> Checking database connection..."
if php -r "try { new PDO('mysql:host=' . (getenv('DB_HOST') ?: '127.0.0.1') . ';port=' . (getenv('DB_PORT') ?: '3306') . ';dbname=' . (getenv('DB_DATABASE') ?: 'popmart_tracker_test'), getenv('DB_USERNAME') ?: 'root', getenv('DB_PASSWORD') ?: 'password'); exit(0); } catch (Exception \$e) { exit(1); }"; then
    echo "==> MariaDB connection successful. Running migrations..."
    php artisan migrate --force --no-interaction
else
    echo "==> WARNING: MariaDB not reachable on 127.0.0.1:3306. Unit tests without DB will pass, but full feature tests require MariaDB."
fi

# 5. Build Frontend Assets (if Node available)
if command -v npm &> /dev/null && [ ! -d "public/build" ]; then
    echo "==> Building frontend assets..."
    npm ci --prefer-offline --no-audit
    npm run build
fi

echo "==> [Jules Setup] Ready for autonomous execution."
