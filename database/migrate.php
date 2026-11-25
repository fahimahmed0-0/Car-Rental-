<?php

declare(strict_types=1);

/**
 * Idempotent CLI database migration script (safe for both fresh and legacy DBs).
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden: Run migrations from CLI only.');
}

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;

$dbh = Database::connection();

function tableExists(PDO $dbh, string $table): bool
{
    $stmt = $dbh->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tbl LIMIT 1');
    $stmt->execute([':tbl' => $table]);
    return (bool) $stmt->fetchColumn();
}

function columnExists(PDO $dbh, string $table, string $column): bool
{
    $stmt = $dbh->prepare('SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tbl AND column_name = :col LIMIT 1');
    $stmt->execute([':tbl' => $table, ':col' => $column]);
    return (bool) $stmt->fetchColumn();
}

function indexExists(PDO $dbh, string $table, string $indexName): bool
{
    $stmt = $dbh->prepare('SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :tbl AND index_name = :idx LIMIT 1');
    $stmt->execute([':tbl' => $table, ':idx' => $indexName]);
    return (bool) $stmt->fetchColumn();
}

function addColumnIfMissing(PDO $dbh, string $table, string $column, string $definition): void
{
    if (tableExists($dbh, $table) && !columnExists($dbh, $table, $column)) {
        $dbh->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        echo "[+] Added {$table}.{$column}\n";
    }
}

try {
    echo "Running DriveX Assam v2 database migrations...\n";

    // 1. Ensure core missing tables exist (fixes broken legacy template dumps)
    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblcontactusquery` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(100) DEFAULT NULL,
        `EmailId` varchar(120) DEFAULT NULL,
        `ContactNumber` varchar(20) DEFAULT NULL,
        `Message` longtext DEFAULT NULL,
        `PostingDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` int(11) DEFAULT 0,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblpages` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `PageName` varchar(255) DEFAULT NULL,
        `type` varchar(255) NOT NULL DEFAULT '',
        `detail` longtext NOT NULL,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblsubscribers` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `SubscriberEmail` varchar(120) DEFAULT NULL,
        `PostingDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tbltestimonial` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `UserEmail` varchar(100) NOT NULL,
        `Testimonial` mediumtext NOT NULL,
        `PostingDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `status` int(11) DEFAULT 0,
        `AiModerationFlag` varchar(20) DEFAULT 'clean',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    addColumnIfMissing($dbh, 'tbltestimonial', 'AiModerationFlag', "varchar(20) DEFAULT 'clean'");

    // 2. Expand admin/users Password columns to support Argon2id/bcrypt (255 chars)
    if (tableExists($dbh, 'admin')) {
        $dbh->exec("ALTER TABLE `admin` MODIFY COLUMN `Password` varchar(255) NOT NULL");
    }
    if (tableExists($dbh, 'tblusers')) {
        $dbh->exec("ALTER TABLE `tblusers` MODIFY COLUMN `Password` varchar(255) DEFAULT NULL");
        $dbh->exec("ALTER TABLE `tblusers` MODIFY COLUMN `ContactNo` varchar(20) DEFAULT NULL");
        addColumnIfMissing($dbh, 'tblusers', 'Role', "varchar(20) NOT NULL DEFAULT 'customer'");
        addColumnIfMissing($dbh, 'tblusers', 'TokenBalance', 'int(11) NOT NULL DEFAULT 0');
        addColumnIfMissing($dbh, 'tblusers', 'TotalTokensEarned', 'int(11) NOT NULL DEFAULT 0');
    }

    // 3. Upgrade tblvehicles
    addColumnIfMissing($dbh, 'tblvehicles', 'Transmission', "varchar(50) NOT NULL DEFAULT 'Manual'");

    // 4. Upgrade tblbooking with payment, lock, and location fields
    addColumnIfMissing($dbh, 'tblbooking', 'PaymentMethod', 'varchar(50) DEFAULT NULL');
    addColumnIfMissing($dbh, 'tblbooking', 'PaymentStatus', "varchar(20) NOT NULL DEFAULT 'pending'");
    addColumnIfMissing($dbh, 'tblbooking', 'PaymentDate', 'timestamp NULL DEFAULT NULL');
    addColumnIfMissing($dbh, 'tblbooking', 'Amount', 'decimal(10,2) NOT NULL DEFAULT 0.00');
    addColumnIfMissing($dbh, 'tblbooking', 'DiscountAmount', 'decimal(10,2) NOT NULL DEFAULT 0.00');
    addColumnIfMissing($dbh, 'tblbooking', 'TaxAmount', 'decimal(10,2) NOT NULL DEFAULT 0.00');
    addColumnIfMissing($dbh, 'tblbooking', 'FinalAmount', 'decimal(10,2) NOT NULL DEFAULT 0.00');
    addColumnIfMissing($dbh, 'tblbooking', 'TokensUsed', 'int(11) NOT NULL DEFAULT 0');
    addColumnIfMissing($dbh, 'tblbooking', 'PriceLockUntil', 'timestamp NULL DEFAULT NULL');
    addColumnIfMissing($dbh, 'tblbooking', 'PickupLocation', "varchar(150) DEFAULT 'DriveX Hub, Guwahati, Assam'");
    addColumnIfMissing($dbh, 'tblbooking', 'DropoffLocation', "varchar(150) DEFAULT 'DriveX Hub, Guwahati, Assam'");

    // 5. Create payment transactions table and add new columns if upgrading
    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblpaymenttransactions` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `booking_id` bigint(12) NOT NULL,
        `user_email` varchar(100) NOT NULL,
        `provider` varchar(30) NOT NULL DEFAULT 'sandbox',
        `payment_method` varchar(50) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `transaction_id` varchar(100) DEFAULT NULL,
        `idempotency_key` varchar(100) DEFAULT NULL,
        `gateway_order_id` varchar(100) DEFAULT NULL,
        `gateway_signature` varchar(255) DEFAULT NULL,
        `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
        `gateway_response` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pt_booking` (`booking_id`),
        KEY `idx_pt_user` (`user_email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    addColumnIfMissing($dbh, 'tblpaymenttransactions', 'provider', "varchar(30) NOT NULL DEFAULT 'sandbox'");
    addColumnIfMissing($dbh, 'tblpaymenttransactions', 'idempotency_key', 'varchar(100) DEFAULT NULL');
    addColumnIfMissing($dbh, 'tblpaymenttransactions', 'gateway_order_id', 'varchar(100) DEFAULT NULL');
    addColumnIfMissing($dbh, 'tblpaymenttransactions', 'gateway_signature', 'varchar(255) DEFAULT NULL');

    if (!indexExists($dbh, 'tblpaymenttransactions', 'uniq_idempotency')) {
        $dbh->exec('ALTER TABLE `tblpaymenttransactions` ADD UNIQUE KEY `uniq_idempotency` (`idempotency_key`)');
    }

    // 6. Immutable Token Ledger
    $dbh->exec("CREATE TABLE IF NOT EXISTS `tbltokenledger` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_email` varchar(100) NOT NULL,
        `booking_id` int(11) DEFAULT NULL,
        `booking_number` bigint(12) DEFAULT NULL,
        `entry_type` varchar(32) NOT NULL,
        `delta` int(11) NOT NULL,
        `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
        `note` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uniq_booking_entry` (`booking_id`, `entry_type`),
        KEY `idx_ledger_email` (`user_email`),
        KEY `idx_ledger_booking_num` (`booking_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Backfill legacy token balances if tblcustomertokens exists and tbltokenledger is empty
    $ledgerCount = (int) $dbh->query('SELECT COUNT(*) FROM `tbltokenledger`')->fetchColumn();
    if ($ledgerCount === 0 && tableExists($dbh, 'tblcustomertokens')) {
        $rows = $dbh->query('SELECT userEmail, tokenBalance, totalEarnedTokens FROM tblcustomertokens')->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $row) {
            if ((int) $row->tokenBalance > 0) {
                $stmt = $dbh->prepare('INSERT INTO tbltokenledger (user_email, booking_id, entry_type, delta, note) VALUES (:email, NULL, :type, :delta, :note)');
                $stmt->execute([
                    ':email' => $row->userEmail,
                    ':type' => 'legacy_migration',
                    ':delta' => (int) $row->tokenBalance,
                    ':note' => 'Migrated from legacy tblcustomertokens',
                ]);
                $upd = $dbh->prepare('UPDATE tblusers SET TokenBalance = :bal, TotalTokensEarned = :tot WHERE EmailId = :email');
                $upd->execute([
                    ':bal' => (int) $row->tokenBalance,
                    ':tot' => max((int) $row->totalEarnedTokens, (int) $row->tokenBalance),
                    ':email' => $row->userEmail,
                ]);
            }
        }
        echo "[+] Migrated legacy customer token balances into tbltokenledger.\n";
    }

    // 7. Password resets, Mail outbox, AI logs/cache, Rate limits
    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblpasswordresets` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `email` varchar(100) NOT NULL,
        `otp_hash` varchar(255) NOT NULL,
        `attempts` int(11) NOT NULL DEFAULT 0,
        `expires_at` datetime NOT NULL,
        `used_at` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_pr_email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblmailoutbox` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `recipient` varchar(150) NOT NULL,
        `subject` varchar(255) NOT NULL,
        `body` mediumtext NOT NULL,
        `status` varchar(20) NOT NULL DEFAULT 'queued_fallback',
        `error_message` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblailogs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `feature` varchar(50) NOT NULL,
        `tier_used` varchar(30) NOT NULL,
        `provider` varchar(40) NOT NULL,
        `latency_ms` int(11) NOT NULL DEFAULT 0,
        `success` tinyint(1) NOT NULL DEFAULT 1,
        `error_note` varchar(255) DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_ai_feature` (`feature`, `created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblaicache` (
        `cache_key` char(64) NOT NULL,
        `feature` varchar(50) NOT NULL,
        `response_json` mediumtext NOT NULL,
        `expires_at` datetime NOT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`cache_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $dbh->exec("CREATE TABLE IF NOT EXISTS `tblratelimits` (
        `id` bigint(20) NOT NULL AUTO_INCREMENT,
        `bucket_key` varchar(120) NOT NULL,
        `attempt_at` datetime NOT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_rl_bucket` (`bucket_key`, `attempt_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    echo "All migrations completed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    exit(1);
}
