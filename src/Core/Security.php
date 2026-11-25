<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Output escaping, HTML sanitization, HTTP helpers, and DB-backed rate limiting.
 */
final class Security
{
    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Resolves either a local filename (in admin/img/vehicleimages/) or an external https:// URL.
     */
    public static function vehicleImageUrl(?string $value, string $localPrefix = 'admin/img/vehicleimages/'): string
    {
        $val = trim((string) ($value ?? ''));
        if ($val === '') {
            return $localPrefix . 'car_755x430.png';
        }
        if (str_starts_with($val, 'https://') || str_starts_with($val, 'http://')) {
            return $val;
        }
        return $localPrefix . $val;
    }

    /**
     * Sanitize admin-authored CMS HTML with a strict tag allowlist and attribute stripping.
     */
    public static function sanitizeRichHtml(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $allowed = '<p><br><b><strong><i><em><u><ul><ol><li><h3><h4><h5><h6><a><blockquote><hr>';
        $cleaned = strip_tags($html, $allowed);

        // Strip inline event handlers (on*) and javascript:/data: hrefs
        $cleaned = preg_replace('/\s+on[a-z]+\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $cleaned) ?? '';
        $cleaned = preg_replace('/href\s*=\s*(["\'])\s*(?:javascript|data|vbscript):.*?\1/i', 'href="#"', $cleaned) ?? '';

        return $cleaned;
    }

    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'), 0, 45);
    }

    public static function jsonResponse(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function redirect(string $url): never
    {
        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href=' . json_encode($url) . ';</script>';
        }
        exit;
    }

    /**
     * DB-backed sliding window rate limiter with graceful no-op fallback if table is missing.
     */
    public static function checkRateLimit(PDO $dbh, string $bucket, int $maxAttempts = 10, int $windowSeconds = 900): bool
    {
        try {
            $stmt = $dbh->prepare('SELECT COUNT(*) FROM tblratelimits WHERE bucket_key = :bucket AND attempt_at >= DATE_SUB(NOW(), INTERVAL :win SECOND)');
            $stmt->bindValue(':bucket', $bucket, PDO::PARAM_STR);
            $stmt->bindValue(':win', max(1, $windowSeconds), PDO::PARAM_INT);
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();
            return $count < $maxAttempts;
        } catch (Throwable) {
            return true;
        }
    }

    public static function hitRateLimit(PDO $dbh, string $bucket): void
    {
        try {
            $stmt = $dbh->prepare('INSERT INTO tblratelimits (bucket_key, attempt_at) VALUES (:bucket, NOW())');
            $stmt->execute([':bucket' => $bucket]);

            // Opportunistic cleanup of records older than 24h
            if (random_int(1, 20) === 1) {
                $dbh->exec("DELETE FROM tblratelimits WHERE attempt_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
            }
        } catch (Throwable) {
            // Non-fatal if table does not exist yet
        }
    }

    public static function clearRateLimit(PDO $dbh, string $bucket): void
    {
        try {
            $stmt = $dbh->prepare('DELETE FROM tblratelimits WHERE bucket_key = :bucket');
            $stmt->execute([':bucket' => $bucket]);
        } catch (Throwable) {
            // Non-fatal
        }
    }
}
