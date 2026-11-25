<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Concurrency-safe booking lifecycle, availability checks, and status transitions.
 */
final class BookingService
{
    public const STATUS_PENDING = 0;
    public const STATUS_CONFIRMED = 1;
    public const STATUS_CANCELLED = 2;
    public const STATUS_COMPLETED = 3;
    public const STATUS_ACTIVE = 4;
    public const STATUS_EXPIRED = 5;

    private PDO $dbh;
    private TokenLedgerService $tokens;
    private MailService $mailer;

    public function __construct(PDO $dbh, ?TokenLedgerService $tokens = null, ?MailService $mailer = null)
    {
        $this->dbh = $dbh;
        $this->tokens = $tokens ?? new TokenLedgerService($dbh);
        $this->mailer = $mailer ?? new MailService($dbh);
    }

    public static function statusLabel(int $status, ?string $paymentStatus = null): string
    {
        return match ($status) {
            self::STATUS_PENDING => $paymentStatus === 'pending' ? 'Pending Payment' : 'Pending Confirmation',
            self::STATUS_CONFIRMED => 'Confirmed',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_ACTIVE => 'Active Trip',
            self::STATUS_EXPIRED => 'Expired',
            default => 'Unknown',
        };
    }

    /**
     * Releases any pending online-payment locks that have passed their 10-minute window.
     */
    public function expireStalePendingLocks(): void
    {
        $this->dbh->exec(
            "UPDATE tblbooking
             SET Status = " . self::STATUS_EXPIRED . ", PaymentStatus = 'expired'
             WHERE Status = " . self::STATUS_PENDING . "
               AND PaymentStatus = 'pending'
               AND PriceLockUntil IS NOT NULL
               AND PriceLockUntil < NOW()"
        );
    }

