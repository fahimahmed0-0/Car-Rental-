<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

require_once __DIR__ . '/auth_handlers.php';

$pageTitle = $pageTitle ?? 'DriveX Assam | Car Rental Portal';
$activeNav = $activeNav ?? '';
$csrfToken = Csrf::token();
$flashes = Session::consumeAllFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo Security::e($csrfToken); ?>">
  <title><?php echo Security::e($pageTitle); ?></title>
  <link rel="shortcut icon" href="assets/images/favicon-icon/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/drivex.css">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-900 font-sans">

<?php include __DIR__ . '/header.php'; ?>

<?php if (!empty($flashes)): ?>
  <div class="mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 pt-4 space-y-2">
    <?php foreach ($flashes as $type => $msg):
      $styles = match ($type) {
          'success' => 'border-blue-600 bg-blue-50 text-blue-900',
          'error' => 'border-red-600 bg-red-50 text-red-900',
          default => 'border-sky-500 bg-sky-50 text-slate-900',
      };
    ?>
      <div class="flex items-center justify-between rounded-lg border-l-4 px-4 py-3 text-sm font-medium shadow-sm <?php echo $styles; ?>" role="alert">
        <span><?php echo Security::e($msg); ?></span>
        <button type="button" onclick="this.parentElement.remove()" class="ml-4 text-xs font-bold uppercase tracking-wider opacity-70 hover:opacity-100">Dismiss</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
