<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use App\Core\Security;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Payment orchestrator supporting live Razorpay and built-in Sandbox fallback.
 */
final class PaymentService
{
    public const VALID_METHODS = [
        'upi' => 'UPI (GPay, PhonePe, BHIM)',
        'card' => 'Credit / Debit / ATM Card',
        'wallet' => 'Mobile Wallet',
        'netbanking' => 'Net Banking',
    ];

    private PDO $dbh;
    private TokenLedgerService $tokens;
    private MailService $mailer;

    public function __construct(PDO $dbh, ?TokenLedgerService $tokens = null, ?MailService $mailer = null)
    {
        $this->dbh = $dbh;
        $this->tokens = $tokens ?? new TokenLedgerService($dbh);
        $this->mailer = $mailer ?? new MailService($dbh);
    }

    public function activeProvider(): string
    {
        $mode = strtolower(trim((string) Env::get('PAYMENT_MODE', 'sandbox')));
        $keyId = trim((string) Env::get('RAZORPAY_KEY_ID', ''));
        $secret = trim((string) Env::get('RAZORPAY_KEY_SECRET', ''));

        if ($mode === 'razorpay' && $keyId !== '' && $secret !== '') {
            return 'razorpay';
        }
        return 'sandbox';
    }

    /**
     * Prepares an authoritative checkout order from DB values (never trusts URL ?amount=).
     */
    public function createOrderForBooking(int $bookingNumber, string $userEmail): array
    {
        $booking = $this->loadPayableBooking($bookingNumber, $userEmail);
        $amount = (float) $booking->FinalAmount;
        $provider = $this->activeProvider();

        if ($amount <= 0.0) {
            return [
                'provider' => 'sandbox',
                'amount' => 0.0,
                'currency' => 'INR',
                'order_id' => 'FREE_' . $bookingNumber,
                'upi_uri' => null,
                'razorpay_key' => null,
            ];
        }

        if ($provider === 'razorpay') {
            $rzpOrder = $this->createRazorpayOrder($bookingNumber, $amount);
            if ($rzpOrder !== null) {
                return [
                    'provider' => 'razorpay',
                    'amount' => $amount,
                    'currency' => 'INR',
                    'order_id' => $rzpOrder['id'],
                    'razorpay_key' => Env::get('RAZORPAY_KEY_ID', ''),
                    'upi_uri' => null,
                ];
            }
            // Fallback gracefully to sandbox if Razorpay endpoint is unreachable in dev
            $provider = 'sandbox';
        }

        $upiUri = sprintf(
            'upi://pay?pa=drivexassam@sandbox&pn=%s&am=%.2f&cu=INR&tn=%s',
            rawurlencode('DriveX Assam'),
            $amount,
            rawurlencode('Booking #' . $bookingNumber)
        );

        return [
            'provider' => $provider,
            'amount' => $amount,
            'currency' => 'INR',
            'order_id' => 'SBX_' . $bookingNumber . '_' . time(),
            'razorpay_key' => null,
            'upi_uri' => $upiUri,
        ];
    }

