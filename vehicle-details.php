<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;
use App\Services\PricingService;
use App\Services\TokenLedgerService;

$vhid = isset($_GET['vhid']) ? (int) $_GET['vhid'] : 0;
if ($vhid <= 0) {
    Security::redirect('car-listing.php');
}

$bookingSvc = new BookingService($dbh);
$bookingSvc->expireStalePendingLocks();

// Process booking submission prior to rendering HTML
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_submit'])) {
    Csrf::requireValid();
    if (empty($_SESSION['login'])) {
        Session::setFlash('error', 'Please sign in to book a vehicle.');
        Security::redirect('vehicle-details.php?vhid=' . $vhid);
    }

    try {
        $mode = ($_POST['booking_mode'] ?? 'pay_now') === 'pay_at_pickup' ? 'pay_at_pickup' : 'pay_now';
        $result = $bookingSvc->createBooking(
            (string) $_SESSION['login'],
            $vhid,
            (string) ($_POST['fromdate'] ?? ''),
            (string) ($_POST['todate'] ?? ''),
            (string) ($_POST['message'] ?? ''),
            (int) ($_POST['redeem_tokens'] ?? 0),
            $mode,
            (string) ($_POST['pickup_location'] ?? 'DriveX Hub, Guwahati, Assam'),
            (string) ($_POST['dropoff_location'] ?? 'DriveX Hub, Guwahati, Assam')
        );

        if ($mode === 'pay_now') {
            Security::redirect('payment.php?booking_id=' . $result['booking_number']);
        }
        Session::setFlash('success', 'Reservation #' . $result['booking_number'] . ' submitted! Awaiting admin confirmation.');
        Security::redirect('my-booking.php');
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
        Security::redirect('vehicle-details.php?vhid=' . $vhid);
    }
}

$vStmt = $dbh->prepare(
    'SELECT v.*, b.BrandName, b.id AS bid
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     WHERE v.id = :vhid LIMIT 1'
);
$vStmt->execute([':vhid' => $vhid]);
$vehicle = $vStmt->fetch();

if (!$vehicle) {
    Session::setFlash('error', 'Vehicle not found.');
    Security::redirect('car-listing.php');
}

$images = array_values(array_filter([
    $vehicle->Vimage1 ?? '',
    $vehicle->Vimage2 ?? '',
    $vehicle->Vimage3 ?? '',
    $vehicle->Vimage4 ?? '',
    $vehicle->Vimage5 ?? '',
], static fn (string $img): bool => trim($img) !== ''));

$accessories = [
    'AirConditioner' => 'Air Conditioner',
    'AntiLockBrakingSystem' => 'ABS (Anti-Lock Braking)',
    'PowerSteering' => 'Power Steering',
    'PowerWindows' => 'Power Windows',
    'DriverAirbag' => 'Driver Airbag',
    'PassengerAirbag' => 'Passenger Airbag',
    'PowerDoorLocks' => 'Power Door Locks',
    'CentralLocking' => 'Central Locking',
    'BrakeAssist' => 'Brake Assist',
    'CrashSensor' => 'Crash Sensor',
    'LeatherSeats' => 'Leather Seats',
    'CDPlayer' => 'Audio / Infotainment',
];

$bookedRanges = $bookingSvc->getBookedDateRanges($vhid);

$tokenBalance = 0;
if (!empty($_SESSION['login'])) {
    $tokenBalance = (new TokenLedgerService($dbh))->getBalance((string) $_SESSION['login'])['balance'];
}

// Similar available vehicles (excluding current car!)
$simStmt = $dbh->prepare(
    'SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Vimage1, b.BrandName
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     WHERE v.id != :vhid
     ORDER BY (v.VehiclesBrand = :bid) DESC, v.id DESC
     LIMIT 4'
);
$simStmt->execute([':vhid' => $vhid, ':bid' => (int) $vehicle->bid]);
$similarCars = $simStmt->fetchAll();

$prefillFrom = trim((string) ($_GET['from_date'] ?? date('Y-m-d')));
$prefillTo = trim((string) ($_GET['to_date'] ?? date('Y-m-d', strtotime('+1 day'))));
$today = date('Y-m-d');

