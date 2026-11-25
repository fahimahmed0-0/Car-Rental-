<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use SessionHandlerInterface;
use Throwable;

/**
 * MariaDB-backed PHP session handler for stateless serverless environments (e.g. Vercel).
 */
final class DbSessionHandler implements SessionHandlerInterface
{
    private PDO $dbh;
    private int $ttl;

    public function __construct(PDO $dbh, int $ttl = 7200)
    {
        $this->dbh = $dbh;
        $this->ttl = $ttl;
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
        try {
            $stmt = $this->dbh->prepare('SELECT data FROM tblsessions WHERE id = :id AND expires_at >= :now LIMIT 1');
            $stmt->execute([':id' => $id, ':now' => time()]);
            $data = $stmt->fetchColumn();
            return is_string($data) ? $data : '';
        } catch (Throwable) {
            return '';
        }
    }

    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->dbh->prepare(
                'INSERT INTO tblsessions (id, data, expires_at) VALUES (:id, :data, :exp)
                 ON DUPLICATE KEY UPDATE data = VALUES(data), expires_at = VALUES(expires_at)'
            );
            return $stmt->execute([
                ':id' => $id,
                ':data' => $data,
                ':exp' => time() + $this->ttl,
            ]);
        } catch (Throwable) {
            return false;
        }
    }

    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->dbh->prepare('DELETE FROM tblsessions WHERE id = :id');
            return $stmt->execute([':id' => $id]);
        } catch (Throwable) {
            return false;
        }
    }

    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->dbh->prepare('DELETE FROM tblsessions WHERE expires_at < :now');
            $stmt->execute([':now' => time()]);
            return $stmt->rowCount();
        } catch (Throwable) {
            return false;
        }
    }
}
