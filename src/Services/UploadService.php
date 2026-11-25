<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use finfo;
use RuntimeException;

/**
 * Validated image upload handler supporting local disk, Cloudinary (for Vercel), and https:// URLs.
 */
final class UploadService
{
    private const MAX_BYTES = 5_242_880; // 5 MB

    private const ALLOWED_MIME_EXT = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Resolves an uploaded file OR optional https:// URL into a stored filename or URL.
     */
    public static function storeVehicleImageOrUrl(array $file, ?string $imageUrl = null, bool $required = false): ?string
    {
        $url = trim((string) ($imageUrl ?? ''));
        $hasFile = isset($file['error']) && (int) $file['error'] !== UPLOAD_ERR_NO_FILE;

        if ($hasFile) {
            return self::storeVehicleImage($file, $required);
        }

        if ($url !== '') {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($url), 'https://')) {
                throw new RuntimeException('Image URL must be a valid https:// URL.');
            }
            if (strlen($url) > 500) {
                throw new RuntimeException('Image URL must be 500 characters or fewer.');
            }
            return $url;
        }

        if ($required) {
            throw new RuntimeException('Please upload an image file or provide a valid https:// image URL.');
        }

        return null;
    }

    /**
     * Validates and stores an uploaded vehicle image (Cloudinary on serverless, local disk on XAMPP/VPS).
     */
    public static function storeVehicleImage(array $file, bool $required = false): ?string
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            if ($required) {
                throw new RuntimeException('Required vehicle image is missing.');
            }
            return null;
        }

        if ($error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('File upload failed with error code ' . $error . '.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('Invalid uploaded file payload.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Image must be smaller than 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath) ?: '';
        if (!array_key_exists($mime, self::ALLOWED_MIME_EXT)) {
            throw new RuntimeException('Only JPG, PNG, and WebP images are allowed.');
        }

        // 1. Prefer Cloudinary when configured (required for stateless Vercel uploads)
        $cloudName = trim((string) Env::get('CLOUDINARY_CLOUD_NAME', ''));
        $apiKey = trim((string) Env::get('CLOUDINARY_API_KEY', ''));
        $apiSecret = trim((string) Env::get('CLOUDINARY_API_SECRET', ''));

        if ($cloudName !== '' && $apiKey !== '' && $apiSecret !== '') {
            return self::uploadToCloudinary($tmpPath, $mime, $cloudName, $apiKey, $apiSecret);
        }

        // 2. Fallback to local disk when writable (XAMPP / Apache / VPS)
        $ext = self::ALLOWED_MIME_EXT[$mime];
        $targetDir = APP_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'vehicleimages';
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Filesystem is read-only. Configure CLOUDINARY_* env vars or paste an https:// Image URL.');
        }

        if (!is_writable($targetDir)) {
            throw new RuntimeException('Upload folder is read-only (serverless). Configure CLOUDINARY_* env vars or paste an https:// Image URL.');
        }

        $filename = 'veh_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $destPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        return $filename;
    }

    private static function uploadToCloudinary(string $tmpPath, string $mime, string $cloudName, string $apiKey, string $apiSecret): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('cURL extension is required for Cloudinary uploads.');
        }

        $timestamp = time();
        $folder = 'drivex-vehicles';
        $sigBase = "folder={$folder}&timestamp={$timestamp}{$apiSecret}";
        $signature = sha1($sigBase);

        $url = "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload";
        $postFields = [
            'file' => new \CURLFile($tmpPath, $mime),
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'folder' => $folder,
            'signature' => $signature,
        ];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Failed to initialize Cloudinary upload.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_TIMEOUT => 15,
        ]);

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $code < 200 || $code >= 300) {
            throw new RuntimeException('Cloudinary image upload failed (HTTP ' . $code . ').');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || empty($decoded['secure_url'])) {
            throw new RuntimeException('Cloudinary did not return a secure image URL.');
        }

        return (string) $decoded['secure_url'];
    }
}
