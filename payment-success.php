<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];
$bookingNumber = (int) ($_GET['booking_id'] ?? 0);
if ($bookingNumber <= 0) {
    Security::redirect('my-booking.php');
}

$bookingSvc = new BookingService($dbh);
$booking = $bookingSvc->getBookingByNumberForUser($bookingNumber, $userEmail);

if (!$booking) {
    Session::setFlash('error', 'Booking receipt not found.');
    Security::redirect('my-booking.php');
}

$txnStmt = $dbh->prepare(
    'SELECT * FROM tblpaymenttransactions WHERE booking_id = :bnum AND user_email = :email ORDER BY id DESC LIMIT 1'
);
$txnStmt->execute([':bnum' => $bookingNumber, ':email' => strtolower($userEmail)]);
$txn = $txnStmt->fetch();

$pageTitle = 'DriveX Assam | Payment Receipt #' . $bookingNumber;
include __DIR__ . '/includes/layout_head.php';
?>

<section class="py-12 flex-1">
  <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
    <div class="card p-8 border-t-4 border-t-blue-700">
      <div class="text-center border-b border-slate-100 pb-6">
        <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-blue-700 text-2xl font-bold text-white">&#10003;</div>
        <h1 class="text-2xl font-extrabold text-slate-950">Payment Confirmed</h1>
        <p class="mt-1 text-sm text-slate-600">Your DriveX Assam reservation is confirmed and ready for pickup.</p>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3 rounded-xl border border-sky-200 bg-blue-50/70 p-4 text-xs">
        <div>
          <span class="block text-slate-500 font-semibold">Booking Number</span>
          <span class="text-sm font-extrabold text-slate-950">#<?php echo (int) $bookingNumber; ?></span>
        </div>
        <div>
          <span class="block text-slate-500 font-semibold">Transaction ID</span>
          <span class="text-sm font-bold text-blue-800 font-mono"><?php echo Security::e($txn->transaction_id ?? 'CONFIRMED'); ?></span>
        </div>
        <div>
          <span class="block text-slate-500 font-semibold">Amount Paid</span>
          <span class="text-sm font-extrabold text-red-600">₹<?php echo number_format((float) ($txn->amount ?? $booking->FinalAmount), 2); ?></span>
        </div>
      </div>

      <div class="mt-6 flex flex-col sm:flex-row gap-5 items-center border-b border-slate-100 pb-6">
        <img src="<?php echo Security::e(Security::vehicleImageUrl($booking->Vimage1)); ?>" alt="<?php echo Security::e($booking->VehiclesTitle); ?>" class="h-32 w-48 rounded-xl object-cover border border-slate-200">
        <div class="space-y-1 text-sm">
          <div class="text-xs font-bold text-blue-700"><?php echo Security::e($booking->BrandName); ?></div>
          <h2 class="text-lg font-extrabold text-slate-950"><?php echo Security::e($booking->VehiclesTitle); ?></h2>
          <p class="text-xs text-slate-600"><?php echo Security::e($booking->FuelType); ?> &bull; <?php echo (int) $booking->SeatingCapacity; ?> Seats &bull; <?php echo (int) $booking->ModelYear; ?></p>
          <p class="text-xs text-slate-700 pt-1"><strong>Trip Dates:</strong> <?php echo Security::e($booking->FromDate); ?> to <?php echo Security::e($booking->ToDate); ?></p>
          <p class="text-xs text-slate-700"><strong>Pickup Hub:</strong> <?php echo Security::e($booking->PickupLocation ?? 'DriveX Hub, Guwahati, Assam'); ?></p>
        </div>
      </div>

      <div class="mt-6 flex flex-wrap items-center justify-center gap-3 print:hidden">
        <button type="button" onclick="window.print()" class="btn-outline">Print Receipt</button>
        <a href="my-booking.php" class="btn-primary">View My Bookings</a>
        <a href="payment-history.php" class="btn-dark">Payment History</a>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
