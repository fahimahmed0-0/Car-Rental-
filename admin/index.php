<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Database;
use App\Core\DemoDatabase;
use App\Core\Security;
use App\Core\Session;
use App\Services\AuthService;

if (!empty($_SESSION['alogin'])) {
    Security::redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    Csrf::requireValid();
    $auth = new AuthService($dbh);
    $res = $auth->attemptAdminLogin((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''));
    if ($res['success']) {
        Session::setFlash('success', $res['message']);
        Security::redirect('dashboard.php');
    }
    $error = $res['message'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Sign In | DriveX Assam</title>
  <link rel="shortcut icon" href="../assets/images/favicon-icon/favicon.png">
  <link rel="stylesheet" href="../assets/css/drivex.css">
</head>
<body class="min-h-screen bg-slate-950 flex items-center justify-center p-4 font-sans">
  <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-white p-8 shadow-2xl">
    <div class="text-center border-b border-slate-100 pb-5 mb-6">
      <div class="text-2xl font-extrabold text-slate-950">Drive<span class="text-red-600">X</span> <span class="text-blue-700">Admin</span></div>
      <p class="mt-1 text-xs text-slate-500">Sign in to manage fleet, bookings, payments &amp; tokens</p>
    </div>

    <?php if ($error): ?>
      <div class="mb-4 rounded-lg border-l-4 border-red-600 bg-red-50 p-3 text-xs font-semibold text-red-900">
        <?php echo Security::e($error); ?>
      </div>
    <?php endif; ?>

    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Admin Username</label>
        <input type="text" name="username" required class="form-input" placeholder="admin">
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Password</label>
        <input type="password" name="password" required class="form-input" placeholder="Enter admin password">
      </div>
      <button type="submit" name="login" class="btn-primary w-full !py-3">Sign In to Admin Panel</button>
    </form>

    <?php if (Database::isDemoMode()): ?>
      <div class="mt-5 rounded-xl border border-sky-200 bg-sky-50 p-3.5 text-xs">
        <div class="flex items-center justify-between gap-2">
          <div>
            <p class="font-bold text-slate-900">Demo Admin Access</p>
            <p class="mt-0.5 text-slate-600">
              User: <code class="font-mono text-slate-900"><?php echo Security::e(DemoDatabase::DEMO_ADMIN_USER); ?></code>
              &bull; Pass: <code class="font-mono text-slate-900"><?php echo Security::e(DemoDatabase::DEMO_ADMIN_PASS); ?></code>
            </p>
          </div>
          <button type="button"
                  onclick="var f=document.querySelector('form');f.querySelector('[name=username]').value='<?php echo Security::e(DemoDatabase::DEMO_ADMIN_USER); ?>';f.querySelector('[name=password]').value='<?php echo Security::e(DemoDatabase::DEMO_ADMIN_PASS); ?>';f.querySelector('[name=login]').click();"
                  class="shrink-0 rounded-lg bg-blue-700 px-3 py-1.5 font-semibold text-white hover:bg-blue-800 transition">
            Demo Sign In
          </button>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-6 text-center text-xs">
      <a href="../index.php" class="font-semibold text-slate-600 hover:text-blue-700">&larr; Back to Customer Portal</a>
    </div>
  </div>
</body>
</html>
