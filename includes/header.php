<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Security;
use App\Services\TokenLedgerService;

$contactStmt = $dbh->query('SELECT EmailId, ContactNo, Address FROM tblcontactusinfo LIMIT 1');
$contactInfo = $contactStmt ? $contactStmt->fetch() : null;
$supportEmail = $contactInfo->EmailId ?? 'support@drivexassam.com';
$supportPhone = $contactInfo->ContactNo ?? '+91 9876543210';

$loggedInEmail = (string) ($_SESSION['login'] ?? '');
$userFullName = '';
$userTokens = 0;

if ($loggedInEmail !== '') {
    $uStmt = $dbh->prepare('SELECT FullName FROM tblusers WHERE EmailId = :email LIMIT 1');
    $uStmt->execute([':email' => $loggedInEmail]);
    $userFullName = (string) ($uStmt->fetchColumn() ?: ($_SESSION['fname'] ?? 'Account'));
    $tokenSvc = new TokenLedgerService($dbh);
    $userTokens = $tokenSvc->getBalance($loggedInEmail)['balance'];
}

$navItems = [
    ['id' => 'home', 'label' => 'Home', 'href' => 'index.php'],
    ['id' => 'cars', 'label' => 'Fleet & Booking', 'href' => 'car-listing.php'],
    ['id' => 'about', 'label' => 'About Us', 'href' => 'page.php?type=aboutus'],
    ['id' => 'faqs', 'label' => 'FAQs', 'href' => 'page.php?type=faqs'],
    ['id' => 'contact', 'label' => 'Contact Us', 'href' => 'contact-us.php'],
];
?>
<!-- Top Utility Bar (Black) -->
<div class="bg-slate-950 text-slate-200 text-xs border-b border-slate-800">
  <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2 px-4 py-2 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center gap-4">
      <a href="mailto:<?php echo Security::e($supportEmail); ?>" class="inline-flex items-center gap-1.5 hover:text-sky-300 transition">
        <span class="h-2 w-2 rounded-full bg-red-600"></span>
        <span><?php echo Security::e($supportEmail); ?></span>
      </a>
      <a href="https://wa.me/<?php echo Security::e(preg_replace('/\D+/', '', $supportPhone)); ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 hover:text-sky-300 transition">
        <span class="font-semibold text-sky-400">24/7 Helpline:</span>
        <span><?php echo Security::e($supportPhone); ?></span>
      </a>
    </div>
    <div class="flex items-center gap-3 sm:gap-4">
      <?php if (Database::isDemoMode()): ?>
        <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-400/40 bg-sky-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-sky-200">
          <span class="h-1.5 w-1.5 rounded-full bg-sky-400"></span>
          Portfolio Demo Mode
        </span>
      <?php endif; ?>
      <span class="hidden sm:inline text-slate-400">Guwahati, Assam • Verified Fleet</span>
      <a href="admin/index.php" class="text-slate-300 hover:text-white font-medium transition">Admin Portal</a>
    </div>
  </div>
</div>

