<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\MailService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_read_id'])) {
    Csrf::requireValid();
    $qid = (int) $_POST['mark_read_id'];
    $dbh->prepare('UPDATE tblcontactusquery SET status = 1 WHERE id = :id')->execute([':id' => $qid]);
    Session::setFlash('success', 'Query marked as read.');
    Security::redirect('manage-queries.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    Csrf::requireValid();
    $qid = (int) ($_POST['query_id'] ?? 0);
    $toEmail = trim((string) ($_POST['reply_email'] ?? ''));
    $toName = trim((string) ($_POST['reply_name'] ?? 'Customer'));
    $replyBody = trim((string) ($_POST['reply_body'] ?? ''));

    if ($toEmail !== '' && $replyBody !== '') {
        $mailer = new MailService($dbh);
        $mailRes = $mailer->send($toEmail, $toName, 'Re: Your DriveX Assam Inquiry', nl2br(Security::e($replyBody)));
        $dbh->prepare('UPDATE tblcontactusquery SET status = 1 WHERE id = :id')->execute([':id' => $qid]);
        Session::setFlash('success', 'Reply processed (' . $mailRes['mode'] . ').');
    }
    Security::redirect('manage-queries.php');
}

$queries = $dbh->query('SELECT * FROM tblcontactusquery ORDER BY id DESC')->fetchAll();

$adminTitle = 'Contact Inquiries';
$adminNav = 'queries';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5" x-data="queryCopilot()">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-950">Contact Inquiries</h1>
    <p class="text-xs text-slate-500">Review customer messages, generate draft responses, and send replies via MailService.</p>
  </div>

  <div class="space-y-4">
    <?php if (empty($queries)): ?>
      <div class="card p-8 text-center text-sm text-slate-500">No contact queries yet.</div>
    <?php else: ?>
      <?php foreach ($queries as $q): ?>
        <div class="card p-5 space-y-3">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="text-sm font-extrabold text-slate-950"><?php echo Security::e($q->name); ?></div>
              <div class="text-xs text-slate-600"><?php echo Security::e($q->EmailId . ' • ' . $q->ContactNumber); ?></div>
            </div>
            <div class="flex items-center gap-2">
              <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold <?php echo ((int) $q->status === 1) ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-700'; ?>">
                <?php echo ((int) $q->status === 1) ? 'Read / Replied' : 'Pending'; ?>
              </span>
              <span class="text-[11px] text-slate-400"><?php echo Security::e($q->PostingDate); ?></span>
            </div>
          </div>

          <p class="rounded-lg bg-slate-50 p-3 text-xs text-slate-800 border border-slate-200"><?php echo nl2br(Security::e($q->Message)); ?></p>

          <div class="flex flex-wrap items-center gap-2">
            <button type="button"
                    @click="draftReply(<?php echo (int) $q->id; ?>, <?php echo Security::e(json_encode($q->name)); ?>, <?php echo Security::e(json_encode($q->EmailId)); ?>, <?php echo Security::e(json_encode($q->Message)); ?>)"
                    class="btn-primary !py-1.5 !px-3 !text-xs">
              Draft AI Reply
            </button>

            <?php if ((int) $q->status !== 1): ?>
              <form method="post" class="inline">
                <?php echo Csrf::field(); ?>
                <input type="hidden" name="mark_read_id" value="<?php echo (int) $q->id; ?>">
                <button type="submit" class="btn-outline !py-1.5 !px-3 !text-xs">Mark as Read</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Reply Draft Modal -->
  <div x-show="modalOpen" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
    <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl border border-slate-200 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-100 pb-3">
        <div>
          <h3 class="text-base font-extrabold text-slate-950">Reply to <span x-text="targetName"></span></h3>
          <p class="text-[11px] text-blue-700 font-semibold" x-text="tierLabel"></p>
        </div>
        <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-700 text-lg">&times;</button>
      </div>
      <form method="post" class="space-y-4">
        <?php echo Csrf::field(); ?>
        <input type="hidden" name="query_id" :value="targetId">
        <input type="hidden" name="reply_email" :value="targetEmail">
        <input type="hidden" name="reply_name" :value="targetName">
        <div>
          <label class="mb-1 block text-xs font-bold uppercase text-slate-600">Suggested Reply (Editable)</label>
          <textarea name="reply_body" x-model="draftText" rows="6" required class="form-input"></textarea>
        </div>
        <div class="flex justify-end gap-2">
          <button type="button" @click="modalOpen = false" class="btn-outline">Cancel</button>
          <button type="submit" name="send_reply" value="1" class="btn-primary">Send Reply</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function queryCopilot() {
  return {
    modalOpen: false,
    targetId: 0,
    targetName: '',
    targetEmail: '',
    draftText: '',
    tierLabel: '',
    async draftReply(id, name, email, message) {
      this.targetId = id;
      this.targetName = name;
      this.targetEmail = email;
      this.draftText = 'Drafting reply...';
      this.tierLabel = 'Loading...';
      this.modalOpen = true;
      var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      try {
        var res = await fetch('../api/v1/ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'draft_reply', customer_name: name, message: message })
        });
        var data = await res.json();
        if (data.success) {
          this.draftText = data.reply || '';
          this.tierLabel = 'Drafted via ' + (data.tier_label || 'Smart Template');
        }
      } catch (e) {
        this.draftText = 'Hello ' + name + ',\n\nThank you for contacting DriveX Assam. Our team has reviewed your inquiry and will assist you shortly.\n\nBest regards,\nDriveX Assam Support';
      }
    }
  };
}
</script>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
