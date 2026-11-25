<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Secure session initialization supporting MySQL DB sessions, stateless Cookie sessions (Zero-DB Vercel), and file sessions.
 */
final class Session
{
    private static bool $handlerRegistered = false;
    private static bool $demoShutdownRegistered = false;

    public static function registerDbHandler(PDO $dbh): void
    {
        if (self::$handlerRegistered || session_status() === PHP_SESSION_ACTIVE || PHP_SAPI === 'cli') {
            return;
        }

        $isHttps = self::isHttpsRequest();

        // In Zero-DB Portfolio Demo Mode on Vercel/stateless servers, use signed cookie sessions
        if (Database::isDemoMode()) {
            session_set_save_handler(new CookieSessionHandler($isHttps), true);
            self::$handlerRegistered = true;
            return;
        }

        $driver = strtolower((string) Env::get('SESSION_DRIVER', ''));
        $isVercel = Env::bool('VERCEL', false) || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');

        if ($driver !== 'database' && !$isVercel) {
            return;
        }

        try {
            $chk = $dbh->query("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tblsessions' LIMIT 1");
            if ($chk && $chk->fetchColumn()) {
                session_set_save_handler(new DbSessionHandler($dbh), true);
                self::$handlerRegistered = true;
            }
        } catch (Throwable) {
            // Fall back to default PHP file session handler
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

        $isHttps = self::isHttpsRequest();

        // Always push an unbounded output buffer so HTML >4KB never flushes headers before session cookie writes
        if (Database::isDemoMode() && !self::$demoShutdownRegistered) {
            self::$demoShutdownRegistered = true;
            ob_start();
            register_shutdown_function(static function (): void {
                if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                    session_write_close();
                }
                while (ob_get_level() > 0) {
                    ob_end_flush();
                }
            });
        }

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
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent() && !Database::isDemoMode()) {
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

    private static function isHttpsRequest(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
