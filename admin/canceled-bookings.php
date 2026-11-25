<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminTitle = 'Cancelled & Expired Bookings';
$adminNav = 'canceled';
$forcedStatusFilter = 'cancelled';
include __DIR__ . '/includes/bookings_list_view.php';
