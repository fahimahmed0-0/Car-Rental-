<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminTitle = 'Confirmed & Active Bookings';
$adminNav = 'confirmed';
$forcedStatusFilter = 'confirmed';
include __DIR__ . '/includes/bookings_list_view.php';
