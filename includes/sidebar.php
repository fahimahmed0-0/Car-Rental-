<?php

declare(strict_types=1);

use App\Core\Security;

$currentScript = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$accountLinks = [
    ['file' => 'my-booking.php', 'label' => 'My Bookings'],
    ['file' => 'payment-history.php', 'label' => 'Payment History'],
    ['file' => 'my-tokens.php', 'label' => 'Loyalty Tokens'],
    ['file' => 'profile.php', 'label' => 'Profile Settings'],
    ['file' => 'update-password.php', 'label' => 'Update Password'],
    ['file' => 'post-testimonial.php', 'label' => 'Post a Testimonial'],
    ['file' => 'my-testimonials.php', 'label' => 'My Testimonials'],
];
?>
<aside class="card p-4">
  <h2 class="mb-3 px-2 text-xs font-bold uppercase tracking-wider text-slate-500">Account Menu</h2>
  <nav class="space-y-1">
    <?php foreach ($accountLinks as $link):
      $active = $currentScript === $link['file'];
    ?>
      <a href="<?php echo Security::e($link['file']); ?>"
         class="block rounded-lg px-3 py-2.5 text-sm font-semibold transition <?php echo $active ? 'bg-blue-700 text-white shadow-sm' : 'text-slate-700 hover:bg-blue-50 hover:text-blue-700'; ?>">
        <?php echo Security::e($link['label']); ?>
      </a>
    <?php endforeach; ?>
    <div class="my-2 border-t border-slate-200"></div>
    <a href="logout.php" class="block rounded-lg px-3 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-50 transition">
      Sign Out
    </a>
  </nav>
</aside>
