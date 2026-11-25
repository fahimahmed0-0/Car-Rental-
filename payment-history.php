<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Services\PaymentService;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];
$paymentSvc = new PaymentService($dbh);
$history = $paymentSvc->getUserPaymentHistory($userEmail, 100);

$completedCount = 0;
$totalPaid = 0.0;
foreach ($history as $tx) {
    if ($tx->payment_status === 'completed') {
        $completedCount++;
        $totalPaid += (float) $tx->amount;
    }
}

$pageTitle = 'DriveX Assam | Payment History';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">Payment History</h1>
    <p class="text-xs text-slate-300 mt-1">Verified transaction records for all your online rentals.</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9 space-y-6">
        <!-- Summary Cards -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
          <div class="card p-5 border-t-4 border-t-slate-900">
            <div class="text-xs font-bold uppercase text-slate-500">Total Transactions</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-950"><?php echo count($history); ?></div>
          </div>
          <div class="card p-5 border-t-4 border-t-blue-700">
            <div class="text-xs font-bold uppercase text-slate-500">Total Paid</div>
            <div class="mt-1 text-2xl font-extrabold text-blue-700">₹<?php echo number_format($totalPaid, 2); ?></div>
          </div>
          <div class="card p-5 border-t-4 border-t-red-600">
            <div class="text-xs font-bold uppercase text-slate-500">Successful Payments</div>
            <div class="mt-1 text-2xl font-extrabold text-red-600"><?php echo $completedCount; ?></div>
          </div>
        </div>

        <?php if (empty($history)): ?>
          <div class="card p-10 text-center">
            <h2 class="text-lg font-bold text-slate-900">No Payment Transactions Yet</h2>
            <p class="mt-1 text-sm text-slate-600">Once you complete an online checkout, receipts will appear here.</p>
            <a href="car-listing.php" class="btn-primary mt-4">Browse Fleet</a>
          </div>
        <?php else: ?>
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <?php foreach ($history as $tx): ?>
              <div class="card p-5 space-y-3">
                <div class="flex items-start justify-between gap-2">
                  <div>
                    <div class="text-xs font-bold text-blue-700">Booking #<?php echo (int) $tx->booking_id; ?></div>
                    <h3 class="text-base font-bold text-slate-950"><?php echo Security::e($tx->BrandName . ' ' . $tx->VehiclesTitle); ?></h3>
                  </div>
                  <div class="text-right">
                    <div class="text-lg font-extrabold text-red-600">₹<?php echo number_format((float) $tx->amount, 2); ?></div>
                    <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold <?php echo $tx->payment_status === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-700'; ?>">
                      <?php echo Security::e(ucfirst($tx->payment_status)); ?>
                    </span>
                  </div>
                </div>
                <div class="flex flex-wrap items-center justify-between text-xs text-slate-600 border-t border-slate-100 pt-3">
                  <span>Method: <strong class="uppercase text-slate-900"><?php echo Security::e($tx->payment_method); ?></strong> (<?php echo Security::e($tx->provider ?? 'sandbox'); ?>)</span>
                  <span><?php echo Security::e(date('d M Y, h:i A', strtotime((string) $tx->created_at))); ?></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                  <span class="font-mono text-slate-500"><?php echo Security::e($tx->transaction_id); ?></span>
                  <a href="payment-success.php?booking_id=<?php echo (int) $tx->booking_id; ?>" class="font-bold text-blue-700 hover:underline">Receipt &rarr;</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
