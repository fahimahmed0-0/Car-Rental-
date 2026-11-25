<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$pageTitle = 'DriveX Assam | Premium Self-Drive & Chauffeur Car Rentals';
$activeNav = 'home';

include __DIR__ . '/includes/layout_head.php';

// Fetch featured vehicles
$vStmt = $dbh->query(
    'SELECT v.id, v.VehiclesTitle, v.PricePerDay, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Transmission, v.VehiclesOverview, v.Vimage1, b.BrandName
     FROM tblvehicles v
     JOIN tblbrands b ON b.id = v.VehiclesBrand
     ORDER BY v.id DESC
     LIMIT 9'
);
$featuredCars = $vStmt ? $vStmt->fetchAll() : [];

// Live fleet & customer metrics
$carCount = (int) $dbh->query('SELECT COUNT(*) FROM tblvehicles')->fetchColumn();
$brandCount = (int) $dbh->query('SELECT COUNT(*) FROM tblbrands')->fetchColumn();
$bookingCount = (int) $dbh->query('SELECT COUNT(*) FROM tblbooking')->fetchColumn();
$userCount = (int) $dbh->query('SELECT COUNT(*) FROM tblusers')->fetchColumn();

// Active testimonials
$tStmt = $dbh->query(
    'SELECT t.Testimonial, u.FullName, t.PostingDate
     FROM tbltestimonial t
     JOIN tblusers u ON t.UserEmail = u.EmailId
     WHERE t.status = 1
     ORDER BY t.id DESC
     LIMIT 6'
);
$testimonials = $tStmt ? $tStmt->fetchAll() : [];

// Brands for quick filter
$brands = $dbh->query('SELECT id, BrandName FROM tblbrands ORDER BY BrandName ASC')->fetchAll();
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>

<!-- Hero -->
<section class="relative bg-slate-950 text-white border-b-4 border-blue-700 overflow-hidden">
  <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8 lg:py-20">
    <div class="grid grid-cols-1 gap-10 lg:grid-cols-12 lg:items-center">
      <div class="lg:col-span-7 space-y-6">
        <div class="inline-flex items-center gap-2 rounded-full border border-sky-400/30 bg-blue-950/80 px-3.5 py-1 text-xs font-semibold text-sky-300">
          <span class="h-2 w-2 rounded-full bg-red-500"></span>
          Guwahati &bull; Shillong &bull; Kaziranga &bull; Northeast India
        </div>
        <h1 class="text-3xl font-extrabold tracking-tight text-white sm:text-5xl leading-tight">
          Reliable Cars for Every Journey Across <span class="text-sky-400">Assam</span>
        </h1>
        <p class="max-w-2xl text-base text-slate-300 sm:text-lg leading-relaxed">
          Book inspected hatchbacks, SUVs, and 7-seaters with transparent <strong class="text-white">₹ INR</strong> daily pricing, instant date availability, and loyalty token discounts on every completed trip.
        </p>

        <!-- Search Bar -->
        <div class="rounded-2xl border border-slate-800 bg-white p-4 text-slate-900 shadow-lg">
          <form action="car-listing.php" method="get" class="grid grid-cols-1 gap-3 sm:grid-cols-12">
            <div class="sm:col-span-5">
              <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Search or Describe Your Trip</label>
              <input type="text" name="q" placeholder="e.g. 7-seater diesel SUV under ₹3500" class="form-input">
            </div>
            <div class="sm:col-span-3">
              <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Pickup Date</label>
              <input type="date" name="from_date" min="<?php echo $today; ?>" value="<?php echo $today; ?>" class="form-input">
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-[11px] font-bold uppercase tracking-wider text-slate-500">Drop-off</label>
              <input type="date" name="to_date" min="<?php echo $today; ?>" value="<?php echo $tomorrow; ?>" class="form-input">
            </div>
            <div class="sm:col-span-2 flex items-end">
              <button type="submit" class="btn-red w-full !py-2.5">Find Cars</button>
            </div>
          </form>
          <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 pt-2.5 text-xs text-slate-600">
            <span>Tip: Try natural queries like <em>"5 seater automatic under ₹2500"</em> in the Smart Finder.</span>
            <a href="car-listing.php?ai=1" class="font-bold text-blue-700 hover:underline">Open Smart Car Finder &rarr;</a>
          </div>
        </div>
      </div>

      <!-- Right Side Highlight Card -->
      <div class="lg:col-span-5">
        <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-xl">
          <img src="assets/images/banner-image.jpg" alt="DriveX Assam Fleet" class="h-64 w-full object-cover sm:h-72">
          <div class="grid grid-cols-3 divide-x divide-slate-800 bg-slate-950 p-4 text-center">
            <div>
              <div class="text-xl font-extrabold text-white"><?php echo max($carCount, 6); ?>+</div>
              <div class="text-[11px] font-medium text-sky-300 uppercase">Verified Cars</div>
            </div>
            <div>
              <div class="text-xl font-extrabold text-white">5 Tokens</div>
              <div class="text-[11px] font-medium text-slate-300 uppercase">Per Trip Earned</div>
            </div>
            <div>
              <div class="text-xl font-extrabold text-red-500">24&times;7</div>
              <div class="text-[11px] font-medium text-slate-300 uppercase">Road Support</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Brand Quick Filters -->
