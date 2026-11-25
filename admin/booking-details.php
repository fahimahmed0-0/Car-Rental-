<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;
use App\Services\PricingService;

if (empty($_SESSION['alogin'])) {
    Security::redirect('index.php');
}

$bid = (int) ($_GET['bid'] ?? 0);
if ($bid <= 0) {
    Security::redirect('manage-bookings.php');
}

$bookingSvc = new BookingService($dbh);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'])) {
    Csrf::requireValid();
    $targetStatus = match ((string) $_POST['booking_action']) {
        'confirm' => BookingService::STATUS_CONFIRMED,
        'activate' => BookingService::STATUS_ACTIVE,
        'complete' => BookingService::STATUS_COMPLETED,
        'cancel' => BookingService::STATUS_CANCELLED,
        default => -1,
    };
    if ($targetStatus >= 0) {
        $res = $bookingSvc->transitionStatus($bid, $targetStatus);
        Session::setFlash($res['success'] ? 'success' : 'error', $res['message']);
    }
    Security::redirect('booking-details.php?bid=' . $bid);
}

$stmt = $dbh->prepare(
    'SELECT u.*, br.BrandName, v.VehiclesTitle, v.PricePerDay, v.id AS vid, b.*
     FROM tblbooking b
     JOIN tblvehicles v ON v.id = b.VehicleId
     JOIN tblbrands br ON br.id = v.VehiclesBrand
     JOIN tblusers u ON u.EmailId = b.userEmail
     WHERE b.id = :bid LIMIT 1'
);
$stmt->execute([':bid' => $bid]);
$row = $stmt->fetch();

if (!$row) {
    Session::setFlash('error', 'Booking record not found.');
    Security::redirect('manage-bookings.php');
}

$days = PricingService::calculateDays((string) $row->FromDate, (string) $row->ToDate, false);
$baseAmt = (float) $row->Amount > 0 ? (float) $row->Amount : ((float) $row->PricePerDay * $days);
$discAmt = (float) $row->DiscountAmount;
$finalAmt = (float) $row->FinalAmount > 0 ? (float) $row->FinalAmount : max(0.0, $baseAmt - $discAmt);
$st = (int) $row->Status;

$adminTitle = 'Booking #' . $row->BookingNumber;
$adminNav = 'bookings';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-4xl space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <a href="manage-bookings.php" class="text-xs font-semibold text-blue-700 hover:underline">&larr; Back to Bookings</a>
      <h1 class="mt-1 text-2xl font-extrabold text-slate-950">Booking #<?php echo (int) $row->BookingNumber; ?></h1>
    </div>
    <button type="button" onclick="window.print()" class="btn-outline !py-2 !text-xs print:hidden">Print Details</button>
  </div>

  <div class="card p-6 space-y-6">
    <div class="grid grid-cols-1 gap-6 md:grid-cols-2 border-b border-slate-100 pb-6 text-sm">
      <div class="space-y-1.5">
        <h2 class="text-xs font-bold uppercase tracking-wider text-blue-700">Customer Details</h2>
        <p class="font-bold text-slate-950"><?php echo Security::e($row->FullName); ?></p>
        <p class="text-xs text-slate-600"><?php echo Security::e($row->EmailId); ?> &bull; <?php echo Security::e($row->ContactNo); ?></p>
        <p class="text-xs text-slate-600"><?php echo Security::e(trim(($row->Address ?? '') . ' ' . ($row->City ?? '') . ' ' . ($row->Country ?? ''))); ?></p>
      </div>
      <div class="space-y-1.5">
        <h2 class="text-xs font-bold uppercase tracking-wider text-blue-700">Booking &amp; Payment</h2>
        <p class="font-bold text-slate-950">
          <a href="edit-vehicle.php?id=<?php echo (int) $row->vid; ?>" class="hover:text-blue-700">
            <?php echo Security::e($row->BrandName . ' ' . $row->VehiclesTitle); ?>
          </a>
        </p>
        <p class="text-xs text-slate-600">Dates: <strong><?php echo Security::e($row->FromDate . ' → ' . $row->ToDate); ?></strong> (<?php echo $days; ?> day<?php echo $days > 1 ? 's' : ''; ?>)</p>
        <p class="text-xs text-slate-600">Status: <strong class="text-slate-900"><?php echo Security::e(BookingService::statusLabel($st, (string) $row->PaymentStatus)); ?></strong></p>
        <p class="text-xs text-slate-600">Payment: <strong class="uppercase"><?php echo Security::e($row->PaymentStatus); ?></strong> <?php echo $row->PaymentMethod ? '(' . Security::e($row->PaymentMethod) . ')' : ''; ?></p>
      </div>
    </div>

    <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm space-y-2">
      <div class="flex justify-between text-xs text-slate-600">
        <span>Rate &times; Days (₹<?php echo number_format((float) $row->PricePerDay, 2); ?> &times; <?php echo $days; ?>)</span>
        <span class="font-semibold text-slate-900">₹<?php echo number_format($baseAmt, 2); ?></span>
      </div>
      <?php if ($discAmt > 0): ?>
        <div class="flex justify-between text-xs text-blue-700 font-semibold">
          <span>Token Discount (<?php echo (int) $row->TokensUsed; ?> tokens)</span>
          <span>- ₹<?php echo number_format($discAmt, 2); ?></span>
        </div>
      <?php endif; ?>
      <div class="border-t border-slate-200 pt-2 flex justify-between items-center">
        <span class="font-bold text-slate-950">Grand Total</span>
        <span class="text-xl font-extrabold text-red-600">₹<?php echo number_format($finalAmt, 2); ?></span>
      </div>
    </div>

    <div class="flex flex-wrap gap-2 print:hidden">
      <?php if ($st === BookingService::STATUS_PENDING): ?>
        <form method="post" onsubmit="return confirm('Confirm this booking?');">
          <?php echo Csrf::field(); ?>
          <button type="submit" name="booking_action" value="confirm" class="btn-primary">Confirm Booking</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($st, [BookingService::STATUS_CONFIRMED, BookingService::STATUS_ACTIVE], true)): ?>
        <form method="post" onsubmit="return confirm('Complete booking and award 5 tokens?');">
          <?php echo Csrf::field(); ?>
          <button type="submit" name="booking_action" value="complete" class="btn-dark">Mark Completed (+5 Tokens)</button>
        </form>
      <?php endif; ?>
      <?php if (in_array($st, [BookingService::STATUS_PENDING, BookingService::STATUS_CONFIRMED], true)): ?>
        <form method="post" onsubmit="return confirm('Cancel this booking and refund redeemed tokens?');">
          <?php echo Csrf::field(); ?>
          <button type="submit" name="booking_action" value="cancel" class="btn-red">Cancel Booking</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
