<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;
use App\Services\PricingService;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];
$bookingSvc = new BookingService($dbh);
$bookingSvc->expireStalePendingLocks();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_booking'])) {
    Csrf::requireValid();
    $bnum = (int) ($_POST['booking_number'] ?? 0);
    $res = $bookingSvc->cancelCustomerBooking($bnum, $userEmail);
    Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
    Security::redirect('my-booking.php');
}

$stmt = $dbh->prepare(
    'SELECT b.*, v.Vimage1, v.VehiclesTitle, v.id AS vid, v.PricePerDay, br.BrandName
     FROM tblbooking b
     JOIN tblvehicles v ON b.VehicleId = v.id
     JOIN tblbrands br ON br.id = v.VehiclesBrand
     WHERE b.userEmail = :email
     ORDER BY b.id DESC'
);
$stmt->execute([':email' => strtolower($userEmail)]);
$bookings = $stmt->fetchAll();

$pageTitle = 'DriveX Assam | My Bookings';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">My Bookings &amp; Invoices</h1>
    <p class="text-xs text-slate-300 mt-1">Track confirmations, complete pending payments, or print trip invoices.</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9 space-y-5">
        <?php if (empty($bookings)): ?>
          <div class="card p-10 text-center">
            <h2 class="text-lg font-bold text-slate-900">No Bookings Found</h2>
            <p class="mt-1 text-sm text-slate-600">Find your next car and earn 5 loyalty tokens on completion.</p>
            <a href="car-listing.php" class="btn-primary mt-4">Explore Fleet</a>
          </div>
        <?php else: ?>
          <?php foreach ($bookings as $bk):
            $st = (int) $bk->Status;
            $days = PricingService::calculateDays((string) $bk->FromDate, (string) $bk->ToDate, false);
            $baseAmt = (float) $bk->Amount > 0 ? (float) $bk->Amount : ((float) $bk->PricePerDay * $days);
            $discAmt = (float) $bk->DiscountAmount;
            $finalAmt = (float) $bk->FinalAmount > 0 ? (float) $bk->FinalAmount : max(0.0, $baseAmt - $discAmt);
            $label = BookingService::statusLabel($st, (string) $bk->PaymentStatus);

            $badgeClass = match ($st) {
                BookingService::STATUS_CONFIRMED, BookingService::STATUS_ACTIVE => 'bg-blue-100 text-blue-800 border-blue-200',
                BookingService::STATUS_COMPLETED => 'bg-slate-900 text-white border-slate-900',
                BookingService::STATUS_CANCELLED, BookingService::STATUS_EXPIRED => 'bg-red-100 text-red-800 border-red-200',
                default => 'bg-sky-100 text-sky-900 border-sky-200',
            };
          ?>
            <div class="card p-6 space-y-4">
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                <div>
                  <span class="text-sm font-extrabold text-red-600">Booking #<?php echo (int) $bk->BookingNumber; ?></span>
                  <span class="ml-2 text-xs text-slate-500">Placed <?php echo Security::e(date('d M Y, h:i A', strtotime((string) $bk->PostingDate))); ?></span>
                </div>
                <span class="rounded-full border px-3 py-1 text-xs font-bold <?php echo $badgeClass; ?>">
                  <?php echo Security::e($label); ?>
                </span>
              </div>

              <div class="grid grid-cols-1 gap-5 md:grid-cols-12 items-center">
                <div class="md:col-span-4">
                  <a href="vehicle-details.php?vhid=<?php echo (int) $bk->vid; ?>">
                    <img src="<?php echo Security::e(Security::vehicleImageUrl($bk->Vimage1)); ?>" alt="<?php echo Security::e($bk->VehiclesTitle); ?>" class="h-36 w-full rounded-xl object-cover border border-slate-200">
                  </a>
                </div>
                <div class="md:col-span-8 space-y-2 text-sm">
                  <h3 class="text-lg font-extrabold text-slate-950">
                    <a href="vehicle-details.php?vhid=<?php echo (int) $bk->vid; ?>" class="hover:text-blue-700">
                      <?php echo Security::e($bk->BrandName . ' ' . $bk->VehiclesTitle); ?>
                    </a>
                  </h3>
                  <div class="grid grid-cols-2 gap-2 text-xs text-slate-600">
                    <div><strong>From:</strong> <?php echo Security::e($bk->FromDate); ?></div>
                    <div><strong>To:</strong> <?php echo Security::e($bk->ToDate); ?> (<?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>)</div>
                    <div><strong>Daily Rate:</strong> ₹<?php echo number_format((float) $bk->PricePerDay, 2); ?></div>
                    <div><strong>Payment:</strong> <?php echo Security::e(ucfirst((string) $bk->PaymentStatus)); ?></div>
                  </div>
                  <?php if (!empty($bk->message)): ?>
                    <p class="text-xs text-slate-600"><strong>Note:</strong> <?php echo Security::e($bk->message); ?></p>
                  <?php endif; ?>

                  <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 flex flex-wrap items-center justify-between gap-3 text-xs">
                    <div>
                      <span class="text-slate-600">Base: ₹<?php echo number_format($baseAmt, 2); ?></span>
                      <?php if ($discAmt > 0): ?>
                        <span class="ml-2 font-semibold text-blue-700">Discount: -₹<?php echo number_format($discAmt, 2); ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-base font-extrabold text-slate-950">
                      Final: <span class="text-red-600">₹<?php echo number_format($finalAmt, 2); ?></span>
                    </div>
                  </div>
                </div>
              </div>

              <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-100 pt-3">
                <?php if ($st === BookingService::STATUS_PENDING && $bk->PaymentStatus === 'pending'): ?>
                  <a href="payment.php?booking_id=<?php echo (int) $bk->BookingNumber; ?>" class="btn-red !py-2 !text-xs">Complete Payment</a>
                <?php endif; ?>
                <?php if ($bk->PaymentStatus === 'completed'): ?>
                  <a href="payment-success.php?booking_id=<?php echo (int) $bk->BookingNumber; ?>" class="btn-outline !py-2 !text-xs">View Receipt</a>
                <?php endif; ?>
                <?php if (in_array($st, [BookingService::STATUS_PENDING, BookingService::STATUS_CONFIRMED], true)): ?>
                  <form method="post" onsubmit="return confirm('Cancel this booking? Any redeemed tokens will be refunded automatically.');">
                    <?php echo Csrf::field(); ?>
                    <input type="hidden" name="booking_number" value="<?php echo (int) $bk->BookingNumber; ?>">
                    <button type="submit" name="cancel_booking" value="1" class="btn-outline !border-red-300 !text-red-600 hover:!bg-red-50 !py-2 !text-xs">
                      Cancel Booking
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
