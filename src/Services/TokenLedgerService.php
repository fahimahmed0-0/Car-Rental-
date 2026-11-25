<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Immutable loyalty token ledger replacing legacy conflicting token tables.
 */
final class TokenLedgerService
{
    private PDO $dbh;

    public function __construct(PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    /**
     * Returns authoritative balance & total earned directly from tbltokenledger.
     *
     * @return array{balance:int, total_earned:int}
     */
    public function getBalance(string $email): array
    {
        $email = strtolower(trim($email));
        $stmt = $this->dbh->prepare(
            "SELECT
                COALESCE(SUM(delta), 0) AS balance,
                COALESCE(SUM(CASE WHEN delta > 0 AND entry_type IN ('earn_completion', 'legacy_migration', 'admin_adjust') THEN delta ELSE 0 END), 0) AS total_earned
             FROM tbltokenledger
             WHERE user_email = :email"
        );
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        return [
            'balance' => max(0, (int) ($row->balance ?? 0)),
            'total_earned' => max(0, (int) ($row->total_earned ?? 0)),
        ];
    }

    public function getHistory(string $email, int $limit = 50): array
    {
        $stmt = $this->dbh->prepare(
            'SELECT l.*, b.FromDate, b.ToDate
             FROM tbltokenledger l
             LEFT JOIN tblbooking b ON b.id = l.booking_id
             WHERE l.user_email = :email
             ORDER BY l.id DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':email', strtolower(trim($email)), PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Deducts tokens for a booking inside an active or new transaction.
     */
    public function redeemForBooking(
        string $email,
        int $bookingId,
        int $bookingNumber,
        int $tokensToRedeem,
        float $discountAmount
    ): void {
        if ($tokensToRedeem <= 0) {
            return;
        }

        $email = strtolower(trim($email));
        $ownTx = !$this->dbh->inTransaction();
        if ($ownTx) {
            $this->dbh->beginTransaction();
        }

        try {
            // Lock user row to prevent concurrent double-spend
            $lock = $this->dbh->prepare('SELECT id FROM tblusers WHERE EmailId = :email FOR UPDATE');
            $lock->execute([':email' => $email]);

            $bal = $this->getBalance($email)['balance'];
            if ($bal < $tokensToRedeem) {
                throw new RuntimeException('Insufficient token balance for redemption.');
            }

            $ins = $this->dbh->prepare(
                'INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
                 VALUES (:email, :bid, :bnum, :etype, :delta, :disc, :note)'
            );
            $ins->execute([
                ':email' => $email,
                ':bid' => $bookingId,
                ':bnum' => $bookingNumber,
                ':etype' => 'redeem',
                ':delta' => -$tokensToRedeem,
                ':disc' => $discountAmount,
                ':note' => "Redeemed {$tokensToRedeem} tokens on booking #{$bookingNumber}",
            ]);

            $this->syncUserCache($email);

            if ($ownTx) {
                $this->dbh->commit();
            }
        } catch (Throwable $e) {
            if ($ownTx && $this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Awards trip completion tokens once per booking (idempotent via UNIQUE(booking_id, entry_type)).
     */
    public function awardOnCompletion(int $bookingId, int $bookingNumber, string $email): bool
    {
        $email = strtolower(trim($email));
        $tokens = PricingService::TOKENS_EARNED_PER_TRIP;

        // Check if already awarded
        $chk = $this->dbh->prepare(
            "SELECT id FROM tbltokenledger WHERE booking_id = :bid AND entry_type = 'earn_completion' LIMIT 1"
        );
        $chk->execute([':bid' => $bookingId]);
        if ($chk->fetch()) {
            return false;
        }

        $ins = $this->dbh->prepare(
            "INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
             VALUES (:email, :bid, :bnum, 'earn_completion', :delta, 0.00, :note)"
        );
        $ins->execute([
            ':email' => $email,
            ':bid' => $bookingId,
            ':bnum' => $bookingNumber,
            ':delta' => $tokens,
            ':note' => "Completed trip reward for booking #{$bookingNumber}",
        ]);

        $this->syncUserCache($email);
        return true;
    }

    /**
     * Refunds previously redeemed tokens if a booking is cancelled or expires.
     */
    public function refundForCancellation(int $bookingId, int $bookingNumber, string $email): int
    {
        $email = strtolower(trim($email));

        $redeemedStmt = $this->dbh->prepare(
            "SELECT delta FROM tbltokenledger WHERE booking_id = :bid AND entry_type = 'redeem' LIMIT 1"
        );
        $redeemedStmt->execute([':bid' => $bookingId]);
        $redeemRow = $redeemedStmt->fetch();
        if (!$redeemRow) {
            return 0;
        }

        $refundChk = $this->dbh->prepare(
            "SELECT id FROM tbltokenledger WHERE booking_id = :bid AND entry_type = 'refund_cancel' LIMIT 1"
        );
        $refundChk->execute([':bid' => $bookingId]);
        if ($refundChk->fetch()) {
            return 0;
        }

        $refundAmount = abs((int) $redeemRow->delta);
        $ins = $this->dbh->prepare(
            "INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
             VALUES (:email, :bid, :bnum, 'refund_cancel', :delta, 0.00, :note)"
        );
        $ins->execute([
            ':email' => $email,
            ':bid' => $bookingId,
            ':bnum' => $bookingNumber,
            ':delta' => $refundAmount,
            ':note' => "Token refund for cancelled booking #{$bookingNumber}",
        ]);

        $this->syncUserCache($email);
        return $refundAmount;
    }

    public function adminAdjust(string $email, int $delta, string $note): void
    {
        if ($delta === 0) {
            return;
        }
        $email = strtolower(trim($email));
        $current = $this->getBalance($email)['balance'];
        if ($current + $delta < 0) {
            throw new RuntimeException('Adjustment would result in negative token balance.');
        }

        $ins = $this->dbh->prepare(
            "INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
             VALUES (:email, NULL, NULL, 'admin_adjust', :delta, 0.00, :note)"
        );
        $ins->execute([
            ':email' => $email,
            ':delta' => $delta,
            ':note' => $note !== '' ? $note : 'Admin balance adjustment',
        ]);

        $this->syncUserCache($email);
    }

    public function getAdminSummary(): array
    {
        $totals = $this->dbh->query(
            "SELECT
                COALESCE(SUM(CASE WHEN delta > 0 THEN delta ELSE 0 END), 0) AS total_issued,
                COALESCE(SUM(CASE WHEN entry_type = 'redeem' THEN ABS(delta) ELSE 0 END), 0) AS total_redeemed,
                COALESCE(SUM(CASE WHEN entry_type = 'redeem' THEN discount_amount ELSE 0 END), 0) AS total_discount
             FROM tbltokenledger"
        )->fetch();

        $users = $this->dbh->query(
            "SELECT u.FullName, u.EmailId, u.TokenBalance, u.TotalTokensEarned, u.UpdationDate
             FROM tblusers u
             ORDER BY u.TokenBalance DESC, u.id DESC"
        )->fetchAll();

        return [
            'total_issued' => (int) ($totals->total_issued ?? 0),
            'total_redeemed' => (int) ($totals->total_redeemed ?? 0),
            'total_discount' => (float) ($totals->total_discount ?? 0),
            'users' => $users,
        ];
    }

    private function syncUserCache(string $email): void
    {
        $bal = $this->getBalance($email);
        $upd = $this->dbh->prepare(
            'UPDATE tblusers SET TokenBalance = :bal, TotalTokensEarned = :tot WHERE EmailId = :email'
        );
        $upd->execute([
            ':bal' => $bal['balance'],
            ':tot' => $bal['total_earned'],
            ':email' => $email,
        ]);
    }
}
