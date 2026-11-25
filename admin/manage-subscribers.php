<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['del_sub_id'])) {
    Csrf::requireValid();
    $sid = (int) $_POST['del_sub_id'];
    $dbh->prepare('DELETE FROM tblsubscribers WHERE id = :id')->execute([':id' => $sid]);
    Session::setFlash('success', 'Subscriber removed.');
    Security::redirect('manage-subscribers.php');
}

$subs = $dbh->query('SELECT * FROM tblsubscribers ORDER BY id DESC')->fetchAll();

$adminTitle = 'Manage Subscribers';
$adminNav = 'subscribers';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <h1 class="text-2xl font-extrabold text-slate-950">Newsletter Subscribers</h1>
  <div class="card overflow-hidden">
    <table class="w-full text-left text-xs">
      <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Email</th>
          <th class="px-4 py-3">Subscribed Date</th>
          <th class="px-4 py-3">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($subs)): ?>
          <tr><td colspan="4" class="px-4 py-8 text-center text-slate-500">No subscribers yet.</td></tr>
        <?php else: ?>
          <?php foreach ($subs as $idx => $s): ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
              <td class="px-4 py-3 font-bold text-slate-900"><?php echo Security::e($s->SubscriberEmail); ?></td>
              <td class="px-4 py-3 text-slate-600"><?php echo Security::e($s->PostingDate); ?></td>
              <td class="px-4 py-3">
                <form method="post" onsubmit="return confirm('Remove this subscriber?');">
                  <?php echo Csrf::field(); ?>
                  <input type="hidden" name="del_sub_id" value="<?php echo (int) $s->id; ?>">
                  <button type="submit" class="btn-red !py-1 !px-2.5 !text-[11px]">Delete</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
