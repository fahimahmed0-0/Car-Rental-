<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Security;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\TokenLedgerService;

$vehicleId = (int) ($_GET['vehicle_id'] ?? 0);
if ($vehicleId <= 0) {
    Security::jsonResponse(['success' => false, 'message' => 'Invalid vehicle_id.'], 400);
}

$vStmt = $dbh->prepare('SELECT id, PricePerDay, VehiclesTitle FROM tblvehicles WHERE id = :id LIMIT 1');
$vStmt->execute([':id' => $vehicleId]);
$vehicle = $vStmt->fetch();

if (!$vehicle) {
    Security::jsonResponse(['success' => false, 'message' => 'Vehicle not found.'], 404);
}

$bookingService = new BookingService($dbh);
$bookedRanges = $bookingService->getBookedDateRanges($vehicleId);

$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$tokensRequested = (int) ($_GET['redeem_tokens'] ?? 0);

$response = [
    'success' => true,
    'vehicle_id' => $vehicleId,
    'daily_rate' => (float) $vehicle->PricePerDay,
    'booked_ranges' => $bookedRanges,
    'available' => null,
    'quote' => null,
];

if ($fromDate !== '' && $toDate !== '') {
    try {
        $balance = 0;
        if (!empty($_SESSION['login'])) {
            $tokens = new TokenLedgerService($dbh);
            $balance = $tokens->getBalance((string) $_SESSION['login'])['balance'];
        }

        $quote = PricingService::calculateQuote(
            (float) $vehicle->PricePerDay,
            $fromDate,
            $toDate,
            $tokensRequested,
            $balance,
            true
        );

        $response['available'] = $bookingService->isVehicleAvailable($vehicleId, $fromDate, $toDate, false);
        $response['quote'] = $quote;
        $response['token_balance'] = $balance;
    } catch (Throwable $e) {
        $response['available'] = false;
        $response['error'] = $e->getMessage();
    }
}

Security::jsonResponse($response);