    /**
     * Completes a Sandbox payment atomically inside a DB transaction.
     *
     * @return array{success:bool, transaction_id?:string, message:string}
     */
    public function processSandboxPayment(
        int $bookingNumber,
        string $userEmail,
        string $method,
        ?string $idempotencyKey = null
    ): array {
        $method = strtolower(trim($method));
        if (!array_key_exists($method, self::VALID_METHODS)) {
            return ['success' => false, 'message' => 'Please select a valid payment method.'];
        }

        $userEmail = strtolower(trim($userEmail));
        $idempotencyKey = $idempotencyKey ?: ('sbx_' . $bookingNumber . '_' . bin2hex(random_bytes(8)));

        $this->dbh->beginTransaction();
        try {
            // Check idempotency
            $idemStmt = $this->dbh->prepare('SELECT transaction_id, payment_status FROM tblpaymenttransactions WHERE idempotency_key = :ik LIMIT 1');
            $idemStmt->execute([':ik' => $idempotencyKey]);
            if ($existing = $idemStmt->fetch()) {
                $this->dbh->commit();
                return [
                    'success' => $existing->payment_status === 'completed',
                    'transaction_id' => (string) $existing->transaction_id,
                    'message' => 'Payment already processed.',
                ];
            }

            $booking = $this->loadPayableBooking($bookingNumber, $userEmail, true);
            $amount = (float) $booking->FinalAmount;
            $txnId = 'TXN' . date('YmdHis') . strtoupper(bin2hex(random_bytes(3)));

            $payload = json_encode([
                'mode' => 'sandbox',
                'method' => $method,
                'booking_number' => $bookingNumber,
                'amount' => $amount,
                'status' => 'success',
                'timestamp' => date(DATE_ATOM),
            ], JSON_UNESCAPED_UNICODE);

            $ins = $this->dbh->prepare(
                "INSERT INTO tblpaymenttransactions
                 (booking_id, user_email, provider, payment_method, amount, transaction_id, idempotency_key, payment_status, gateway_response)
                 VALUES (:bnum, :email, 'sandbox', :method, :amt, :txn, :ik, 'completed', :resp)"
            );
            $ins->execute([
                ':bnum' => $bookingNumber,
                ':email' => $userEmail,
                ':method' => $method,
                ':amt' => $amount,
                ':txn' => $txnId,
                ':ik' => $idempotencyKey,
                ':resp' => $payload,
            ]);

            $this->finalizeBookingAndTokens($booking, $method);
            $this->dbh->commit();

            $this->sendReceiptEmail($booking, $userEmail, $txnId, $method, $amount);

            return [
                'success' => true,
                'transaction_id' => $txnId,
                'message' => 'Payment processed successfully.',
            ];
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Verifies Razorpay signature and finalizes booking atomically.
     *
     * @return array{success:bool, transaction_id?:string, message:string}
     */
    public function verifyRazorpayPayment(
        int $bookingNumber,
        string $userEmail,
        string $orderId,
        string $paymentId,
        string $signature
    ): array {
        $secret = trim((string) Env::get('RAZORPAY_KEY_SECRET', ''));
        if ($secret === '') {
            return ['success' => false, 'message' => 'Razorpay secret is not configured.'];
        }

        $expected = hash_hmac('sha256', $orderId . '|' . $paymentId, $secret);
        if (!hash_equals($expected, $signature)) {
            return ['success' => false, 'message' => 'Payment signature verification failed.'];
        }

        $userEmail = strtolower(trim($userEmail));
        $this->dbh->beginTransaction();
        try {
            $booking = $this->loadPayableBooking($bookingNumber, $userEmail, true);
            $amount = (float) $booking->FinalAmount;

            $ins = $this->dbh->prepare(
                "INSERT INTO tblpaymenttransactions
                 (booking_id, user_email, provider, payment_method, amount, transaction_id, idempotency_key, gateway_order_id, gateway_signature, payment_status, gateway_response)
                 VALUES (:bnum, :email, 'razorpay', 'razorpay', :amt, :txn, :ik, :oid, :sig, 'completed', :resp)"
            );
            $ins->execute([
                ':bnum' => $bookingNumber,
                ':email' => $userEmail,
                ':amt' => $amount,
                ':txn' => $paymentId,
                ':ik' => 'rzp_' . $paymentId,
                ':oid' => $orderId,
                ':sig' => $signature,
                ':resp' => json_encode(['order_id' => $orderId, 'payment_id' => $paymentId]),
            ]);

            $this->finalizeBookingAndTokens($booking, 'razorpay');
            $this->dbh->commit();

            $this->sendReceiptEmail($booking, $userEmail, $paymentId, 'razorpay', $amount);

            return [
                'success' => true,
                'transaction_id' => $paymentId,
                'message' => 'Razorpay payment verified and confirmed.',
            ];
        } catch (Throwable $e) {
            if ($this->dbh->inTransaction()) {
                $this->dbh->rollBack();
            }
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getUserPaymentHistory(string $userEmail, int $limit = 50): array
    {
        $stmt = $this->dbh->prepare(
            'SELECT pt.*, b.BookingNumber, b.FromDate, b.ToDate, v.VehiclesTitle, br.BrandName
             FROM tblpaymenttransactions pt
             JOIN tblbooking b ON pt.booking_id = b.BookingNumber
             JOIN tblvehicles v ON b.VehicleId = v.id
             JOIN tblbrands br ON v.VehiclesBrand = br.id
             WHERE pt.user_email = :email
             ORDER BY pt.created_at DESC
             LIMIT :lim'
        );
        $stmt->bindValue(':email', strtolower(trim($userEmail)), PDO::PARAM_STR);
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function loadPayableBooking(int $bookingNumber, string $userEmail, bool $forUpdate = false): object
    {
        $sql = 'SELECT b.*, v.VehiclesTitle,
                       (b.PriceLockUntil IS NOT NULL AND b.PriceLockUntil < NOW()) AS is_lock_expired
                FROM tblbooking b
                JOIN tblvehicles v ON v.id = b.VehicleId
                WHERE b.BookingNumber = :bnum AND b.userEmail = :email
                LIMIT 1';
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $stmt = $this->dbh->prepare($sql);
        $stmt->execute([
            ':bnum' => $bookingNumber,
            ':email' => strtolower(trim($userEmail)),
        ]);
        $booking = $stmt->fetch();

        if (!$booking) {
            throw new RuntimeException('Booking not found for your account.');
        }
        if ((string) $booking->PaymentStatus === 'completed') {
            throw new RuntimeException('This booking has already been paid.');
        }
        if ((int) $booking->Status !== BookingService::STATUS_PENDING) {
            throw new RuntimeException('This booking is no longer awaiting payment.');
        }
        if ((int) ($booking->is_lock_expired ?? 0) === 1) {
            $this->dbh->prepare(
                "UPDATE tblbooking SET Status = " . BookingService::STATUS_EXPIRED . ", PaymentStatus = 'expired' WHERE id = :id"
            )->execute([':id' => (int) $booking->id]);
            throw new RuntimeException('Your 10-minute price lock expired. Please re-book to refresh availability.');
        }

        return $booking;
    }

    private function finalizeBookingAndTokens(object $booking, string $method): void
    {
        $upd = $this->dbh->prepare(
            "UPDATE tblbooking
             SET Status = " . BookingService::STATUS_CONFIRMED . ",
                 PaymentMethod = :method,
                 PaymentStatus = 'completed',
                 PaymentDate = NOW(),
                 PriceLockUntil = NULL
             WHERE id = :id"
        );
        $upd->execute([
            ':method' => $method,
            ':id' => (int) $booking->id,
        ]);

        $tokensUsed = (int) ($booking->TokensUsed ?? 0);
        if ($tokensUsed > 0) {
            $this->tokens->redeemForBooking(
                (string) $booking->userEmail,
                (int) $booking->id,
                (int) $booking->BookingNumber,
                $tokensUsed,
                (float) $booking->DiscountAmount
            );
        }
    }

    private function createRazorpayOrder(int $bookingNumber, float $amountInr): ?array
    {
        $keyId = trim((string) Env::get('RAZORPAY_KEY_ID', ''));
        $secret = trim((string) Env::get('RAZORPAY_KEY_SECRET', ''));
        if ($keyId === '' || $secret === '') {
            return null;
        }

        $ch = curl_init('https://api.razorpay.com/v1/orders');
        if ($ch === false) {
            return null;
        }

        $payload = json_encode([
            'amount' => (int) round($amountInr * 100),
            'currency' => 'INR',
            'receipt' => 'rcpt_' . $bookingNumber,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_USERPWD => $keyId . ':' . $secret,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 6,
        ]);

        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $code < 200 || $code >= 300) {
            return null;
        }

        $decoded = json_decode($resp, true);
        return is_array($decoded) && isset($decoded['id']) ? $decoded : null;
    }

    private function sendReceiptEmail(object $booking, string $email, string $txnId, string $method, float $amount): void
    {
        try {
            $body = '<h3>Payment Confirmed - Booking #' . (int) $booking->BookingNumber . '</h3>'
                . '<p>Thank you for choosing <strong>DriveX Assam</strong>!</p>'
                . '<p><strong>Vehicle:</strong> ' . Security::e($booking->VehiclesTitle) . '</p>'
                . '<p><strong>Dates:</strong> ' . Security::e($booking->FromDate) . ' to ' . Security::e($booking->ToDate) . '</p>'
                . '<p><strong>Amount Paid:</strong> ₹' . number_format($amount, 2) . ' via ' . Security::e(strtoupper($method)) . '</p>'
                . '<p><strong>Transaction ID:</strong> ' . Security::e($txnId) . '</p>';

            $this->mailer->send($email, $email, 'DriveX Assam - Payment Receipt #' . (int) $booking->BookingNumber, $body);
        } catch (Throwable $e) {
            error_log('Payment receipt email error: ' . $e->getMessage());
        }
    }
}
