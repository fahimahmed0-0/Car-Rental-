<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

$pagetype = trim((string) ($_GET['type'] ?? 'aboutus'));
$stmt = $dbh->prepare('SELECT type, detail, PageName FROM tblpages WHERE type = :t LIMIT 1');
$stmt->execute([':t' => $pagetype]);
$page = $stmt->fetch();

$activeNav = match ($pagetype) {
    'aboutus' => 'about',
    'faqs' => 'faqs',
    default => '',
};
$pageTitle = 'DriveX Assam | ' . ($page->PageName ?? 'Information');
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-10 border-b-4 border-blue-700">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="text-xs text-slate-400">
      <a href="index.php" class="hover:text-sky-300">Home</a> / <span class="text-sky-300"><?php echo Security::e($page->PageName ?? 'Page'); ?></span>
    </div>
    <h1 class="mt-1 text-3xl font-extrabold"><?php echo Security::e($page->PageName ?? 'Page Not Found'); ?></h1>
  </div>
</section>

<section class="py-12 flex-1">
  <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
    <div class="card p-8 prose prose-slate max-w-none text-sm leading-relaxed text-slate-700 space-y-4">
      <?php if ($page): ?>
        <?php echo Security::sanitizeRichHtml((string) $page->detail); ?>
      <?php else: ?>
        <p>The requested information page has not been published yet. Please check back soon or <a href="contact-us.php" class="font-bold text-blue-700 underline">contact us</a>.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
