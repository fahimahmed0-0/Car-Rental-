<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\AuthService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $newPass = (string) ($_POST['newpassword'] ?? '');
    $confirm = (string) ($_POST['confirmpassword'] ?? '');
    if ($newPass !== $confirm) {
        Session::setFlash('error', 'New password and confirm password do not match.');
    } else {
        $auth = new AuthService($dbh);
        $res = $auth->updateAdminPassword((string) $_SESSION['alogin'], (string) ($_POST['password'] ?? ''), $newPass);
        Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
    }
    Security::redirect('change-password.php');
}

$adminTitle = 'Change Admin Password';
$adminNav = '';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-xl">
  <h1 class="text-2xl font-extrabold text-slate-950 mb-5">Change Admin Password</h1>
  <div class="card p-6">
    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Current Password</label>
        <input type="password" name="password" required class="form-input">
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">New Password (min 8 chars)</label>
        <input type="password" name="newpassword" minlength="8" required class="form-input">
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Confirm Password</label>
        <input type="password" name="confirmpassword" minlength="8" required class="form-input">
      </div>
      <button type="submit" name="submit" class="btn-primary">Save Password</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