<!-- Main Sticky Navigation (White + Blue + Red) -->
<header class="sticky top-0 z-40 border-b border-slate-200 bg-white shadow-sm" x-data="{ mobileOpen: false, userMenu: false }">
  <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
    <!-- Brand -->
    <a href="index.php" class="flex items-center gap-3">
      <img src="assets/images/logo_02.png" alt="DriveX Assam" class="h-10 w-auto object-contain">
      <div class="leading-tight">
        <span class="block text-lg font-extrabold tracking-tight text-slate-950">Drive<span class="text-red-600">X</span> <span class="text-blue-700">Assam</span></span>
        <span class="block text-[11px] font-medium uppercase tracking-wider text-slate-500">Premium Car Rentals</span>
      </div>
    </a>

    <!-- Desktop Links -->
    <nav class="hidden lg:flex items-center gap-1">
      <?php foreach ($navItems as $item):
        $isActive = ($activeNav === $item['id']);
      ?>
        <a href="<?php echo Security::e($item['href']); ?>"
           class="rounded-lg px-3.5 py-2 text-sm font-semibold transition <?php echo $isActive ? 'bg-blue-50 text-blue-700' : 'text-slate-700 hover:bg-slate-100 hover:text-slate-950'; ?>">
          <?php echo Security::e($item['label']); ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <!-- Right Actions -->
    <div class="flex items-center gap-2.5">
      <!-- Quick Search -->
      <form action="car-listing.php" method="get" class="hidden md:flex items-center rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1.5 focus-within:border-blue-600 focus-within:bg-white">
        <input type="text" name="q" value="<?php echo Security::e($_GET['q'] ?? $_POST['searchdata'] ?? ''); ?>" placeholder="Search car or brand..." class="w-40 bg-transparent text-xs text-slate-900 placeholder-slate-400 focus:outline-none">
        <button type="submit" class="text-blue-700 hover:text-blue-900 text-xs font-bold px-1" aria-label="Search">Go</button>
      </form>

      <?php if ($loggedInEmail === ''): ?>
        <button type="button" onclick="window.openAuthModal('login')" class="btn-outline !py-2 !px-3.5 !text-xs sm:!text-sm">Sign In</button>
        <button type="button" onclick="window.openAuthModal('signup')" class="btn-red !py-2 !px-3.5 !text-xs sm:!text-sm">Register</button>
      <?php else: ?>
        <a href="my-tokens.php" class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-sky-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-800 hover:bg-blue-100 transition">
          <span class="h-2 w-2 rounded-full bg-blue-600"></span>
          <?php echo (int) $userTokens; ?> Tokens
        </a>

        <div class="relative">
          <button type="button" @click="userMenu = !userMenu" @click.away="userMenu = false"
                  class="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-900 hover:border-blue-600 transition">
            <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-700 text-xs font-bold text-white">
              <?php echo Security::e(strtoupper(substr($userFullName, 0, 1))); ?>
            </span>
            <span class="hidden sm:inline max-w-[120px] truncate"><?php echo Security::e($userFullName); ?></span>
            <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </button>

          <div x-show="userMenu" x-transition style="display:none;"
               class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg z-50">
            <div class="border-b border-slate-100 px-4 py-2">
              <p class="text-xs font-semibold text-slate-500">Signed in as</p>
              <p class="truncate text-xs font-bold text-slate-900"><?php echo Security::e($loggedInEmail); ?></p>
            </div>
            <a href="my-booking.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">My Bookings</a>
            <a href="payment-history.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">Payment History</a>
            <a href="my-tokens.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">My Tokens (<?php echo (int) $userTokens; ?>)</a>
            <a href="profile.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">Profile Settings</a>
            <a href="update-password.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">Update Password</a>
            <a href="post-testimonial.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">Post Testimonial</a>
            <a href="my-testimonials.php" class="block px-4 py-2 text-sm text-slate-700 hover:bg-blue-50 hover:text-blue-700">My Testimonials</a>
            <div class="my-1 border-t border-slate-100"></div>
            <a href="logout.php" class="block px-4 py-2 text-sm font-semibold text-red-600 hover:bg-red-50">Sign Out</a>
          </div>
        </div>
      <?php endif; ?>

      <!-- Mobile Menu Toggle -->
      <button type="button" @click="mobileOpen = !mobileOpen" class="lg:hidden rounded-lg border border-slate-300 p-2 text-slate-700 hover:bg-slate-100" aria-label="Toggle Menu">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>
  </div>

  <!-- Mobile Navigation Drawer -->
  <div x-show="mobileOpen" x-transition style="display:none;" class="border-t border-slate-200 bg-white px-4 py-3 lg:hidden">
    <form action="car-listing.php" method="get" class="mb-3 flex gap-2">
      <input type="text" name="q" placeholder="Search cars..." class="form-input !py-2">
      <button type="submit" class="btn-primary !py-2 !px-4">Search</button>
    </form>
    <div class="space-y-1">
      <?php foreach ($navItems as $item): ?>
        <a href="<?php echo Security::e($item['href']); ?>" class="block rounded-lg px-3 py-2 text-sm font-semibold text-slate-800 hover:bg-blue-50 hover:text-blue-700">
          <?php echo Security::e($item['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</header>
