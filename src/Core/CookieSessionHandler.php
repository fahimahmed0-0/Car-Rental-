<?php

declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;

/**
 * HMAC-signed stateless cookie session handler for serverless deployments without external session storage.
 */
final class CookieSessionHandler implements SessionHandlerInterface
{
    private const COOKIE_NAME = 'drivex_sess_data';
    private string $secret;
    private bool $secure;

    public function __construct(bool $secure = false)
    {
        $this->secure = $secure;
        $key = (string) Env::get('APP_KEY', '');
        $this->secret = $key !== '' ? $key : hash('sha256', 'drivex-assam-session-' . APP_ROOT);
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!is_string($raw) || !str_contains($raw, '.')) {
            return '';
        }

        [$payloadB64, $sig] = explode('.', $raw, 2);
        $expected = hash_hmac('sha256', $payloadB64, $this->secret);
        if (!hash_equals($expected, $sig)) {
            return '';
        }

        $json = base64_decode($payloadB64, true);
        if ($json === false) {
            return '';
        }

        $parsed = json_decode($json, true);
        if (!is_array($parsed) || !isset($parsed['exp'], $parsed['data']) || (int) $parsed['exp'] < time()) {
            return '';
        }

        return (string) $parsed['data'];
    }

    public function write(string $id, string $data): bool
    {
        if (headers_sent()) {
            return true;
        }

        $payload = json_encode([
            'exp' => time() + 7200,
            'data' => $data,
        ]);
        $payloadB64 = base64_encode($payload ?: '{}');
        $sig = hash_hmac('sha256', $payloadB64, $this->secret);
        $cookieVal = $payloadB64 . '.' . $sig;

        // Keep well below 4KB browser cookie cap
        if (strlen($cookieVal) > 3800) {
            return false;
        }

        return setcookie(self::COOKIE_NAME, $cookieVal, [
            'expires' => time() + 7200,
            'path' => '/',
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    public function destroy(string $id): bool
    {
        if (!headers_sent()) {
            setcookie(self::COOKIE_NAME, '', [
                'expires' => time() - 3600,
                'path' => '/',
                'secure' => $this->secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        unset($_COOKIE[self::COOKIE_NAME]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;
    }
}
