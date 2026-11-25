<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$vhid = isset($_GET['vhid']) ? (int) $_GET['vhid'] : 0;
if ($vhid > 0) {
    Security::redirect('vehical-details.php?vhid=' . $vhid);
}
Security::redirect('car-listing.php');
