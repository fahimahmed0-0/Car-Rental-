<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Security::redirect('manage-brands.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $brand = trim((string) ($_POST['brand'] ?? ''));
    if ($brand !== '') {
        $upd = $dbh->prepare('UPDATE tblbrands SET BrandName = :brand WHERE id = :id');
        $upd->execute([':brand' => $brand, ':id' => $id]);
        Session::setFlash('success', 'Brand updated successfully.');
    }
    Security::redirect('manage-brands.php');
}

$stmt = $dbh->prepare('SELECT * FROM tblbrands WHERE id = :id LIMIT 1');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();

$adminTitle = 'Edit Brand';
$adminNav = 'brands';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-lg space-y-5">
  <h1 class="text-2xl font-extrabold text-slate-950">Edit Brand</h1>
  <div class="card p-6">
    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Brand Name</label>
        <input type="text" name="brand" required value="<?php echo Security::e($row->BrandName ?? ''); ?>" class="form-input">
      </div>
      <button type="submit" name="submit" class="btn-primary">Save Brand</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
