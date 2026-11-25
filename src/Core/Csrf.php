<?php

declare(strict_types=1);

namespace App\Core;

/**
 * CSRF token manager for form submissions and AJAX/API requests.
 */
final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        Session::start();
        if (empty($_SESSION[self::SESSION_KEY]) || !is_string($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::SESSION_KEY];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function verify(?string $candidate = null): bool
    {
        $sessionToken = self::token();
        if ($candidate === null) {
            $candidate = $_POST['_csrf']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? '';
        }

        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($sessionToken, $candidate);
    }

    public static function requireValid(?string $candidate = null): void
    {
        if (!self::verify($candidate)) {
            http_response_code(403);
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            if (str_contains($accept, 'application/json') || str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page.']);
                exit;
            }
            exit('Invalid or expired security token. Please go back, refresh, and try again.');
        }
    }
}
