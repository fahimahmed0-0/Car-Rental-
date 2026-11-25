<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $brand = trim((string) ($_POST['brand'] ?? ''));
    if ($brand === '') {
        Session::setFlash('error', 'Brand name cannot be empty.');
    } else {
        $ins = $dbh->prepare('INSERT INTO tblbrands (BrandName) VALUES (:brand)');
        $ins->execute([':brand' => $brand]);
        Session::setFlash('success', 'Brand created successfully.');
        Security::redirect('manage-brands.php');
    }
    Security::redirect('create-brand.php');
}

$adminTitle = 'Create Brand';
$adminNav = 'create-brand';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-lg space-y-5">
  <h1 class="text-2xl font-extrabold text-slate-950">Create Brand</h1>
  <div class="card p-6">
    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Brand Name</label>
        <input type="text" name="brand" required class="form-input" placeholder="e.g. Toyota">
      </div>
      <button type="submit" name="submit" class="btn-primary">Create Brand</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
