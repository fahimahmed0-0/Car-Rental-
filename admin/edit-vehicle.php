<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    Security::redirect('manage-vehicles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $sql = 'UPDATE tblvehicles SET
                VehiclesTitle = :title,
                VehiclesBrand = :brand,
                VehiclesOverview = :overview,
                PricePerDay = :price,
                FuelType = :fuel,
                ModelYear = :year,
                SeatingCapacity = :seats,
                Transmission = :trans,
                AirConditioner = :ac,
                PowerDoorLocks = :pdl,
                AntiLockBrakingSystem = :abs,
                BrakeAssist = :ba,
                PowerSteering = :ps,
                DriverAirbag = :da,
                PassengerAirbag = :pa,
                PowerWindows = :pw,
                CDPlayer = :cd,
                CentralLocking = :cl,
                CrashSensor = :cs,
                LeatherSeats = :ls
            WHERE id = :id';

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
        ':id' => $id,
    ]);

    Session::setFlash('success', 'Vehicle details updated successfully.');
    Security::redirect('edit-vehicle.php?id=' . $id);
}

$stmt = $dbh->prepare(
    'SELECT v.*, b.BrandName, b.id AS bid
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     WHERE v.id = :id LIMIT 1'
);
$stmt->execute([':id' => $id]);
$vehicleObj = $stmt->fetch();

if (!$vehicleObj) {
    Session::setFlash('error', 'Vehicle not found.');
    Security::redirect('manage-vehicles.php');
}

$brands = $dbh->query('SELECT id, BrandName FROM tblbrands ORDER BY BrandName ASC')->fetchAll();

$adminTitle = 'Edit Vehicle';
$adminNav = 'vehicles';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-5xl space-y-6">
  <div class="flex items-center justify-between">
    <div>
      <a href="manage-vehicles.php" class="text-xs font-semibold text-blue-700 hover:underline">&larr; Back to Vehicles</a>
      <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Edit Vehicle: <?php echo Security::e($vehicleObj->VehiclesTitle); ?></h1>
    </div>
  </div>

  <form method="post" class="space-y-6">
    <?php echo Csrf::field(); ?>

    <div class="card p-6 space-y-5">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Basic Information</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Vehicle Title *</label>
          <input type="text" name="vehicletitle" required value="<?php echo Security::e($vehicleObj->VehiclesTitle); ?>" class="form-input">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Brand *</label>
          <select name="brandname" required class="form-input">
            <?php foreach ($brands as $b): ?>
              <option value="<?php echo (int) $b->id; ?>" <?php echo ((int) $b->id === (int) $vehicleObj->bid) ? 'selected' : ''; ?>>
                <?php echo Security::e($b->BrandName); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="sm:col-span-2">
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Vehicle Overview *</label>
          <textarea name="vehicalorcview" rows="3" required class="form-input"><?php echo Security::e($vehicleObj->VehiclesOverview); ?></textarea>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Price Per Day (₹ INR) *</label>
          <input type="number" name="priceperday" min="100" required value="<?php echo (int) $vehicleObj->PricePerDay; ?>" class="form-input">
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Fuel Type *</label>
          <select name="fueltype" required class="form-input">
            <?php foreach (['Petrol', 'Diesel', 'CNG', 'Electric'] as $ft): ?>
              <option value="<?php echo $ft; ?>" <?php echo ($vehicleObj->FuelType === $ft) ? 'selected' : ''; ?>><?php echo $ft; ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Model Year *</label>
          <input type="number" name="modelyear" required value="<?php echo (int) $vehicleObj->ModelYear; ?>" class="form-input">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Seating Capacity *</label>
            <input type="number" name="seatingcapacity" required value="<?php echo (int) $vehicleObj->SeatingCapacity; ?>" class="form-input">
          </div>
          <div>
            <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Transmission *</label>
            <select name="transmission" required class="form-input">
              <?php foreach (['Manual', 'Automatic'] as $tr): ?>
                <option value="<?php echo $tr; ?>" <?php echo (($vehicleObj->Transmission ?? 'Manual') === $tr) ? 'selected' : ''; ?>><?php echo $tr; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Vehicle Images</h2>
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        <?php for ($i = 1; $i <= 5; $i++):
          $col = 'Vimage' . $i;
          $imgVal = (string) ($vehicleObj->$col ?? '');
        ?>
          <div class="rounded-lg border border-slate-200 p-2.5 text-center space-y-2">
            <div class="text-xs font-bold text-slate-700">Image <?php echo $i; ?></div>
            <?php if ($imgVal !== ''): ?>
              <img src="<?php echo Security::e(Security::vehicleImageUrl($imgVal, 'img/vehicleimages/')); ?>" alt="Image <?php echo $i; ?>" class="h-24 w-full rounded object-cover">
            <?php else: ?>
              <div class="h-24 flex items-center justify-center rounded bg-slate-100 text-[11px] text-slate-400">No Image</div>
            <?php endif; ?>
            <a href="changeimage<?php echo $i; ?>.php?imgid=<?php echo (int) $vehicleObj->id; ?>" class="block text-xs font-bold text-blue-700 hover:underline">
              Change Image <?php echo $i; ?>
            </a>
          </div>
        <?php endfor; ?>
      </div>
    </div>

    <div class="card p-6 space-y-4">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-blue-700 border-b border-slate-100 pb-2">Accessories &amp; Safety</h2>
      <?php include __DIR__ . '/includes/vehicle_accessories_partial.php'; ?>
    </div>

    <button type="submit" name="submit" class="btn-primary">Save Changes</button>
  </form>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
