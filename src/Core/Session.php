<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Secure session initialization (with DB session handler for Vercel serverless) and flash helpers.
 */
final class Session
{
    private static bool $handlerRegistered = false;

    public static function registerDbHandler(PDO $dbh): void
    {
        if (self::$handlerRegistered || session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }

        $driver = strtolower((string) Env::get('SESSION_DRIVER', ''));
        $isVercel = Env::bool('VERCEL', false) || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');

        if ($driver !== 'database' && !$isVercel) {
            return;
        }

        try {
            // Confirm tblsessions exists before switching save handler
            $chk = $dbh->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tblsessions' LIMIT 1");
            if ($chk && $chk->fetchColumn()) {
                session_set_save_handler(new DbSessionHandler($dbh), true);
                self::$handlerRegistered = true;
            }
        } catch (Throwable) {
            // Fall back to default PHP file session handler if table isn't migrated yet
        }
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function regenerate(): void
    {
        self::start();
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function setFlash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][$type] = $message;
    }

    public static function getFlash(string $type): ?string
    {
        self::start();
        if (!isset($_SESSION['_flash'][$type])) {
            return null;
        }
        $msg = (string) $_SESSION['_flash'][$type];
        unset($_SESSION['_flash'][$type]);
        return $msg;
    }

    public static function consumeAllFlash(): array
    {
        self::start();
        $all = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($all) ? $all : [];
    }
}