$pageTitle = 'DriveX Assam | ' . $vehicle->BrandName . ' ' . $vehicle->VehiclesTitle;
$activeNav = 'cars';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-6 border-b border-slate-800">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4">
    <div>
      <div class="text-xs text-slate-400">
        <a href="index.php" class="hover:text-sky-300">Home</a> /
        <a href="car-listing.php" class="hover:text-sky-300">Fleet</a> /
        <span class="text-sky-300"><?php echo Security::e($vehicle->BrandName); ?></span>
      </div>
      <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">
        <?php echo Security::e($vehicle->BrandName . ' ' . $vehicle->VehiclesTitle); ?>
      </h1>
    </div>
    <div class="rounded-xl border border-slate-800 bg-slate-900 px-5 py-3 text-right">
      <div class="text-2xl font-extrabold text-red-500">₹<?php echo number_format((float) $vehicle->PricePerDay); ?></div>
      <div class="text-xs font-medium text-slate-400">Per Calendar Day</div>
    </div>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <!-- Left: Gallery, Specs, Accessories -->
      <div class="lg:col-span-8 space-y-6" x-data="{ activeImg: <?php echo Security::e(json_encode($images[0] ?? '')); ?> }">
        <!-- Image Gallery -->
        <div class="card overflow-hidden p-3">
          <div class="relative aspect-[16/9] overflow-hidden rounded-xl bg-slate-100">
            <img :src="(activeImg && (activeImg.startsWith('http://') || activeImg.startsWith('https://'))) ? activeImg : ('admin/img/vehicleimages/' + (activeImg || 'car_755x430.png'))" alt="<?php echo Security::e($vehicle->VehiclesTitle); ?>" class="h-full w-full object-cover">
          </div>
          <?php if (count($images) > 1): ?>
            <div class="mt-3 grid grid-cols-5 gap-2">
              <?php foreach ($images as $img): ?>
                <button type="button" @click="activeImg = <?php echo Security::e(json_encode($img)); ?>"
                        :class="activeImg === <?php echo Security::e(json_encode($img)); ?> ? 'ring-2 ring-blue-700 border-blue-700' : 'border-slate-200 opacity-80 hover:opacity-100'"
                        class="overflow-hidden rounded-lg border bg-slate-100 aspect-[16/10] transition">
                  <img src="<?php echo Security::e(Security::vehicleImageUrl($img)); ?>" alt="Thumbnail" class="h-full w-full object-cover">
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Key Specs -->
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
          <div class="card p-4 text-center">
            <div class="text-xs font-semibold uppercase text-slate-500">Model Year</div>
            <div class="mt-1 text-lg font-extrabold text-slate-950"><?php echo (int) $vehicle->ModelYear; ?></div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-xs font-semibold uppercase text-slate-500">Fuel Type</div>
            <div class="mt-1 text-lg font-extrabold text-blue-700"><?php echo Security::e($vehicle->FuelType); ?></div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-xs font-semibold uppercase text-slate-500">Seats</div>
            <div class="mt-1 text-lg font-extrabold text-slate-950"><?php echo (int) $vehicle->SeatingCapacity; ?> Persons</div>
          </div>
          <div class="card p-4 text-center">
            <div class="text-xs font-semibold uppercase text-slate-500">Transmission</div>
            <div class="mt-1 text-lg font-extrabold text-slate-950"><?php echo Security::e($vehicle->Transmission ?? 'Manual'); ?></div>
          </div>
        </div>

        <!-- Overview & Features -->
        <div class="card p-6 space-y-6">
          <div>
            <h2 class="text-lg font-bold text-slate-950 border-b border-slate-100 pb-2">Vehicle Overview</h2>
            <p class="mt-3 text-sm text-slate-700 leading-relaxed"><?php echo nl2br(Security::e($vehicle->VehiclesOverview)); ?></p>
          </div>

          <div>
            <h2 class="text-lg font-bold text-slate-950 border-b border-slate-100 pb-2">Safety &amp; Comfort Features</h2>
            <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 md:grid-cols-3">
              <?php foreach ($accessories as $col => $label):
                $has = ((int) ($vehicle->$col ?? 0)) === 1;
              ?>
                <div class="flex items-center justify-between rounded-lg border px-3 py-2 text-xs font-semibold <?php echo $has ? 'border-sky-200 bg-blue-50/70 text-slate-900' : 'border-slate-200 bg-slate-50 text-slate-400'; ?>">
                  <span><?php echo Security::e($label); ?></span>
                  <span class="<?php echo $has ? 'text-blue-700' : 'text-slate-400'; ?>"><?php echo $has ? 'Yes' : 'No'; ?></span>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <!-- Right: Live Booking & Quote Card -->
      <aside class="lg:col-span-4">
        <div class="card p-6 sticky top-24 space-y-5"
             x-data="bookingWidget(<?php echo (int) $vhid; ?>, <?php echo (float) $vehicle->PricePerDay; ?>, <?php echo (int) $tokenBalance; ?>, <?php echo Security::e(json_encode($prefillFrom)); ?>, <?php echo Security::e(json_encode($prefillTo)); ?>)">
          <div class="border-b border-slate-100 pb-3">
            <h2 class="text-lg font-extrabold text-slate-950">Book This Vehicle</h2>
            <p class="text-xs text-slate-500">Instant availability check &amp; transparent ₹ INR fare breakdown</p>
          </div>

          <?php if (!empty($bookedRanges)): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-900">
              <div class="font-bold mb-1">Currently Reserved Dates:</div>
              <ul class="space-y-0.5">
                <?php foreach ($bookedRanges as $rng): ?>
                  <li>&bull; <?php echo Security::e($rng['from']); ?> to <?php echo Security::e($rng['to']); ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <form method="post" class="space-y-4">
            <?php echo Csrf::field(); ?>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Pickup Date</label>
                <input type="date" name="fromdate" x-model="fromDate" @change="if (fromDate && (!toDate || toDate < fromDate)) { toDate = fromDate; } refreshQuote()" min="<?php echo $today; ?>" required class="form-input">
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold text-slate-600">Drop-off Date</label>
                <input type="date" name="todate" x-model="toDate" @change="refreshQuote()" :min="fromDate || '<?php echo $today; ?>'" required class="form-input">
              </div>
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Pickup &amp; Drop-off Hub</label>
              <select name="pickup_location" class="form-input">
                <option value="DriveX Hub, GS Road, Guwahati">DriveX Hub, GS Road, Guwahati</option>
                <option value="Lokpriya Gopinath Bordoloi Airport (GAU)">Guwahati Airport (GAU)</option>
                <option value="Guwahati Railway Station, Paltan Bazar">Guwahati Railway Station</option>
              </select>
              <input type="hidden" name="dropoff_location" value="DriveX Hub, GS Road, Guwahati">
            </div>

            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Trip Notes / Requirements</label>
              <textarea name="message" rows="2" class="form-input" placeholder="Destination, driver preference, pickup time..."></textarea>
            </div>

            <?php if (!empty($_SESSION['login'])): ?>
              <!-- Loyalty Token Redemption -->
              <div class="rounded-xl border border-sky-200 bg-blue-50/70 p-3.5 space-y-2">
                <div class="flex items-center justify-between text-xs">
                  <span class="font-bold text-blue-900">Loyalty Tokens</span>
                  <span class="font-extrabold text-blue-700"><?php echo (int) $tokenBalance; ?> available</span>
                </div>
                <?php if ($tokenBalance >= PricingService::MIN_REDEEM_TOKENS): ?>
                  <select name="redeem_tokens" x-model.number="redeemTokens" @change="refreshQuote()" class="form-input bg-white !py-2 !text-xs">
                    <option value="0">Do not use tokens (Save for later)</option>
                    <?php
                    $maxRedeem = (int) floor($tokenBalance / 5) * 5;
                    for ($t = 15; $t <= $maxRedeem; $t += 5):
                        $disc = ($t / 5) * 100;
                    ?>
                      <option value="<?php echo $t; ?>">Redeem <?php echo $t; ?> tokens (Save ₹<?php echo $disc; ?>)</option>
                    <?php endfor; ?>
                  </select>
                  <button type="button" @click="getAiTip()" class="text-[11px] font-bold text-blue-700 hover:underline">
                    Optimize tokens &amp; get route advice &rarr;
                  </button>
                  <div x-show="aiTip" class="rounded bg-white p-2 text-[11px] text-slate-700 border border-sky-200" x-text="aiTip"></div>
                <?php else: ?>
                  <p class="text-[11px] text-slate-600">Minimum 15 tokens needed to redeem (5 tokens = ₹100 off). You earn 5 tokens after every completed trip.</p>
                <?php endif; ?>
              </div>

              <!-- Live Fare Breakdown -->
              <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2 text-xs">
                <div class="flex justify-between">
                  <span class="text-slate-600">Rental Duration</span>
                  <span class="font-bold text-slate-900" x-text="quote.days + ' day(s)'">1 day</span>
                </div>
                <div class="flex justify-between">
                  <span class="text-slate-600">Base Fare</span>
                  <span class="font-semibold text-slate-900" x-text="'₹' + Number(quote.base_amount).toLocaleString('en-IN')">₹0</span>
                </div>
                <div class="flex justify-between" x-show="quote.discount_amount > 0">
                  <span class="text-blue-700 font-semibold">Token Discount</span>
                  <span class="text-blue-700 font-bold" x-text="'- ₹' + Number(quote.discount_amount).toLocaleString('en-IN')">- ₹0</span>
                </div>
                <div class="border-t border-slate-200 pt-2 flex justify-between items-center">
                  <span class="text-sm font-bold text-slate-950">Total Payable</span>
                  <span class="text-xl font-extrabold text-red-600" x-text="'₹' + Number(quote.final_amount).toLocaleString('en-IN')">₹0</span>
                </div>
                <div x-show="availabilityMsg" class="pt-1 text-[11px] font-bold"
                     :class="isAvailable ? 'text-blue-700' : 'text-red-600'" x-text="availabilityMsg"></div>
              </div>

              <div class="space-y-2">
                <button type="submit" name="book_submit" value="1" onclick="document.getElementById('booking_mode_input').value='pay_now'"
                        :disabled="isAvailable === false" class="btn-red w-full !py-3">
                  Book &amp; Pay Online
                </button>
                <button type="submit" name="book_submit" value="1" onclick="document.getElementById('booking_mode_input').value='pay_at_pickup'"
                        :disabled="isAvailable === false" class="btn-outline w-full">
                  Reserve Now (Pay at Pickup)
                </button>
                <input type="hidden" name="booking_mode" id="booking_mode_input" value="pay_now">
              </div>
            <?php else: ?>
              <button type="button" onclick="window.openAuthModal('login')" class="btn-primary w-full !py-3">
                Sign In to Book This Vehicle
              </button>
            <?php endif; ?>
          </form>
        </div>
      </aside>
    </div>

    <!-- Similar Cars -->
    <?php if (!empty($similarCars)): ?>
      <div class="mt-14 border-t border-slate-200 pt-10">
        <h2 class="text-xl font-extrabold text-slate-950 mb-6">Similar Available Vehicles</h2>
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
          <?php foreach ($similarCars as $sim): ?>
            <a href="vehicle-details.php?vhid=<?php echo (int) $sim->id; ?>" class="card overflow-hidden hover:border-blue-600 transition">
              <img src="<?php echo Security::e(Security::vehicleImageUrl($sim->Vimage1)); ?>" alt="<?php echo Security::e($sim->VehiclesTitle); ?>" class="h-40 w-full object-cover">
              <div class="p-4">
                <div class="text-xs font-bold text-blue-700"><?php echo Security::e($sim->BrandName); ?></div>
                <div class="font-bold text-slate-950 truncate"><?php echo Security::e($sim->VehiclesTitle); ?></div>
                <div class="mt-2 flex items-center justify-between text-xs">
                  <span class="text-slate-600"><?php echo (int) $sim->SeatingCapacity; ?> Seats &bull; <?php echo Security::e($sim->FuelType); ?></span>
                  <span class="font-extrabold text-red-600">₹<?php echo number_format((float) $sim->PricePerDay); ?>/d</span>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</section>

