<?php

declare(strict_types=1);

use App\Core\Security;

$accessoryFields = [
    'airconditioner' => ['AirConditioner', 'Air Conditioner'],
    'powerdoorlocks' => ['PowerDoorLocks', 'Power Door Locks'],
    'antilockbrakingsys' => ['AntiLockBrakingSystem', 'AntiLock Braking System'],
    'brakeassist' => ['BrakeAssist', 'Brake Assist'],
    'powersteering' => ['PowerSteering', 'Power Steering'],
    'driverairbag' => ['DriverAirbag', 'Driver Airbag'],
    'passengerairbag' => ['PassengerAirbag', 'Passenger Airbag'],
    'powerwindow' => ['PowerWindows', 'Power Windows'],
    'cdplayer' => ['CDPlayer', 'Audio / CD Player'],
    'centrallocking' => ['CentralLocking', 'Central Locking'],
    'crashcensor' => ['CrashSensor', 'Crash Sensor'],
    'leatherseats' => ['LeatherSeats', 'Leather Seats'],
];
?>
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <?php foreach ($accessoryFields as $inputName => [$dbCol, $label]):
    $checked = isset($vehicleObj) && ((int) ($vehicleObj->$dbCol ?? 0) === 1);
  ?>
    <label for="acc_<?php echo Security::e($inputName); ?>"
           class="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-xs font-semibold text-slate-800 hover:border-blue-600 hover:bg-blue-50 transition">
      <input type="checkbox"
             id="acc_<?php echo Security::e($inputName); ?>"
             name="<?php echo Security::e($inputName); ?>"
             value="1"
             <?php echo $checked ? 'checked' : ''; ?>
             class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
      <span><?php echo Security::e($label); ?></span>
    </label>
  <?php endforeach; ?>
</div>
