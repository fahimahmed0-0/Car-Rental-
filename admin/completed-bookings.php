<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

$adminTitle = 'Completed Bookings';
$adminNav = 'completed';
$forcedStatusFilter = 'completed';
include __DIR__ . '/includes/bookings_list_view.php';
