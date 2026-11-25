<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Env;
use App\Core\Security;
use App\Core\Session;

define('APP_ROOT', dirname(__DIR__));

// Register lightweight PSR-4 autoloader for App\* -> src/*
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

Env::load(APP_ROOT . DIRECTORY_SEPARATOR . '.env');
date_default_timezone_set(Env::get('APP_TIMEZONE', 'Asia/Kolkata') ?: 'Asia/Kolkata');

// Configure safe error handling & persistent log file
$logDir = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
if (is_dir($logDir) && is_writable($logDir)) {
    ini_set('log_errors', '1');
    ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'app.log');
}

if (Env::bool('APP_DEBUG', false)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
    ini_set('display_errors', '0');
}

if (PHP_SAPI !== 'cli') {
    Security::sendHeaders();
    Session::start();
}

// Global PDO instance for compatibility with existing pages and services
$dbh = Database::connection();

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return Security::e($value);
    }
}
