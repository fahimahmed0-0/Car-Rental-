<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;

if (empty($_SESSION['alogin'])) {
    Security::redirect('index.php');
}

$bookingSvc = new BookingService($dbh);
$bookingSvc->expireStalePendingLocks();

$redirectSelf = basename((string) ($_SERVER['PHP_SELF'] ?? 'manage-bookings.php'));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['booking_action'], $_POST['booking_id'])) {
    Csrf::requireValid();
    $bid = (int) $_POST['booking_id'];
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
    Security::redirect($redirectSelf . (isset($_GET['status']) ? '?status=' . urlencode((string) $_GET['status']) : ''));
}

// Determine status filter
$statusFilter = $forcedStatusFilter ?? (isset($_GET['status']) && $_GET['status'] !== '' ? (string) $_GET['status'] : 'all');

$whereSql = match ($statusFilter) {
    'pending' => 'WHERE b.Status = 0',
    'confirmed' => 'WHERE b.Status IN (1, 4)',
    'completed' => 'WHERE b.Status = 3',
    'cancelled' => 'WHERE b.Status IN (2, 5)',
    default => '',
};

$sql = "SELECT u.FullName, u.EmailId, br.BrandName, v.VehiclesTitle, v.id AS vid,
               b.id, b.BookingNumber, b.FromDate, b.ToDate, b.Status, b.PaymentStatus, b.FinalAmount, b.PostingDate,
               tl.delta AS tokens_awarded
        FROM tblbooking b
        JOIN tblvehicles v ON v.id = b.VehicleId
        JOIN tblbrands br ON br.id = v.VehiclesBrand
        JOIN tblusers u ON u.EmailId = b.userEmail
        LEFT JOIN tbltokenledger tl ON tl.booking_id = b.id AND tl.entry_type = 'earn_completion'
        {$whereSql}
        ORDER BY b.id DESC";

$rows = $dbh->query($sql)->fetchAll();

include __DIR__ . '/layout_head.php';
?>

<div class="space-y-5">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-950"><?php echo Security::e($adminTitle); ?></h1>
      <p class="text-xs text-slate-500">All booking transitions use atomic locking and idempotent loyalty token awards.</p>
    </div>
    <?php if (!isset($forcedStatusFilter)): ?>
      <div class="flex flex-wrap gap-1.5 text-xs">
        <?php foreach (['all' => 'All', 'pending' => 'Pending', 'confirmed' => 'Confirmed / Active', 'completed' => 'Completed', 'cancelled' => 'Cancelled / Expired'] as $k => $lbl): ?>
          <a href="manage-bookings.php?status=<?php echo $k; ?>"
             class="rounded-lg px-3 py-1.5 font-semibold border transition <?php echo $statusFilter === $k ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-blue-600'; ?>">
            <?php echo Security::e($lbl); ?>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
          <tr>
            <th class="px-4 py-3">#</th>
            <th class="px-4 py-3">Customer</th>
            <th class="px-4 py-3">Booking No.</th>
            <th class="px-4 py-3">Vehicle</th>
            <th class="px-4 py-3">Dates</th>
            <th class="px-4 py-3">Amount</th>
            <th class="px-4 py-3">Status</th>
            <th class="px-4 py-3">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (empty($rows)): ?>
            <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No bookings found in this category.</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $idx => $r):
              $st = (int) $r->Status;
              $lbl = BookingService::statusLabel($st, (string) $r->PaymentStatus);
            ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
                <td class="px-4 py-3">
                  <div class="font-bold text-slate-900"><?php echo Security::e($r->FullName); ?></div>
                  <div class="text-[11px] text-slate-500"><?php echo Security::e($r->EmailId); ?></div>
                </td>
                <td class="px-4 py-3 font-extrabold text-red-600">#<?php echo (int) $r->BookingNumber; ?></td>
                <td class="px-4 py-3">
                  <a href="edit-vehicle.php?id=<?php echo (int) $r->vid; ?>" class="font-semibold text-blue-700 hover:underline">
                    <?php echo Security::e($r->BrandName . ' ' . $r->VehiclesTitle); ?>
                  </a>
                </td>
                <td class="px-4 py-3 text-slate-700"><?php echo Security::e($r->FromDate . ' → ' . $r->ToDate); ?></td>
                <td class="px-4 py-3 font-bold text-slate-900">
                  ₹<?php echo number_format((float) $r->FinalAmount, 2); ?>
                  <div class="text-[10px] font-normal text-slate-500"><?php echo Security::e($r->PaymentStatus); ?></div>
                </td>
                <td class="px-4 py-3">
                  <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold <?php echo match ($st) {
                      1, 4 => 'bg-blue-100 text-blue-800',
                      3 => 'bg-slate-900 text-white',
                      2, 5 => 'bg-red-100 text-red-800',
                      default => 'bg-sky-100 text-sky-900',
                  }; ?>">
                    <?php echo Security::e($lbl); ?>
                  </span>
                  <?php if ($st === 3 && $r->tokens_awarded): ?>
                    <div class="mt-0.5 text-[10px] font-semibold text-blue-700">+<?php echo (int) $r->tokens_awarded; ?> tokens</div>
                  <?php endif; ?>
                </td>
                <td class="px-4 py-3">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <a href="booking-details.php?bid=<?php echo (int) $r->id; ?>" class="btn-outline !py-1 !px-2.5 !text-[11px]">View</a>

                    <?php if ($st === BookingService::STATUS_PENDING): ?>
                      <form method="post" class="inline" onsubmit="return confirm('Confirm this booking?');">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo (int) $r->id; ?>">
                        <button type="submit" name="booking_action" value="confirm" class="btn-primary !py-1 !px-2.5 !text-[11px]">Confirm</button>
                      </form>
                    <?php endif; ?>

                    <?php if (in_array($st, [BookingService::STATUS_CONFIRMED, BookingService::STATUS_ACTIVE], true)): ?>
                      <form method="post" class="inline" onsubmit="return confirm('Mark completed and award 5 tokens to the customer?');">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo (int) $r->id; ?>">
                        <button type="submit" name="booking_action" value="complete" class="btn-dark !py-1 !px-2.5 !text-[11px]">Complete (+5 Tokens)</button>
                      </form>
                    <?php endif; ?>

                    <?php if (in_array($st, [BookingService::STATUS_PENDING, BookingService::STATUS_CONFIRMED], true)): ?>
                      <form method="post" class="inline" onsubmit="return confirm('Cancel this booking and refund any redeemed tokens?');">
                        <?php echo Csrf::field(); ?>
                        <input type="hidden" name="booking_id" value="<?php echo (int) $r->id; ?>">
                        <button type="submit" name="booking_action" value="cancel" class="btn-red !py-1 !px-2.5 !text-[11px]">Cancel</button>
                      </form>
                    <?php endif; ?>
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

<?php include __DIR__ . '/layout_foot.php'; ?>
