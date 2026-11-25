<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\UploadService;

if (empty($_SESSION['alogin'])) {
    Security::redirect('index.php');
}

$imageIndex = isset($imageIndex) ? max(1, min(5, (int) $imageIndex)) : 1;
$columnName = 'Vimage' . $imageIndex;
$imgId = (int) ($_GET['imgid'] ?? 0);

if ($imgId <= 0) {
    Security::redirect('manage-vehicles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    Csrf::requireValid();
    try {
        $storedName = UploadService::storeVehicleImage($_FILES['vehicle_img'] ?? [], true);
        $upd = $dbh->prepare("UPDATE tblvehicles SET `{$columnName}` = :img WHERE id = :id");
        $upd->execute([':img' => $storedName, ':id' => $imgId]);
        Session::setFlash('success', "Vehicle Image {$imageIndex} updated successfully.");
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
    }
    Security::redirect("changeimage{$imageIndex}.php?imgid={$imgId}");
}

$stmt = $dbh->prepare("SELECT VehiclesTitle, `{$columnName}` AS current_img FROM tblvehicles WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $imgId]);
$row = $stmt->fetch();

$adminTitle = "Update Vehicle Image {$imageIndex}";
$adminNav = 'vehicles';
include __DIR__ . '/layout_head.php';
?>

<div class="max-w-xl space-y-5">
  <div>
    <a href="edit-vehicle.php?id=<?php echo $imgId; ?>" class="text-xs font-semibold text-blue-700 hover:underline">&larr; Back to Edit Vehicle</a>
    <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Update Image <?php echo $imageIndex; ?></h1>
    <?php if ($row): ?>
      <p class="text-xs text-slate-500"><?php echo Security::e($row->VehiclesTitle); ?></p>
    <?php endif; ?>
  </div>

  <div class="card p-6 space-y-5">
    <?php if ($row && !empty($row->current_img)): ?>
      <div>
        <div class="mb-2 text-xs font-bold uppercase text-slate-600">Current Image</div>
        <img src="img/vehicleimages/<?php echo Security::e($row->current_img); ?>" alt="Current" class="h-48 w-full rounded-xl object-cover border border-slate-200">
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Upload New Image (JPG / PNG / WebP, max 5 MB)</label>
        <input type="file" name="vehicle_img" accept=".jpg,.jpeg,.png,.webp" required class="form-input !p-2">
      </div>
      <button type="submit" name="update" class="btn-primary">Upload &amp; Replace Image</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/layout_foot.php'; ?>
