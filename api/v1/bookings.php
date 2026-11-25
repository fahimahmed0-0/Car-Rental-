<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Services\BookingService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Security::jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

Csrf::requireValid();

if (empty($_SESSION['login'])) {
    Security::jsonResponse(['success' => false, 'message' => 'Please sign in to manage bookings.'], 401);
}

$input = $_POST;
if (empty($input) && str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '', true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$action = (string) ($input['action'] ?? 'create');
$userEmail = (string) $_SESSION['login'];
$service = new BookingService($dbh);

try {
    if ($action === 'cancel') {
        $bookingNumber = (int) ($input['booking_number'] ?? 0);
        if ($bookingNumber <= 0) {
            Security::jsonResponse(['success' => false, 'message' => 'Invalid booking number.'], 400);
        }
        $res = $service->cancelCustomerBooking($bookingNumber, $userEmail);
        Security::jsonResponse($res, $res['success'] ? 200 : 400);
    }

    $vehicleId = (int) ($input['vehicle_id'] ?? 0);
    $fromDate = trim((string) ($input['from_date'] ?? ''));
    $toDate = trim((string) ($input['to_date'] ?? ''));
    $message = trim((string) ($input['message'] ?? ''));
    $tokens = (int) ($input['redeem_tokens'] ?? 0);
    $mode = (string) ($input['mode'] ?? 'pay_now');
    $pickup = trim((string) ($input['pickup_location'] ?? 'DriveX Hub, Guwahati, Assam'));
    $dropoff = trim((string) ($input['dropoff_location'] ?? 'DriveX Hub, Guwahati, Assam'));

    if ($vehicleId <= 0 || $fromDate === '' || $toDate === '') {
        Security::jsonResponse(['success' => false, 'message' => 'Vehicle and valid dates are required.'], 400);
    }

    $created = $service->createBooking($userEmail, $vehicleId, $fromDate, $toDate, $message, $tokens, $mode, $pickup, $dropoff);

    Security::jsonResponse([
        'success' => true,
        'booking_number' => $created['booking_number'],
        'mode' => $created['mode'],
        'quote' => $created['quote'],
        'redirect_url' => $created['mode'] === 'pay_now'
            ? 'payment.php?booking_id=' . $created['booking_number']
            : 'my-booking.php',
    ], 201);
} catch (Throwable $e) {
    Security::jsonResponse(['success' => false, 'message' => $e->getMessage()], 400);
}