<section class="border-b border-sky-100 bg-blue-50/70 py-5">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
    <span class="text-xs font-bold uppercase tracking-wider text-blue-900">Browse by Brand:</span>
    <div class="flex flex-wrap items-center gap-2">
      <a href="car-listing.php" class="rounded-full border border-blue-700 bg-blue-700 px-3.5 py-1 text-xs font-semibold text-white">All Brands</a>
      <?php foreach ($brands as $b): ?>
        <a href="car-listing.php?brand=<?php echo (int) $b->id; ?>"
           class="rounded-full border border-sky-200 bg-white px-3.5 py-1 text-xs font-semibold text-slate-800 hover:border-blue-600 hover:text-blue-700 transition">
          <?php echo Security::e($b->BrandName); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Featured Fleet -->
<section class="py-14 bg-white">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mb-8 flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
      <div>
        <span class="inline-block rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-blue-700">Our Fleet</span>
        <h2 class="mt-2 text-2xl font-extrabold text-slate-950 sm:text-3xl">Featured Vehicles Ready to Drive</h2>
        <p class="mt-1 text-sm text-slate-600">Clean, well-maintained vehicles with transparent daily pricing and zero hidden charges.</p>
      </div>
      <a href="car-listing.php" class="btn-outline shrink-0">View All Vehicles &rarr;</a>
    </div>

    <?php if (empty($featuredCars)): ?>
      <div class="card p-10 text-center">
        <p class="text-slate-600">No vehicles are currently listed. Add vehicles from the <a href="admin/index.php" class="font-bold text-blue-700 underline">Admin Portal</a> or run the seed script.</p>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php foreach ($featuredCars as $car): ?>
          <article class="card group overflow-hidden flex flex-col transition hover:border-blue-500 hover:shadow-md">
            <a href="vehicle-details.php?vhid=<?php echo (int) $car->id; ?>" class="relative block h-52 bg-slate-100 overflow-hidden">
              <img src="<?php echo Security::e(Security::vehicleImageUrl($car->Vimage1)); ?>"
                   alt="<?php echo Security::e($car->BrandName . ' ' . $car->VehiclesTitle); ?>"
                   class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
              <span class="absolute top-3 left-3 rounded-md bg-slate-950/85 px-2.5 py-1 text-xs font-bold text-white">
                <?php echo Security::e($car->BrandName); ?>
              </span>
              <span class="absolute top-3 right-3 rounded-md bg-blue-700 px-2.5 py-1 text-xs font-bold text-white">
                <?php echo Security::e($car->FuelType); ?>
              </span>
            </a>

            <div class="flex flex-1 flex-col p-5">
              <div class="flex items-start justify-between gap-3">
                <h3 class="text-lg font-bold text-slate-950 leading-snug">
                  <a href="vehicle-details.php?vhid=<?php echo (int) $car->id; ?>" class="hover:text-blue-700 transition">
                    <?php echo Security::e($car->VehiclesTitle); ?>
                  </a>
                </h3>
                <div class="text-right shrink-0">
                  <span class="block text-lg font-extrabold text-red-600">₹<?php echo number_format((float) $car->PricePerDay); ?></span>
                  <span class="block text-[11px] font-medium text-slate-500">per day</span>
                </div>
              </div>

              <p class="mt-2 line-clamp-2 text-xs text-slate-600 leading-relaxed">
                <?php echo Security::e(mb_substr((string) $car->VehiclesOverview, 0, 110)); ?>
              </p>

              <div class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-3 text-center text-xs font-semibold text-slate-700">
                <div class="rounded-md bg-slate-50 py-1.5"><?php echo (int) $car->SeatingCapacity; ?> Seats</div>
                <div class="rounded-md bg-slate-50 py-1.5"><?php echo (int) $car->ModelYear; ?></div>
                <div class="rounded-md bg-slate-50 py-1.5"><?php echo Security::e($car->Transmission ?? 'Manual'); ?></div>
              </div>

              <div class="mt-4 pt-1 flex gap-2">
                <a href="vehicle-details.php?vhid=<?php echo (int) $car->id; ?>" class="btn-primary w-full">View &amp; Book</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Program Highlights -->
