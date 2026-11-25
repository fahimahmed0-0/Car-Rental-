<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $address = trim((string) ($_POST['address'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $contactno = trim((string) ($_POST['contactno'] ?? ''));

    $count = (int) $dbh->query('SELECT COUNT(*) FROM tblcontactusinfo')->fetchColumn();
    if ($count > 0) {
        $dbh->prepare('UPDATE tblcontactusinfo SET Address = :a, EmailId = :e, ContactNo = :c')
            ->execute([':a' => $address, ':e' => $email, ':c' => $contactno]);
    } else {
        $dbh->prepare('INSERT INTO tblcontactusinfo (Address, EmailId, ContactNo) VALUES (:a, :e, :c)')
            ->execute([':a' => $address, ':e' => $email, ':c' => $contactno]);
    }

    Session::setFlash('success', 'Contact information updated successfully.');
    Security::redirect('update-contactinfo.php');
}

$info = $dbh->query('SELECT * FROM tblcontactusinfo LIMIT 1')->fetch();

$adminTitle = 'Update Contact Info';
$adminNav = 'contactinfo';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-xl space-y-5">
  <h1 class="text-2xl font-extrabold text-slate-950">Update Contact Information</h1>
  <div class="card p-6">
    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Office Address</label>
        <textarea name="address" rows="3" required class="form-input"><?php echo Security::e($info->Address ?? ''); ?></textarea>
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Support Email</label>
        <input type="email" name="email" required value="<?php echo Security::e($info->EmailId ?? ''); ?>" class="form-input">
      </div>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Helpline Phone Number</label>
        <input type="text" name="contactno" required value="<?php echo Security::e($info->ContactNo ?? ''); ?>" class="form-input">
      </div>
      <button type="submit" name="submit" class="btn-primary">Save Contact Info</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
