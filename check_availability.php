<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$email = strtolower(trim((string) ($_POST['emailid'] ?? $_GET['emailid'] ?? '')));
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || (($_GET['format'] ?? '') === 'json');

if ($email === '') {
    if ($wantsJson) {
        Security::jsonResponse(['available' => false, 'message' => 'Email is required.'], 400);
    }
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    if ($wantsJson) {
        Security::jsonResponse(['available' => false, 'message' => 'Please enter a valid email address.']);
    }
    echo '<span class="text-red-600">Please enter a valid email address.</span>';
    exit;
}

$stmt = $dbh->prepare('SELECT id FROM tblusers WHERE EmailId = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$exists = (bool) $stmt->fetchColumn();

if ($wantsJson) {
    Security::jsonResponse([
        'available' => !$exists,
        'message' => $exists ? 'Email already exists.' : 'Email is available for registration.',
    ]);
}

if ($exists) {
    echo '<span style="color:#dc2626;font-weight:600;">Email already exists.</span>';
} else {
    echo '<span style="color:#1d4ed8;font-weight:600;">Email available for registration.</span>';
}
