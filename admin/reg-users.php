<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$users = $dbh->query('SELECT * FROM tblusers ORDER BY id DESC')->fetchAll();

$adminTitle = 'Registered Users';
$adminNav = 'users';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-950">Registered Users</h1>
    <p class="text-xs text-slate-500">Customer directory and current loyalty token balances.</p>
  </div>

  <div class="card overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
          <tr>
            <th class="px-4 py-3">#</th>
            <th class="px-4 py-3">Name</th>
            <th class="px-4 py-3">Email</th>
            <th class="px-4 py-3">Contact</th>
            <th class="px-4 py-3">City / Country</th>
            <th class="px-4 py-3">Tokens</th>
            <th class="px-4 py-3">Registered</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <?php if (empty($users)): ?>
            <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No registered users yet.</td></tr>
          <?php else: ?>
            <?php foreach ($users as $idx => $u): ?>
              <tr class="hover:bg-slate-50">
                <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
                <td class="px-4 py-3 font-bold text-slate-900"><?php echo Security::e($u->FullName); ?></td>
                <td class="px-4 py-3 text-slate-700"><?php echo Security::e($u->EmailId); ?></td>
                <td class="px-4 py-3 text-slate-700"><?php echo Security::e($u->ContactNo); ?></td>
                <td class="px-4 py-3 text-slate-600"><?php echo Security::e(trim(($u->City ?? '') . ' ' . ($u->Country ?? '')) ?: '—'); ?></td>
                <td class="px-4 py-3 font-bold text-blue-700"><?php echo (int) ($u->TokenBalance ?? 0); ?></td>
                <td class="px-4 py-3 text-slate-500"><?php echo Security::e($u->RegDate); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
