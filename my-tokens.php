<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Services\TokenLedgerService;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];
$tokenSvc = new TokenLedgerService($dbh);
$summary = $tokenSvc->getBalance($userEmail);
$history = $tokenSvc->getHistory($userEmail, 50);

$pageTitle = 'DriveX Assam | Loyalty Tokens';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">Loyalty Token Wallet</h1>
    <p class="text-xs text-slate-300 mt-1">Earn 5 tokens on every completed trip and redeem them for instant rental discounts.</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9 space-y-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="card p-6 border-t-4 border-t-blue-700">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Available Balance</div>
            <div class="mt-2 text-3xl font-extrabold text-blue-700"><?php echo (int) $summary['balance']; ?> Tokens</div>
            <p class="mt-1 text-xs text-slate-600">Worth up to ₹<?php echo number_format(($summary['balance'] / 5) * 100); ?> in discounts</p>
          </div>
          <div class="card p-6 border-t-4 border-t-slate-900">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Lifetime Earned</div>
            <div class="mt-2 text-3xl font-extrabold text-slate-950"><?php echo (int) $summary['total_earned']; ?> Tokens</div>
            <p class="mt-1 text-xs text-slate-600">5 tokens automatically credited once per completed trip</p>
          </div>
        </div>

        <div class="rounded-xl border border-sky-200 bg-blue-50/70 p-5 text-xs text-blue-950 space-y-1.5">
          <h2 class="text-sm font-bold text-slate-950">How DriveX Tokens Work</h2>
          <p>&bull; <strong>Earn:</strong> +5 tokens when an admin marks your confirmed booking as Completed.</p>
          <p>&bull; <strong>Redeem:</strong> 5 tokens = ₹100 discount (minimum 15 tokens per booking, redeemed in multiples of 5).</p>
          <p>&bull; <strong>Protection:</strong> If a booking with redeemed tokens is cancelled, your tokens are automatically refunded.</p>
        </div>

        <div class="card overflow-hidden">
          <div class="border-b border-slate-100 px-6 py-4">
            <h2 class="text-base font-extrabold text-slate-950">Token Ledger History</h2>
          </div>
          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-slate-50 text-slate-600 uppercase">
                <tr>
                  <th class="px-5 py-3">Date</th>
                  <th class="px-5 py-3">Event</th>
                  <th class="px-5 py-3">Booking</th>
                  <th class="px-5 py-3">Change</th>
                  <th class="px-5 py-3">Discount</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <?php if (empty($history)): ?>
                  <tr><td colspan="5" class="px-5 py-6 text-center text-slate-500">No token activity yet.</td></tr>
                <?php else: ?>
                  <?php foreach ($history as $row):
                    $delta = (int) $row->delta;
                  ?>
                    <tr>
                      <td class="px-5 py-3 text-slate-600"><?php echo Security::e(date('d M Y', strtotime((string) $row->created_at))); ?></td>
                      <td class="px-5 py-3 font-medium text-slate-900"><?php echo Security::e($row->note ?? $row->entry_type); ?></td>
                      <td class="px-5 py-3 text-slate-600"><?php echo $row->booking_number ? '#' . (int) $row->booking_number : '—'; ?></td>
                      <td class="px-5 py-3 font-extrabold <?php echo $delta >= 0 ? 'text-blue-700' : 'text-red-600'; ?>">
                        <?php echo $delta >= 0 ? '+' . $delta : (string) $delta; ?>
                      </td>
                      <td class="px-5 py-3 text-slate-700"><?php echo (float) $row->discount_amount > 0 ? '₹' . number_format((float) $row->discount_amount, 2) : '—'; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