<section class="border-y border-slate-200 bg-slate-50 py-14">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-4">
      <div class="card p-6 border-t-4 border-t-blue-700">
        <div class="text-xs font-bold uppercase tracking-wider text-blue-700">01 &bull; Fleet</div>
        <h3 class="mt-2 text-base font-bold text-slate-950"><?php echo max($carCount, 1); ?>+ Vehicles &amp; <?php echo max($brandCount, 1); ?> Brands</h3>
        <p class="mt-1 text-xs text-slate-600 leading-relaxed">Compact hatchbacks to 7-seater SUVs serviced before every handover.</p>
      </div>
      <div class="card p-6 border-t-4 border-t-red-600">
        <div class="text-xs font-bold uppercase tracking-wider text-red-600">02 &bull; Loyalty</div>
        <h3 class="mt-2 text-base font-bold text-slate-950">Earn &amp; Redeem Tokens</h3>
        <p class="mt-1 text-xs text-slate-600 leading-relaxed">Earn 5 tokens on every completed trip. Redeem 15+ tokens (5 tokens = ₹100 off).</p>
      </div>
      <div class="card p-6 border-t-4 border-t-slate-900">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-900">03 &bull; Booking</div>
        <h3 class="mt-2 text-base font-bold text-slate-950">Real-Time Date Locking</h3>
        <p class="mt-1 text-xs text-slate-600 leading-relaxed">Instant overlap checks and a 10-minute checkout price lock prevent double bookings.</p>
      </div>
      <div class="card p-6 border-t-4 border-t-sky-500">
        <div class="text-xs font-bold uppercase tracking-wider text-sky-700">04 &bull; Support</div>
        <h3 class="mt-2 text-base font-bold text-slate-950"><?php echo max($bookingCount, 0); ?>+ Trips &bull; <?php echo max($userCount, 0); ?>+ Members</h3>
        <p class="mt-1 text-xs text-slate-600 leading-relaxed">Local Guwahati support team with instant helpline and transparent cancellation rules.</p>
      </div>
    </div>
  </div>
</section>

<!-- Testimonials -->
<?php if (!empty($testimonials)): ?>
<section class="py-14 bg-white">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="mb-8 text-center">
      <span class="inline-block rounded-md bg-blue-50 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-blue-700">Customer Reviews</span>
      <h2 class="mt-2 text-2xl font-extrabold text-slate-950 sm:text-3xl">What DriveX Assam Riders Say</h2>
    </div>
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
      <?php foreach ($testimonials as $t): ?>
        <div class="card p-6 flex flex-col justify-between bg-slate-50/70">
          <p class="text-sm text-slate-700 leading-relaxed">"<?php echo Security::e($t->Testimonial); ?>"</p>
          <div class="mt-4 border-t border-slate-200 pt-3 flex items-center justify-between">
            <span class="text-sm font-bold text-slate-950"><?php echo Security::e($t->FullName); ?></span>
            <span class="rounded bg-blue-100 px-2 py-0.5 text-[11px] font-semibold text-blue-800">Verified Rider</span>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
