<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

// Route legacy search.php requests cleanly to unified car-listing.php without XSS
$q = trim((string) ($_REQUEST['searchdata'] ?? $_REQUEST['q'] ?? ''));
Security::redirect('car-listing.php' . ($q !== '' ? '?q=' . urlencode($q) : ''));
