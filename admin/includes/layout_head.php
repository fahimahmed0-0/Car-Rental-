<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if (empty($_SESSION['alogin'])) {
    Security::redirect('index.php');
}

$adminTitle = $adminTitle ?? 'DriveX Admin';
$adminNav = $adminNav ?? '';
$csrfToken = Csrf::token();
$flashes = Session::consumeAllFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?php echo Security::e($csrfToken); ?>">
  <title><?php echo Security::e($adminTitle); ?> | DriveX Assam</title>
  <link rel="shortcut icon" href="../assets/images/favicon-icon/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/drivex.css">
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 font-sans" x-data="{ sidebarOpen: false }">

<?php include __DIR__ . '/header.php'; ?>

<div class="flex min-h-[calc(100vh-64px)]">
  <?php include __DIR__ . '/leftbar.php'; ?>

  <main class="flex-1 p-4 sm:p-6 lg:p-8 overflow-x-hidden">
    <?php if (!empty($flashes)): ?>
      <div class="mb-6 space-y-2">
        <?php foreach ($flashes as $type => $msg):
          $cls = match ($type) {
              'success' => 'border-blue-600 bg-blue-50 text-blue-900',
              'error' => 'border-red-600 bg-red-50 text-red-900',
              default => 'border-sky-500 bg-sky-50 text-slate-900',
          };
        ?>
          <div class="flex items-center justify-between rounded-lg border-l-4 px-4 py-3 text-sm font-medium shadow-sm <?php echo $cls; ?>">
            <span><?php echo Security::e($msg); ?></span>
            <button type="button" onclick="this.parentElement.remove()" class="ml-4 text-xs font-bold uppercase opacity-70 hover:opacity-100">Dismiss</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
