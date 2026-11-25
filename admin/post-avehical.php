<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\UploadService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    try {
        $vimage1 = UploadService::storeVehicleImage($_FILES['img1'] ?? [], true);
        $vimage2 = UploadService::storeVehicleImage($_FILES['img2'] ?? [], true);
        $vimage3 = UploadService::storeVehicleImage($_FILES['img3'] ?? [], true);
        $vimage4 = UploadService::storeVehicleImage($_FILES['img4'] ?? [], true);
        $vimage5 = UploadService::storeVehicleImage($_FILES['img5'] ?? [], false) ?? '';

        $sql = 'INSERT INTO tblvehicles
            (VehiclesTitle, VehiclesBrand, VehiclesOverview, PricePerDay, FuelType, ModelYear, SeatingCapacity, Transmission,
             Vimage1, Vimage2, Vimage3, Vimage4, Vimage5,
             AirConditioner, PowerDoorLocks, AntiLockBrakingSystem, BrakeAssist, PowerSteering, DriverAirbag,
             PassengerAirbag, PowerWindows, CDPlayer, CentralLocking, CrashSensor, LeatherSeats)
            VALUES (:title, :brand, :overview, :price, :fuel, :year, :seats, :trans,
                    :img1, :img2, :img3, :img4, :img5,
                    :ac, :pdl, :abs, :ba, :ps, :da, :pa, :pw, :cd, :cl, :cs, :ls)';

        $stmt = $dbh->prepare($sql);
        $stmt->execute([
            ':title' => trim((string) ($_POST['vehicletitle'] ?? '')),
            ':brand' => (int) ($_POST['brandname'] ?? 0),
            ':overview' => trim((string) ($_POST['vehicalorcview'] ?? '')),
            ':price' => (int) ($_POST['priceperday'] ?? 0),
            ':fuel' => trim((string) ($_POST['fueltype'] ?? '')),
            ':year' => (int) ($_POST['modelyear'] ?? date('Y')),
            ':seats' => (int) ($_POST['seatingcapacity'] ?? 5),
            ':trans' => trim((string) ($_POST['transmission'] ?? 'Manual')),
            ':img1' => $vimage1,
            ':img2' => $vimage2,
            ':img3' => $vimage3,
            ':img4' => $vimage4,
            ':img5' => $vimage5,
            ':ac' => isset($_POST['airconditioner']) ? 1 : 0,
            ':pdl' => isset($_POST['powerdoorlocks']) ? 1 : 0,
            ':abs' => isset($_POST['antilockbrakingsys']) ? 1 : 0,
            ':ba' => isset($_POST['brakeassist']) ? 1 : 0,
            ':ps' => isset($_POST['powersteering']) ? 1 : 0,
            ':da' => isset($_POST['driverairbag']) ? 1 : 0,
            ':pa' => isset($_POST['passengerairbag']) ? 1 : 0,
            ':pw' => isset($_POST['powerwindow']) ? 1 : 0,
            ':cd' => isset($_POST['cdplayer']) ? 1 : 0,
            ':cl' => isset($_POST['centrallocking']) ? 1 : 0,
            ':cs' => isset($_POST['crashcensor']) ? 1 : 0,
            ':ls' => isset($_POST['leatherseats']) ? 1 : 0,
        ]);

        Session::setFlash('success', 'Vehicle posted successfully.');
        Security::redirect('manage-vehicles.php');
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
        Security::redirect('post-avehical.php');
    }
}

$brands = $dbh->query('SELECT id, BrandName FROM tblbrands ORDER BY BrandName ASC')->fetchAll();

$adminTitle = 'Post a Vehicle';
$adminNav = 'post-vehicle';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-5xl space-y-6">
  <h1 class="text-2xl font-extrabold text-slate-950">Post a New Vehicle</h1>

  <form method="post" enctype="multipart/form-data" class="space-y-6">
    <?php echo Csrf::field(); ?>

    <div class="card p-6 space-y-5">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Basic Information</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Vehicle Title *</label>
          <input type="text" name="vehicletitle" required class="form-input" placeholder="e.g. Vitara Brezza ZDi+">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Brand *</label>
          <select name="brandname" required class="form-input">
            <option value="">Select Brand</option>
            <?php foreach ($brands as $b): ?>
              <option value="<?php echo (int) $b->id; ?>"><?php echo Security::e($b->BrandName); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Vehicle Overview *</label>
          <textarea name="vehicalorcview" rows="3" required class="form-input"></textarea>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Price Per Day (₹ INR) *</label>
          <input type="number" name="priceperday" min="100" required class="form-input" placeholder="e.g. 2400">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Fuel Type *</label>
          <select name="fueltype" required class="form-input">
            <option value="">Select Fuel Type</option>
            <option value="Petrol">Petrol</option>
            <option value="Diesel">Diesel</option>
            <option value="CNG">CNG</option>
            <option value="Electric">Electric</option>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Model Year *</label>
          <input type="number" name="modelyear" min="2005" max="<?php echo (int) date('Y') + 1; ?>" required class="form-input" value="<?php echo date('Y'); ?>">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Seating Capacity *</label>
            <input type="number" name="seatingcapacity" min="2" max="15" required class="form-input" value="5">
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Transmission *</label>
            <select name="transmission" required class="form-input">
              <option value="Manual">Manual</option>
              <option value="Automatic">Automatic</option>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Vehicle Images (JPG / PNG / WebP, max 5 MB each)</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 text-xs">
        <div><label class="mb-1 block font-bold text-slate-700">Image 1 *</label><input type="file" name="img1" accept=".jpg,.jpeg,.png,.webp" required class="form-input !p-2"></div>
        <div><label class="mb-1 block font-bold text-slate-700">Image 2 *</label><input type="file" name="img2" accept=".jpg,.jpeg,.png,.webp" required class="form-input !p-2"></div>
        <div><label class="mb-1 block font-bold text-slate-700">Image 3 *</label><input type="file" name="img3" accept=".jpg,.jpeg,.png,.webp" required class="form-input !p-2"></div>
        <div><label class="mb-1 block font-bold text-slate-700">Image 4 *</label><input type="file" name="img4" accept=".jpg,.jpeg,.png,.webp" required class="form-input !p-2"></div>
        <div><label class="mb-1 block font-bold text-slate-700">Image 5 (Optional)</label><input type="file" name="img5" accept=".jpg,.jpeg,.png,.webp" class="form-input !p-2"></div>
      </div>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Accessories &amp; Safety</h2>
      <?php $vehicleObj = null; include __DIR__ . '/includes/vehicle_accessories_partial.php'; ?>
    </div>

    <div class="flex gap-3">
      <button type="submit" name="submit" class="btn-primary">Publish Vehicle</button>
      <a href="manage-vehicles.php" class="btn-outline">Cancel</a>
    </div>
  </form>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
