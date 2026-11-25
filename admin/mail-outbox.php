<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$rows = $dbh->query('SELECT * FROM tblmailoutbox ORDER BY id DESC LIMIT 100')->fetchAll();

$adminTitle = 'Mail Outbox (Fallback Queue)';
$adminNav = 'outbox';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="space-y-5">
  <div>
    <h1 class="text-2xl font-extrabold text-slate-950">Mail Outbox &amp; Fallback Log</h1>
    <p class="text-xs text-slate-500">When SMTP is unconfigured or offline, DriveX Assam safely queues all OTPs, booking confirmations, and replies here.</p>
  </div>

  <div class="space-y-3">
    <?php if (empty($rows)): ?>
      <div class="card p-8 text-center text-sm text-slate-500">Outbox is empty.</div>
    <?php else: ?>
      <?php foreach ($rows as $mail): ?>
        <div class="card p-5 space-y-2">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <span class="text-sm font-bold text-slate-950"><?php echo Security::e($mail->subject); ?></span>
              <span class="ml-2 text-xs text-slate-500">To: <?php echo Security::e($mail->recipient); ?></span>
            </div>
            <div class="flex items-center gap-2 text-xs">
              <span class="rounded-full px-2.5 py-0.5 font-bold <?php echo $mail->status === 'sent' ? 'bg-blue-100 text-blue-800' : 'bg-sky-100 text-sky-900'; ?>">
                <?php echo Security::e($mail->status); ?>
              </span>
              <span class="text-slate-400"><?php echo Security::e($mail->created_at); ?></span>
            </div>
          </div>
          <div class="rounded-lg bg-slate-50 p-3 text-xs text-slate-700 border border-slate-200">
            <?php echo Security::sanitizeRichHtml($mail->body); ?>
          </div>
          <?php if (!empty($mail->error_message)): ?>
            <p class="text-[11px] text-red-600">Note: <?php echo Security::e($mail->error_message); ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
