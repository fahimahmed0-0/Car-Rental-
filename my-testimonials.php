<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$stmt = $dbh->prepare('SELECT * FROM tbltestimonial WHERE UserEmail = :email ORDER BY id DESC');
$stmt->execute([':email' => (string) $_SESSION['login']]);
$items = $stmt->fetchAll();

$pageTitle = 'DriveX Assam | My Testimonials';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-extrabold">My Testimonials</h1>
      <p class="text-xs text-slate-300 mt-1">Reviews you have submitted for DriveX Assam.</p>
    </div>
    <a href="post-testimonial.php" class="btn-red !py-2 !text-xs">+ Write Review</a>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9 space-y-4">
        <?php if (empty($items)): ?>
          <div class="card p-10 text-center">
            <p class="text-sm text-slate-600">You haven't posted any testimonials yet.</p>
            <a href="post-testimonial.php" class="btn-primary mt-4">Post Your First Review</a>
          </div>
        <?php else: ?>
          <?php foreach ($items as $row): ?>
            <div class="card p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
              <div>
                <p class="text-sm text-slate-800">"<?php echo Security::e($row->Testimonial); ?>"</p>
                <p class="mt-2 text-xs text-slate-500">Posted: <?php echo Security::e($row->PostingDate); ?></p>
              </div>
              <span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold <?php echo (int) $row->status === 1 ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700'; ?>">
                <?php echo (int) $row->status === 1 ? 'Published' : 'Pending Approval'; ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
