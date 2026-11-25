<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vehicle_id'])) {
    Csrf::requireValid();
    $delid = (int) $_POST['delete_vehicle_id'];
    $stmt = $dbh->prepare('DELETE FROM tblvehicles WHERE id = :id');
    $stmt->execute([':id' => $delid]);
    Session::setFlash('success', 'Vehicle record deleted successfully.');
    Security::redirect('manage-vehicles.php');
}

$vehicles = $dbh->query(
    'SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Transmission, v.Vimage1, b.BrandName
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     ORDER BY v.id DESC'
)->fetchAll();

$adminTitle = 'Manage Vehicles';
$adminNav = 'vehicles';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-950">Manage Vehicles</h1>
      <p class="text-xs text-slate-500">Edit fleet specifications, pricing (₹ INR), and vehicle photos.</p>
    </div>
    <a href="post-vehicle.php" class="btn-primary !py-2 !text-xs">+ Post a Vehicle</a>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
          <tr>
            <th class="px-4 py-3">#</th>
            <th class="px-4 py-3">Vehicle</th>
            <th class="px-4 py-3">Brand</th>
            <th class="px-4 py-3">Rate / Day</th>
            <th class="px-4 py-3">Specs</th>
            <th class="px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (empty($vehicles)): ?>
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No vehicles posted yet.</td></tr>
          <?php else: ?>
            <?php foreach ($vehicles as $idx => $v): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <img src="<?php echo Security::e(Security::vehicleImageUrl($v->Vimage1, 'img/vehicleimages/')); ?>" alt="" class="h-10 w-14 rounded object-cover border border-slate-200">
                    <span class="font-bold text-slate-900"><?php echo Security::e($v->VehiclesTitle); ?></span>
                  </div>
                </td>
                <td class="px-4 py-3 font-semibold text-blue-700"><?php echo Security::e($v->BrandName); ?></td>
                <td class="px-4 py-3 font-extrabold text-red-600">₹<?php echo number_format((float) $v->PricePerDay); ?></td>
                <td class="px-4 py-3 text-slate-600"><?php echo Security::e($v->FuelType . ' • ' . $v->SeatingCapacity . ' seats • ' . ($v->Transmission ?? 'Manual') . ' • ' . $v->ModelYear); ?></td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-2">
                    <a href="edit-vehicle.php?id=<?php echo (int) $v->id; ?>" class="btn-outline !py-1 !px-2.5 !text-[11px]">Edit</a>
                    <form method="post" class="inline" onsubmit="return confirm('Delete this vehicle permanently?');">
                      <?php echo Csrf::field(); ?>
                      <input type="hidden" name="delete_vehicle_id" value="<?php echo (int) $v->id; ?>">
                      <button type="submit" class="btn-red !py-1 !px-2.5 !text-[11px]">Delete</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