<script>
function bookingWidget(vehicleId, dailyRate, tokenBalance, preFrom, preTo) {
  return {
    fromDate: preFrom,
    toDate: preTo,
    redeemTokens: 0,
    isAvailable: true,
    availabilityMsg: '',
    aiTip: '',
    quote: { days: 1, base_amount: dailyRate, discount_amount: 0, final_amount: dailyRate },
    init() {
      this.refreshQuote();
    },
    async refreshQuote() {
      if (!this.fromDate || !this.toDate) return;
      try {
        var params = new URLSearchParams({
          vehicle_id: vehicleId,
          from_date: this.fromDate,
          to_date: this.toDate,
          redeem_tokens: this.redeemTokens || 0
        });
        var res = await fetch('api/v1/availability.php?' + params.toString());
        var data = await res.json();
        if (data.error) {
          this.isAvailable = false;
          this.availabilityMsg = data.error;
          return;
        }
        if (data.quote) {
          this.quote = data.quote;
        }
        this.isAvailable = Boolean(data.available);
        this.availabilityMsg = this.isAvailable
          ? 'Vehicle is available for selected dates.'
          : 'Selected dates overlap an existing booking.';
      } catch (e) {}
    },
    async getAiTip() {
      var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      try {
        var res = await fetch('api/v1/ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({
            action: 'optimize_booking',
            vehicle_id: vehicleId,
            days: this.quote.days,
            token_balance: tokenBalance
          })
        });
        var data = await res.json();
        if (data.success) {
          this.aiTip = data.advice || '';
        }
      } catch (e) {}
    }
  };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
