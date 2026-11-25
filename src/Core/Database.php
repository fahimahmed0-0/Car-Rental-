<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * Singleton PDO database connector configured from environment variables (with Cloud MySQL SSL support).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Env::get('DB_HOST', 'localhost');
        $port = Env::get('DB_PORT', '3306') ?: '3306';
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        $name = Env::get('DB_NAME', 'carrental');

        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        // Support Cloud MySQL TLS (TiDB Serverless, Aiven, PlanetScale, Railway)
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
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
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
    }
}
