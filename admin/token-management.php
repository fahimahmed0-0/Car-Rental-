<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\TokenLedgerService;

$tokenSvc = new TokenLedgerService($dbh);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_tokens'])) {
    Csrf::requireValid();
    try {
        $email = trim((string) ($_POST['user_email'] ?? ''));
        $delta = (int) ($_POST['delta'] ?? 0);
        $note = trim((string) ($_POST['note'] ?? ''));
        $tokenSvc->adminAdjust($email, $delta, $note);
        Session::setFlash('success', "Adjusted {$delta} tokens for {$email}.");
    } catch (Throwable $e) {
        Session::setFlash('error', $e->getMessage());
    }
    Security::redirect('token-management.php');
}

$summary = $tokenSvc->getAdminSummary();

$adminTitle = 'Loyalty Token Management';
$adminNav = 'tokens';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-6">
  <h1 class="text-2xl font-extrabold text-slate-950">Loyalty Token Management</h1>

  <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="card p-5 border-t-4 border-t-blue-700">
      <div class="text-xs font-bold uppercase text-slate-500">Total Tokens Issued</div>
      <div class="mt-2 text-3xl font-extrabold text-blue-700"><?php echo (int) $summary['total_issued']; ?></div>
    </div>
    <div class="card p-5 border-t-4 border-t-slate-900">
      <div class="text-xs font-bold uppercase text-slate-500">Total Tokens Redeemed</div>
      <div class="mt-2 text-3xl font-extrabold text-slate-950"><?php echo (int) $summary['total_redeemed']; ?></div>
    </div>
    <div class="card p-5 border-t-4 border-t-red-600">
      <div class="text-xs font-bold uppercase text-slate-500">Total Discount Given</div>
      <div class="mt-2 text-3xl font-extrabold text-red-600">₹<?php echo number_format((float) $summary['total_discount'], 2); ?></div>
    </div>
  </div>

  <!-- Admin Manual Adjustment -->
  <div class="card p-6">
    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 mb-3">Manual Token Adjustment (Audited in Ledger)</h2>
    <form method="post" class="grid grid-cols-1 gap-3 sm:grid-cols-12">
      <?php echo Csrf::field(); ?>
      <div class="sm:col-span-4">
        <select name="user_email" required class="form-input">
          <option value="">Select Customer</option>
          <?php foreach ($summary['users'] as $u): ?>
            <option value="<?php echo Security::e($u->EmailId); ?>">
              <?php echo Security::e($u->FullName . ' (' . $u->EmailId . ') - ' . (int) $u->TokenBalance . ' tok'); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="sm:col-span-2">
        <input type="number" name="delta" required class="form-input" placeholder="+10 or -5">
      </div>
      <div class="sm:col-span-4">
        <input type="text" name="note" required class="form-input" placeholder="Reason (e.g. Goodwill bonus)">
      </div>
      <div class="sm:col-span-2">
        <button type="submit" name="adjust_tokens" class="btn-primary w-full">Apply</button>
      </div>
    </form>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-left text-xs">
      <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Customer</th>
          <th class="px-4 py-3">Email</th>
          <th class="px-4 py-3">Current Balance</th>
          <th class="px-4 py-3">Total Earned</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php foreach ($summary['users'] as $idx => $u): ?>
          <tr class="hover:bg-slate-50">
            <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
            <td class="px-4 py-3 font-bold text-slate-900"><?php echo Security::e($u->FullName); ?></td>
            <td class="px-4 py-3 text-slate-700"><?php echo Security::e($u->EmailId); ?></td>
            <td class="px-4 py-3 font-extrabold text-blue-700"><?php echo (int) $u->TokenBalance; ?></td>
            <td class="px-4 py-3 text-slate-700"><?php echo (int) $u->TotalTokensEarned; ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
