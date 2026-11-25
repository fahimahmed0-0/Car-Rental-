<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminTitle = 'Manage Bookings';
$adminNav = 'bookings';
$forcedStatusFilter = null;
include __DIR__ . '/includes/bookings_list_view.php';
