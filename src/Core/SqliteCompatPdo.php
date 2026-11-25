<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * SQLite PDO wrapper that maps MySQL scalar/date expressions to SQLite equivalents.
 */
final class SqliteCompatPdo extends PDO
{
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);

        // Register MySQL-compatible scalar functions in SQLite
        $this->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'), 0);
        $this->sqliteCreateFunction('CURDATE', static fn (): string => date('Y-m-d'), 0);
        $this->sqliteCreateFunction('GREATEST', static fn (mixed $a, mixed $b): mixed => ($a > $b ? $a : $b), 2);
        $this->sqliteCreateFunction('DATE_FORMAT', static function (?string $dt, string $fmt): string {
            if ($dt === null || $dt === '') {
                return '';
            }
            $ts = strtotime($dt);
            if ($ts === false) {
                return $dt;
            }
            $phpFmt = strtr($fmt, ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%b' => 'M', '%H' => 'H', '%i' => 'i']);
            return date($phpFmt, $ts);
        }, 2);
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(self::translateSql($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        $translated = self::translateSql($query);
        if ($fetchMode === null) {
            return parent::query($translated);
        }
        return parent::query($translated, $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translateSql($statement));
    }

    public static function translateSql(string $sql): string
    {
        // Strip MySQL row-level locking clause
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql) ?? $sql;

        // CAST(... AS CHAR) -> CAST(... AS TEXT)
        $sql = preg_replace('/CAST\((.+?)\s+AS\s+CHAR\)/i', 'CAST($1 AS TEXT)', $sql) ?? $sql;

        // TIMESTAMPDIFF(SECOND, a, b) -> (strftime('%s', b) - strftime('%s', a))
        $sql = preg_replace(
            '/TIMESTAMPDIFF\s*\(\s*SECOND\s*,\s*([^,]+?)\s*,\s*([^)]+?)\s*\)/i',
            "(CAST(strftime('%s', $2) AS INTEGER) - CAST(strftime('%s', $1) AS INTEGER))",
            $sql
        ) ?? $sql;

        // DATE_ADD(NOW(), INTERVAL :ttl SECOND)
        $sql = preg_replace(
            '/DATE_ADD\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(:[a-zA-Z0-9_]+)\s+SECOND\s*\)/i',
            "datetime(NOW(), '+' || $1 || ' seconds')",
            $sql
        ) ?? $sql;

        // DATE_ADD(NOW(), INTERVAL N MINUTE|HOUR|DAY|SECOND)
        $sql = preg_replace_callback(
            '/DATE_ADD\s*\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(SECOND|MINUTE|HOUR|DAY|MONTH)\s*\)/i',
            static fn (array $m): string => "datetime(NOW(), '+" . $m[1] . ' ' . strtolower($m[2]) . "s')",
            $sql
        ) ?? $sql;

        // DATE_SUB(NOW()|CURDATE(), INTERVAL :param SECOND)
        $sql = preg_replace(
            '/DATE_SUB\s*\(\s*(?:NOW\(\)|CURDATE\(\))\s*,\s*INTERVAL\s+(:[a-zA-Z0-9_]+)\s+SECOND\s*\)/i',
            "datetime(NOW(), '-' || $1 || ' seconds')",
            $sql
        ) ?? $sql;

        // DATE_SUB(NOW()|CURDATE(), INTERVAL N UNIT)
        $sql = preg_replace_callback(
            '/DATE_SUB\s*\(\s*(?:NOW\(\)|CURDATE\(\))\s*,\s*INTERVAL\s+(\d+)\s+(SECOND|MINUTE|HOUR|DAY|MONTH)\s*\)/i',
            static fn (array $m): string => "datetime(NOW(), '-" . $m[1] . ' ' . strtolower($m[2]) . "s')",
            $sql
        ) ?? $sql;

        // information_schema table check
        $sql = preg_replace(
            '/SELECT\s+1\s+FROM\s+information_schema\.tables\s+WHERE\s+table_schema\s*=\s*DATABASE\(\)\s+AND\s+table_name\s*=\s*(\'[^\']+\')\s+LIMIT\s+1/i',
            "SELECT 1 FROM sqlite_master WHERE type='table' AND name=$1 LIMIT 1",
            $sql
        ) ?? $sql;

        // ON DUPLICATE KEY UPDATE for tblaicache & tblsessions
        if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            if (stripos($sql, 'tblaicache') !== false) {
                $sql = preg_replace(
                    '/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+response_json\s*=\s*VALUES\(response_json\)\s*,\s*expires_at\s*=\s*VALUES\(expires_at\)/i',
                    'ON CONFLICT(cache_key) DO UPDATE SET response_json = excluded.response_json, expires_at = excluded.expires_at',
                    $sql
                ) ?? $sql;
            } elseif (stripos($sql, 'tblsessions') !== false) {
                $sql = preg_replace(
                    '/ON\s+DUPLICATE\s+KEY\s+UPDATE\s+data\s*=\s*VALUES\(data\)\s*,\s*expires_at\s*=\s*VALUES\(expires_at\)/i',
                    'ON CONFLICT(id) DO UPDATE SET data = excluded.data, expires_at = excluded.expires_at',
                    $sql
                ) ?? $sql;
            }
        }

        return $sql;
    }
}
