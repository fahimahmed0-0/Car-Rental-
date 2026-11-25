<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $text = trim((string) ($_POST['testimonial'] ?? ''));
    if (strlen($text) < 10) {
        Session::setFlash('error', 'Please write at least 10 characters for your testimonial.');
    } else {
        // Basic heuristic pre-screen flag for admin moderation
        $flag = preg_match('/https?:\/\/|<script|viagra|casino|crypto/i', $text) ? 'flagged' : 'clean';
        $ins = $dbh->prepare('INSERT INTO tbltestimonial (UserEmail, Testimonial, AiModerationFlag) VALUES (:email, :txt, :flag)');
        $ins->execute([
            ':email' => (string) $_SESSION['login'],
            ':txt' => $text,
            ':flag' => $flag,
        ]);
        Session::setFlash('success', 'Thank you! Your testimonial has been submitted for approval.');
    }
    Security::redirect('my-testimonials.php');
}

$pageTitle = 'DriveX Assam | Post Testimonial';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">Post a Testimonial</h1>
    <p class="text-xs text-slate-300 mt-1">Share your rental experience with fellow travelers.</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9">
        <div class="card p-6 sm:p-8 max-w-2xl">
          <h2 class="text-lg font-extrabold text-slate-950 border-b border-slate-100 pb-3 mb-5">Your Review</h2>
          <form method="post" class="space-y-4">
            <?php echo Csrf::field(); ?>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Testimonial</label>
              <textarea name="testimonial" rows="5" required minlength="10" class="form-input" placeholder="How was the vehicle condition, pickup experience, and trip?"></textarea>
            </div>
            <button type="submit" name="submit" class="btn-primary">Submit Testimonial</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
