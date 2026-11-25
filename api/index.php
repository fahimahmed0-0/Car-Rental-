<?php

declare(strict_types=1);

/**
 * Vercel Serverless Front Controller.
 * Routes allowlisted PHP entrypoints through a single vercel-php function
 * while preserving relative paths and blocking internal directories.
 */

$rootDir = dirname(__DIR__);
$rawRoute = (string) ($_GET['__route'] ?? '');

// Strip internal router param before downstream scripts read $_GET / $_REQUEST
unset($_GET['__route'], $_REQUEST['__route']);

if ($rawRoute === '') {
    $uriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $rawRoute = ltrim($uriPath, '/');
}

$route = ltrim(str_replace('\\', '/', $rawRoute), '/');
if ($route === '' || $route === '/') {
    $route = 'index.php';
} elseif ($route === 'admin' || $route === 'admin/') {
    $route = 'admin/index.php';
}

// Reject traversal or non-PHP routes
if (str_contains($route, '..') || !str_ends_with($route, '.php')) {
    http_response_code(404);
    exit('Not Found');
}

// Map api/v1/*.php to src/Api/*.php (so api/v1/ can stay ignored as standalone functions on Vercel)
if (preg_match('#^api/v1/([a-z0-9\-]+\.php)$#i', $route, $m)) {
    $apiFile = $rootDir . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . 'Api' . DIRECTORY_SEPARATOR . $m[1];
    if (is_file($apiFile)) {
        chdir(dirname($apiFile));
        require $apiFile;
        exit;
    }
    http_response_code(404);
    exit('API endpoint not found');
}

$allowedCustomerPages = [
    'index.php',
    'car-listing.php',
    'search.php',
    'search-carresult.php',
    'vehical-details.php',
    'booking-with-payment.php',
    'payment.php',
    'payment-success.php',
    'payment-history.php',
    'my-booking.php',
    'my-tokens.php',
    'profile.php',
    'update-password.php',
    'post-testimonial.php',
    'my-testimonials.php',
    'contact-us.php',
    'page.php',
    'check_availability.php',
    'logout.php',
];

$allowedAdminPages = [
    'admin/index.php',
    'admin/dashboard.php',
    'admin/manage-bookings.php',
    'admin/new-bookings.php',
    'admin/confirmed-bookings.php',
    'admin/completed-bookings.php',
    'admin/canceled-bookings.php',
    'admin/booking-details.php',
    'admin/bookig-details.php',
    'admin/manage-vehicles.php',
    'admin/post-avehical.php',
    'admin/edit-vehicle.php',
    'admin/changeimage1.php',
    'admin/changeimage2.php',
    'admin/changeimage3.php',
    'admin/changeimage4.php',
    'admin/changeimage5.php',
    'admin/manage-brands.php',
    'admin/create-brand.php',
    'admin/edit-brand.php',
    'admin/reg-users.php',
    'admin/token-management.php',
    'admin/testimonials.php',
    'admin/manage-conactusquery.php',
    'admin/manage-subscribers.php',
    'admin/manage-pages.php',
    'admin/update-contactinfo.php',
    'admin/change-password.php',
    'admin/mail-outbox.php',
    'admin/logout.php',
];

if (!in_array($route, $allowedCustomerPages, true) && !in_array($route, $allowedAdminPages, true)) {
    http_response_code(404);
    exit('Page not found');
}

$targetFile = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $route);
if (!is_file($targetFile)) {
    http_response_code(404);
    exit('Page not found');
}

// Match PHP_SELF and working directory to what the script expects on Apache/XAMPP
$_SERVER['PHP_SELF'] = '/' . $route;
$_SERVER['SCRIPT_NAME'] = '/' . $route;
chdir(dirname($targetFile));

require $targetFile;
