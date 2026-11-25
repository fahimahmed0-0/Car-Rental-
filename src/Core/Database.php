<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton PDO connection manager with Cloud MySQL TLS support and automatic SQLite demo mode.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $demoMode = false;

    public static function isDemoMode(): bool
    {
        return self::$demoMode;
    }

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = trim((string) Env::get('DB_HOST', 'localhost'));
        $isVercel = Env::bool('VERCEL', false) || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');
        $explicitDemo = Env::bool('DEMO_MODE', false);

        // Activate embedded SQLite demo database when DEMO_MODE=true
        // or on serverless deployments without an external DB_HOST.
        if ($explicitDemo || ($isVercel && ($host === '' || $host === 'localhost' || $host === '127.0.0.1'))) {
            self::$demoMode = true;
            self::$pdo = DemoDatabase::connect();
            return self::$pdo;
        }

        $port = Env::get('DB_PORT', '3306') ?: '3306';
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $name = Env::get('DB_NAME', 'carrental');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ];

        if (Env::bool('DB_SSL', false)) {
            $sslCa = trim((string) Env::get('DB_SSL_CA', ''));
            if ($sslCa !== '' && is_file($sslCa) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $sslCa;
            }
            if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $sslCa !== '';
            }
        }

        try {
            self::$pdo = new PDO($dsn, $user, $pass, $options);
            self::$demoMode = false;
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());

            // Fall back to SQLite demo mode on web requests if MySQL is unreachable
            if (PHP_SAPI !== 'cli' && Env::bool('DB_DEMO_FALLBACK', true)) {
                self::$demoMode = true;
                self::$pdo = DemoDatabase::connect();
                return self::$pdo;
            }

            if (Env::bool('APP_DEBUG', false)) {
                throw $e;
            }
            http_response_code(500);
            exit('Database service is temporarily unavailable.');
        }
    }

    /**
     * Allows tests or CLI scripts to inject a custom PDO connection.
     */
    public static function setConnection(?PDO $pdo): void
    {
        self::$pdo = $pdo;
        self::$demoMode = false;
    }
}
