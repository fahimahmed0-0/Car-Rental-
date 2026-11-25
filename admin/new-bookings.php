<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminTitle = 'New / Pending Bookings';
$adminNav = 'bookings';
$forcedStatusFilter = 'pending';
include __DIR__ . '/includes/bookings_list_view.php';
