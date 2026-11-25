<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

use App\Core\Security;

if (empty($_SESSION['alogin'])) {
    Security::jsonResponse(['success' => false, 'message' => 'Admin authentication required.'], 401);
}

// Monthly revenue & bookings (last 6 months)
$monthly = $dbh->query(
    "SELECT DATE_FORMAT(created_at, '%b %Y') AS label,
            DATE_FORMAT(created_at, '%Y-%m') AS ym,
            COALESCE(SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END), 0) AS revenue,
            COUNT(*) AS transactions
     FROM tblpaymenttransactions
     WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY ym, label
     ORDER BY ym ASC"
)->fetchAll();

// Booking status counts
$statusRows = $dbh->query(
    'SELECT Status, COUNT(*) AS cnt FROM tblbooking GROUP BY Status'
)->fetchAll();

$statusMap = [0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
foreach ($statusRows as $r) {
    $statusMap[(int) $r->Status] = (int) $r->cnt;
}

// Top vehicles by booking count
$topVehicles = $dbh->query(
    'SELECT v.VehiclesTitle, b.BrandName, COUNT(bk.id) AS bookings,
            COALESCE(SUM(CASE WHEN bk.PaymentStatus = "completed" THEN bk.FinalAmount ELSE 0 END), 0) AS revenue
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     LEFT JOIN tblbooking bk ON bk.VehicleId = v.id AND bk.Status IN (1, 3, 4)
     GROUP BY v.id, v.VehiclesTitle, b.BrandName
     ORDER BY bookings DESC, v.id DESC
     LIMIT 5'
)->fetchAll();

// AI tier usage summary
$aiTiers = $dbh->query(
    'SELECT tier_used, COUNT(*) AS cnt, ROUND(AVG(latency_ms)) AS avg_ms
     FROM tblailogs
     GROUP BY tier_used'
)->fetchAll();

Security::jsonResponse([
    'success' => true,
    'monthly' => $monthly,
    'status_counts' => [
        'pending' => $statusMap[0],
        'confirmed' => $statusMap[1],
        'cancelled' => $statusMap[2],
        'completed' => $statusMap[3],
        'active' => $statusMap[4],
        'expired' => $statusMap[5],
    ],
    'top_vehicles' => $topVehicles,
    'ai_tiers' => $aiTiers,
]);
