<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Services\AuthService;

AuthService::logoutAdmin();
Security::redirect('index.php');
