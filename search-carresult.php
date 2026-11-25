<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

// Route legacy search-carresult.php filter posts cleanly to unified car-listing.php
$params = [];
if (!empty($_REQUEST['brand']) && is_numeric($_REQUEST['brand'])) {
    $params['brand'] = (int) $_REQUEST['brand'];
}
if (!empty($_REQUEST['fueltype']) && $_REQUEST['fueltype'] !== 'Select Fuel Type') {
    $params['fuel'] = trim((string) $_REQUEST['fueltype']);
}
$qs = $params ? '?' . http_build_query($params) : '';
Security::redirect('car-listing.php' . $qs);
