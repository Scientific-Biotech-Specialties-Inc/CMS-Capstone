#!/bin/sh
set -e

mkdir -p bootstrap/cache \
    storage/app/public \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
ln -sf ../storage/app/public public/storage 2>/dev/null || true
chmod -R 775 bootstrap/cache storage

if [ "$DB_CONNECTION" = "mysql" ] && [ -n "$DB_DATABASE" ]; then
    php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: "3306";
    $user = getenv("DB_USERNAME") ?: "root";
    $pass = getenv("DB_PASSWORD") ?: "";
    $db = getenv("DB_DATABASE");
    $max = 12;
    while ($max-- > 0) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "Database `$db` ready.\n";
            break;
        } catch (\Exception $e) {
            echo "Waiting for MySQL at $host:$port...\n";
            sleep(2);
        }
    }
    ' 2>/dev/null || true
fi

if [ "$APP_ENV" = "production" ]; then
    php artisan config:clear 2>/dev/null || true
    php artisan migrate --force --no-interaction 2>/dev/null || true
fi

exec "$@"

