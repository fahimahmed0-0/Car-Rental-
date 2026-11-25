<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_brand_id'])) {
    Csrf::requireValid();
    $id = (int) $_POST['delete_brand_id'];
    $stmt = $dbh->prepare('DELETE FROM tblbrands WHERE id = :id');
    $stmt->execute([':id' => $id]);
    Session::setFlash('success', 'Brand deleted successfully.');
    Security::redirect('manage-brands.php');
}

$brands = $dbh->query('SELECT * FROM tblbrands ORDER BY id DESC')->fetchAll();

$adminTitle = 'Manage Brands';
$adminNav = 'brands';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <div class="flex items-center justify-between">
    <h1 class="text-2xl font-extrabold text-slate-950">Manage Brands</h1>
    <a href="create-brand.php" class="btn-primary !py-2 !text-xs">+ Create Brand</a>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-left text-xs">
      <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Brand Name</th>
          <th class="px-4 py-3">Created</th>
          <th class="px-4 py-3">Updated</th>
          <th class="px-4 py-3">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($brands as $idx => $b): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
            <td class="px-4 py-3 font-bold text-slate-900"><?php echo Security::e($b->BrandName); ?></td>
            <td class="px-4 py-3 text-slate-600"><?php echo Security::e($b->CreationDate); ?></td>
            <td class="px-4 py-3 text-slate-600"><?php echo Security::e($b->UpdationDate ?? '—'); ?></td>
            <td class="px-4 py-3">
              <div class="flex gap-2">
                <a href="edit-brand.php?id=<?php echo (int) $b->id; ?>" class="btn-outline !py-1 !px-2.5 !text-[11px]">Edit</a>
                <form method="post" class="inline" onsubmit="return confirm('Delete this brand?');">
                  <?php echo Csrf::field(); ?>
                  <input type="hidden" name="delete_brand_id" value="<?php echo (int) $b->id; ?>">
                  <button type="submit" class="btn-red !py-1 !px-2.5 !text-[11px]">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
