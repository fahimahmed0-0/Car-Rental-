<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\AuthService;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updatepass'])) {
    Csrf::requireValid();
    $newPass = (string) ($_POST['newpassword'] ?? '');
    $confirm = (string) ($_POST['confirmpassword'] ?? '');
    if ($newPass !== $confirm) {
        Session::setFlash('error', 'New password and confirm password do not match.');
    } else {
        $auth = new AuthService($dbh);
        $res = $auth->updateCustomerPassword((string) $_SESSION['login'], (string) ($_POST['password'] ?? ''), $newPass);
        Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
    }
    Security::redirect('update-password.php');
}

$pageTitle = 'DriveX Assam | Update Password';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">Update Password</h1>
    <p class="text-xs text-slate-300 mt-1">Keep your account secure with a strong password (minimum 8 characters).</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9">
        <div class="card p-6 sm:p-8 max-w-xl">
          <h2 class="text-lg font-extrabold text-slate-950 border-b border-slate-100 pb-3 mb-5">Change Account Password</h2>
          <form method="post" class="space-y-4">
            <?php echo Csrf::field(); ?>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Current Password</label>
              <input type="password" name="password" required class="form-input">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">New Password (min 8 characters)</label>
              <input type="password" name="newpassword" minlength="8" required class="form-input">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Confirm New Password</label>
              <input type="password" name="confirmpassword" minlength="8" required class="form-input">
            </div>
            <button type="submit" name="updatepass" class="btn-primary">Update Password</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
