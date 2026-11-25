<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Core\Security;
use App\Services\BookingService;

$q = trim((string) ($_GET['q'] ?? ''));
$brand = isset($_GET['brand']) && $_GET['brand'] !== '' ? (int) $_GET['brand'] : null;
$fuel = trim((string) ($_GET['fuel'] ?? ''));
$transmission = trim((string) ($_GET['transmission'] ?? ''));
$seats = isset($_GET['seats']) && $_GET['seats'] !== '' ? (int) $_GET['seats'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int) $_GET['max_price'] : null;
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$limit = min(50, max(1, (int) ($_GET['limit'] ?? 24)));

// Expire stale locks before availability filter
$bookingService = new BookingService($dbh);
$bookingService->expireStalePendingLocks();

$where = ['1=1'];
$params = [];

if ($q !== '') {
    $where[] = '(v.VehiclesTitle LIKE :q1 OR b.BrandName LIKE :q2 OR v.FuelType LIKE :q3 OR v.VehiclesOverview LIKE :q4 OR CAST(v.ModelYear AS CHAR) LIKE :q5)';
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
    $params[':q4'] = $like;
    $params[':q5'] = $like;
}
if ($brand !== null && $brand > 0) {
    $where[] = 'v.VehiclesBrand = :brand';
    $params[':brand'] = $brand;
}
if ($fuel !== '') {
    $where[] = 'v.FuelType = :fuel';
    $params[':fuel'] = $fuel;
}
if ($transmission !== '') {
    $where[] = 'v.Transmission = :trans';
    $params[':trans'] = $transmission;
}
if ($seats !== null && $seats > 0) {
    $where[] = 'v.SeatingCapacity >= :seats';
    $params[':seats'] = $seats;
}
if ($maxPrice !== null && $maxPrice > 0) {
    $where[] = 'v.PricePerDay <= :maxp';
    $params[':maxp'] = $maxPrice;
}

$availabilitySelect = '1 AS is_available';
if ($fromDate !== '' && $toDate !== '') {
    $availabilitySelect = "CASE WHEN EXISTS (
        SELECT 1 FROM tblbooking bk
        WHERE bk.VehicleId = v.id
          AND (
              bk.Status IN (" . BookingService::STATUS_CONFIRMED . ", " . BookingService::STATUS_ACTIVE . ")
              OR (bk.Status = " . BookingService::STATUS_PENDING . " AND (bk.PriceLockUntil IS NULL OR bk.PriceLockUntil >= NOW()))
          )
          AND NOT (DATE(bk.ToDate) < :avail_from OR DATE(bk.FromDate) > :avail_to)
    ) THEN 0 ELSE 1 END AS is_available";
    $params[':avail_from'] = $fromDate;
    $params[':avail_to'] = $toDate;
}

$orderBy = match ($sort) {
    'price_asc' => 'v.PricePerDay ASC, v.id DESC',
    'price_desc' => 'v.PricePerDay DESC, v.id DESC',
    'year_desc' => 'v.ModelYear DESC, v.id DESC',
    default => 'v.id DESC',
};

$sql = "SELECT v.id, v.VehiclesTitle, v.VehiclesBrand, v.VehiclesOverview, v.PricePerDay,
               v.FuelType, v.ModelYear, v.SeatingCapacity, v.Transmission, v.Vimage1,
               b.BrandName, {$availabilitySelect}
        FROM tblvehicles v
        JOIN tblbrands b ON b.id = v.VehiclesBrand
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
        LIMIT :lim";

$stmt = $dbh->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();

$vehicles = $stmt->fetchAll();

Security::jsonResponse([
    'success' => true,
    'count' => count($vehicles),
    'vehicles' => $vehicles,
]);
