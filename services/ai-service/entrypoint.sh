#!/bin/sh
set -e

mkdir -p bootstrap/cache \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs
chmod -R 775 bootstrap/cache storage

if [ "$DB_CONNECTION" = "pgsql" ] && [ -n "$DB_DATABASE" ]; then
    php -r '
    $host = getenv("DB_HOST") ?: "127.0.0.1";
    $port = getenv("DB_PORT") ?: "5432";
    $user = getenv("DB_USERNAME") ?: "ocr_user";
    $pass = getenv("DB_PASSWORD") ?: "";
    $db = getenv("DB_DATABASE") ?: "cms_ai_service";
    $max = 12;
    while ($max-- > 0) {
        try {
            $pdo = new PDO("pgsql:host=$host;port=$port;dbname=ocr_pipeline", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname = '$db'");
            if (!$stmt->fetch()) {
                $pdo->exec("CREATE DATABASE \"$db\"");
                echo "PostgreSQL database `$db` created.\n";
            }
            $pdoDb = new PDO("pgsql:host=$host;port=$port;dbname=$db", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdoDb->exec("CREATE EXTENSION IF NOT EXISTS vector");
            echo "Extension vector verified in `$db`.\n";
            break;
        } catch (\Exception $e) {
            echo "Waiting for PostgreSQL ($host:$port)...\n";
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