    /**
     * Checks if a vehicle is free across [fromDate, toDate] inclusive.
     * Cancelled (2), Completed (3), and Expired (5) bookings do NOT block availability.
     */
    public function isVehicleAvailable(int $vehicleId, string $fromDate, string $toDate, bool $forUpdate = false): bool
    {
        $sql = "SELECT id FROM tblbooking
                WHERE VehicleId = :vhid
                  AND (
                      Status IN (" . self::STATUS_CONFIRMED . ", " . self::STATUS_ACTIVE . ")
                      OR (Status = " . self::STATUS_PENDING . " AND (PriceLockUntil IS NULL OR PriceLockUntil >= NOW()))
                  )
                  AND NOT (DATE(ToDate) < :from_date OR DATE(FromDate) > :to_date)";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':vhid' => $vehicleId,
            ':from_date' => $fromDate,
            ':to_date' => $toDate,
        ]);

        return $stmt->fetch() === false;
    }

    /**
     * Returns active/upcoming booked date windows for UI calendar blocking.
     *
     * @return array<int, array{from:string, to:string, status:int}>
     */
    public function getBookedDateRanges(int $vehicleId): array
    {
        $this->expireStalePendingLocks();

        $stmt = $this->dbh->prepare(
            "SELECT FromDate, ToDate, Status FROM tblbooking
             WHERE VehicleId = :vhid
               AND (
                   Status IN (" . self::STATUS_CONFIRMED . ", " . self::STATUS_ACTIVE . ")
                   OR (Status = " . self::STATUS_PENDING . " AND (PriceLockUntil IS NULL OR PriceLockUntil >= NOW()))
               )
               AND DATE(ToDate) >= CURDATE()
             ORDER BY FromDate ASC"
        );
        $stmt->execute([':vhid' => $vehicleId]);
        $rows = $stmt->fetchAll();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'from' => (string) $row->FromDate,
                'to' => (string) $row->ToDate,
                'status' => (int) $row->Status,
            ];
        }
        return $out;
    }

    /**
     * Creates a booking transactionally with row-level lock & server-calculated pricing.
     *
     * @return array{booking_id:int, booking_number:int, quote:array, mode:string}
     */
    public function createBooking(
        string $userEmail,
        int $vehicleId,
        string $fromDate,
        string $toDate,
        string $message,
        int $tokensRequested = 0,
        string $mode = 'pay_now',
        string $pickupLocation = 'DriveX Hub, Guwahati, Assam',
        string $dropoffLocation = 'DriveX Hub, Guwahati, Assam'
    ): array {
        $userEmail = strtolower(trim($userEmail));
        $mode = $mode === 'pay_at_pickup' ? 'pay_at_pickup' : 'pay_now';
        $message = trim($message);

        $this->expireStalePendingLocks();
        $this->dbh->beginTransaction();

        try {
            // Lock vehicle row
            $vStmt = $this->dbh->prepare(
                'SELECT v.*, b.BrandName
                 FROM tblvehicles v
                 JOIN tblbrands b ON b.id = v.VehiclesBrand
                 WHERE v.id = :vhid FOR UPDATE'
            );
            $vStmt->execute([':vhid' => $vehicleId]);
            $vehicle = $vStmt->fetch();
            if (!$vehicle) {
                throw new RuntimeException('Selected vehicle was not found.');
            }

            // Lock overlapping bookings & check availability
            if (!$this->isVehicleAvailable($vehicleId, $fromDate, $toDate, true)) {
                throw new RuntimeException('This vehicle is already reserved for the selected dates.');
            }

            $balance = $this->tokens->getBalance($userEmail)['balance'];
            $quote = PricingService::calculateQuote(
                (float) $vehicle->PricePerDay,
                $fromDate,
                $toDate,
                $tokensRequested,
                $balance,
                true
            );

            $bookingNumber = $this->generateUniqueBookingNumber();
            $paymentStatus = $mode === 'pay_at_pickup' ? 'pay_at_pickup' : 'pending';
            $lockExpr = $mode === 'pay_now' ? 'DATE_ADD(NOW(), INTERVAL 10 MINUTE)' : 'NULL';

            // If 100% discounted by tokens in pay_now mode, still go through checkout confirmation cleanly
            $ins = $this->dbh->prepare(
                "INSERT INTO tblbooking
                 (BookingNumber, userEmail, VehicleId, FromDate, ToDate, message, Status, PaymentStatus, Amount, DiscountAmount, TaxAmount, FinalAmount, TokensUsed, PriceLockUntil, PickupLocation, DropoffLocation)
                 VALUES (:bnum, :email, :vhid, :from_d, :to_d, :msg, :status, :pstatus, :amt, :disc, :tax, :final, :tok, {$lockExpr}, :pick, :drop)"
            );
            $ins->execute([
                ':bnum' => $bookingNumber,
                ':email' => $userEmail,
                ':vhid' => $vehicleId,
                ':from_d' => $fromDate,
                ':to_d' => $toDate,
                ':msg' => $message,
                ':status' => self::STATUS_PENDING,
                ':pstatus' => $paymentStatus,
                ':amt' => $quote['base_amount'],
                ':disc' => $quote['discount_amount'],
                ':tax' => $quote['tax_amount'],
                ':final' => $quote['final_amount'],
                ':tok' => $quote['tokens_used'],
                ':pick' => $pickupLocation,
                ':drop' => $dropoffLocation,
            ]);

            $bookingId = (int) $this->dbh->lastInsertId();

            // Pay-at-pickup reserves immediately; deduct tokens atomically now (refunded if cancelled)
            if ($mode === 'pay_at_pickup' && $quote['tokens_used'] > 0) {
                $this->tokens->redeemForBooking(
                    $userEmail,
                    $bookingId,
                    $bookingNumber,
                    $quote['tokens_used'],
                    $quote['discount_amount']
                );
            }

            $this->dbh->commit();

            // Dispatch notification email (non-blocking via outbox fallback)
            $this->sendBookingNotification($bookingNumber, $userEmail, $vehicle, $fromDate, $toDate, $quote, $mode, $message);

            return [
                'booking_id' => $bookingId,
                'booking_number' => $bookingNumber,
                'quote' => $quote,
                'mode' => $mode,
            ];
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            throw $e;
        }
    }

    public function getBookingByNumberForUser(int $bookingNumber, string $userEmail): ?object
    {
        $stmt = $this->dbh->prepare(
            'SELECT b.*, v.VehiclesTitle, v.FuelType, v.ModelYear, v.SeatingCapacity, v.PricePerDay, v.Vimage1, br.BrandName,
                    GREATEST(0, COALESCE(TIMESTAMPDIFF(SECOND, NOW(), b.PriceLockUntil), 600)) AS lock_seconds_remaining
             FROM tblbooking b
             JOIN tblvehicles v ON v.id = b.VehicleId
             JOIN tblbrands br ON br.id = v.VehiclesBrand
             WHERE b.BookingNumber = :bnum AND b.userEmail = :email
             LIMIT 1'
        );
        $stmt->execute([
            ':bnum' => $bookingNumber,
            ':email' => strtolower(trim($userEmail)),
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Admin status transition with enforced workflow and idempotent token award/refund.
     *
     * @return array{success:bool, message:string}
     */
    public function transitionStatus(int $bookingId, int $newStatus): array
    {
        $allowed = [
            self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
            self::STATUS_CONFIRMED => [self::STATUS_ACTIVE, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_ACTIVE => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
            self::STATUS_EXPIRED => [],
        ];

        $this->dbh->beginTransaction();
        try {
            $stmt = $this->dbh->prepare('SELECT * FROM tblbooking WHERE id = :id FOR UPDATE');
            $stmt->execute([':id' => $bookingId]);
            $booking = $stmt->fetch();

            if (!$booking) {
                throw new RuntimeException('Booking not found.');
            }

            $currentStatus = (int) $booking->Status;
            if ($currentStatus === $newStatus) {
                $this->dbh->commit();
                return ['success' => true, 'message' => 'Booking is already in that status.'];
            }

            if (!in_array($newStatus, $allowed[$currentStatus] ?? [], true)) {
                throw new RuntimeException(
                    'Invalid transition from ' . self::statusLabel($currentStatus) . ' to ' . self::statusLabel($newStatus) . '.'
                );
            }

            $this->dbh->prepare('UPDATE tblbooking SET Status = :st WHERE id = :id')
                ->execute([':st' => $newStatus, ':id' => $bookingId]);

            $msg = 'Booking status updated to ' . self::statusLabel($newStatus) . '.';

            if ($newStatus === self::STATUS_COMPLETED) {
                $awarded = $this->tokens->awardOnCompletion(
                    $bookingId,
                    (int) $booking->BookingNumber,
                    (string) $booking->userEmail
                );
                if ($awarded) {
                    $msg .= ' Awarded ' . PricingService::TOKENS_EARNED_PER_TRIP . ' loyalty tokens.';
                }
            } elseif ($newStatus === self::STATUS_CANCELLED) {
                $refunded = $this->tokens->refundForCancellation(
                    $bookingId,
                    (int) $booking->BookingNumber,
                    (string) $booking->userEmail
                );
                if ($refunded > 0) {
                    $msg .= " Refunded {$refunded} redeemed tokens.";
                }
            }

            $this->dbh->commit();
            return ['success' => true, 'message' => $msg];
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Allows a logged-in customer to cancel their own pending/confirmed booking before trip start.
     *
     * @return array{success:bool, message:string}
     */
    public function cancelCustomerBooking(int $bookingNumber, string $userEmail): array
    {
        $stmt = $this->dbh->prepare('SELECT id, Status FROM tblbooking WHERE BookingNumber = :bnum AND userEmail = :email LIMIT 1');
        $stmt->execute([
            ':bnum' => $bookingNumber,
            ':email' => strtolower(trim($userEmail)),
        ]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['success' => false, 'message' => 'Booking not found for your account.'];
        }
        if (!in_array((int) $row->Status, [self::STATUS_PENDING, self::STATUS_CONFIRMED], true)) {
            return ['success' => false, 'message' => 'Only pending or confirmed bookings can be cancelled.'];
        }

        return $this->transitionStatus((int) $row->id, self::STATUS_CANCELLED);
    }

    private function generateUniqueBookingNumber(): int
    {
        for ($i = 0; $i < 10; $i++) {
            $num = random_int(100000000, 999999999);
            $chk = $this->dbh->prepare('SELECT id FROM tblbooking WHERE BookingNumber = :b LIMIT 1');
            $chk->execute([':b' => $num]);
            if (!$chk->fetch()) {
                return $num;
            }
        }
        return (int) (time() . random_int(10, 99));
    }

    private function sendBookingNotification(
        int $bookingNumber,
        string $userEmail,
        object $vehicle,
        string $fromDate,
        string $toDate,
        array $quote,
        string $mode,
        string $message
    ): void {
        try {
            $uStmt = $this->dbh->prepare('SELECT FullName, ContactNo FROM tblusers WHERE EmailId = :email LIMIT 1');
            $uStmt->execute([':email' => $userEmail]);
            $user = $uStmt->fetch();

            $customerName = $user->FullName ?? $userEmail;
            $phone = $user->ContactNo ?? 'N/A';

            $body = '<h3>Booking Request #' . $bookingNumber . '</h3>'
                . '<p><strong>Customer:</strong> ' . Security::e($customerName) . ' (' . Security::e($userEmail) . ', ' . Security::e($phone) . ')</p>'
                . '<p><strong>Vehicle:</strong> ' . Security::e($vehicle->BrandName . ' ' . $vehicle->VehiclesTitle) . '</p>'
                . '<p><strong>Dates:</strong> ' . Security::e($fromDate) . ' to ' . Security::e($toDate) . ' (' . $quote['days'] . ' days)</p>'
                . '<p><strong>Total:</strong> ₹' . number_format((float) $quote['final_amount'], 2) . ' (' . Security::e($mode) . ')</p>'
                . ($message !== '' ? '<p><strong>Notes:</strong> ' . Security::e($message) . '</p>' : '');

            $this->mailer->notifyAdmin('New Car Booking #' . $bookingNumber, $body, $userEmail);
        } catch (Throwable $e) {
            error_log('Booking notification error: ' . $e->getMessage());
        }
    }
}
