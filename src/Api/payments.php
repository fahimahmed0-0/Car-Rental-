<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Services\PaymentService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

Csrf::requireValid();

if (empty($_SESSION['login'])) {
    Security::jsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
}

$input = $_POST;
if (empty($input) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = (string) ($input['action'] ?? 'sandbox_pay');
$bookingNumber = (int) ($input['booking_number'] ?? 0);
$userEmail = (string) $_SESSION['login'];

if ($bookingNumber <= 0) {
    Security::jsonResponse(['success' => false, 'message' => 'Invalid booking number.'], 400);
}

$payments = new PaymentService($dbh);

try {
    if ($action === 'create_order') {
        $order = $payments->createOrderForBooking($bookingNumber, $userEmail);
        Security::jsonResponse(['success' => true, 'order' => $order]);
    }

    if ($action === 'verify_razorpay') {
        $res = $payments->verifyRazorpayPayment(
            $bookingNumber,
            $userEmail,
            (string) ($input['razorpay_order_id'] ?? ''),
            (string) ($input['razorpay_payment_id'] ?? ''),
            (string) ($input['razorpay_signature'] ?? '')
        );
        Security::jsonResponse($res, $res['success'] ? 200 : 400);
    }

    $method = (string) ($input['payment_method'] ?? 'upi');
    $idemKey = (string) ($input['idempotency_key'] ?? '');
    $res = $payments->processSandboxPayment($bookingNumber, $userEmail, $method, $idemKey ?: null);
    Security::jsonResponse($res, $res['success'] ? 200 : 400);
} catch (Throwable $e) {
    Security::jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
