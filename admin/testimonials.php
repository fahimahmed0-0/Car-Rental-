<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_id'], $_POST['target_status'])) {
    Csrf::requireValid();
    $tid = (int) $_POST['toggle_id'];
    $st = ((int) $_POST['target_status'] === 1) ? 1 : 0;
    $upd = $dbh->prepare('UPDATE tbltestimonial SET status = :st WHERE id = :id');
    $upd->execute([':st' => $st, ':id' => $tid]);
    Session::setFlash('success', $st === 1 ? 'Testimonial activated.' : 'Testimonial deactivated.');
    Security::redirect('testimonials.php');
}

$rows = $dbh->query(
    'SELECT t.*, u.FullName FROM tbltestimonial t JOIN tblusers u ON u.EmailId = t.UserEmail ORDER BY t.id DESC'
)->fetchAll();

$adminTitle = 'Manage Testimonials';
$adminNav = 'testimonials';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-950">Manage Testimonials</h1>
    <p class="text-xs text-slate-500">Approve customer reviews with automated spam/toxicity pre-screening.</p>
  </div>

  <div class="card overflow-hidden">
    <table class="w-full text-left text-xs">
      <thead class="bg-slate-50 text-slate-600 uppercase border-b border-slate-200">
        <tr>
          <th class="px-4 py-3">#</th>
          <th class="px-4 py-3">Customer</th>
          <th class="px-4 py-3">Testimonial</th>
          <th class="px-4 py-3">AI Screen</th>
          <th class="px-4 py-3">Status</th>
          <th class="px-4 py-3">Action</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        <?php if (empty($rows)): ?>
          <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No testimonials submitted yet.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $idx => $r):
            $isActive = ((int) $r->status === 1);
            $flag = (string) ($r->AiModerationFlag ?? 'clean');
          ?>
            <tr class="hover:bg-slate-50">
              <td class="px-4 py-3 text-slate-500"><?php echo $idx + 1; ?></td>
              <td class="px-4 py-3">
                <div class="font-bold text-slate-900"><?php echo Security::e($r->FullName); ?></div>
                <div class="text-[11px] text-slate-500"><?php echo Security::e($r->UserEmail); ?></div>
              </td>
              <td class="px-4 py-3 text-slate-700 max-w-md"><?php echo Security::e($r->Testimonial); ?></td>
              <td class="px-4 py-3">
                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold <?php echo $flag === 'flagged' ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-800'; ?>">
                  <?php echo Security::e(ucfirst($flag)); ?>
                </span>
              </td>
              <td class="px-4 py-3 font-bold <?php echo $isActive ? 'text-blue-700' : 'text-slate-500'; ?>">
                <?php echo $isActive ? 'Active' : 'Inactive'; ?>
              </td>
              <td class="px-4 py-3">
                <form method="post" class="inline">
                  <?php echo Csrf::field(); ?>
                  <input type="hidden" name="toggle_id" value="<?php echo (int) $r->id; ?>">
                  <input type="hidden" name="target_status" value="<?php echo $isActive ? 0 : 1; ?>">
                  <button type="submit" class="<?php echo $isActive ? 'btn-red' : 'btn-primary'; ?> !py-1 !px-2.5 !text-[11px]">
                    <?php echo $isActive ? 'Deactivate' : 'Approve'; ?>
                  </button>
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
