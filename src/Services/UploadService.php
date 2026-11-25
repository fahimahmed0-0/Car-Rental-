<?php

declare(strict_types=1);

namespace App\Services;

use finfo;
use RuntimeException;

/**
 * Validated image upload handler preventing arbitrary script/extension uploads.
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
     * Validates and moves an uploaded vehicle image, returning the generated filename.
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

        $ext = self::ALLOWED_MIME_EXT[$mime];
        $targetDir = APP_ROOT . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'img' . DIRECTORY_SEPARATOR . 'vehicleimages';
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException('Failed to create vehicle image storage directory.');
        }

        $filename = 'veh_' . bin2hex(random_bytes(12)) . '.' . $ext;
        $destPath = $targetDir . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new RuntimeException('Failed to save uploaded image.');
        }

        return $filename;
    }
}
