<?php

declare(strict_types=1);

use App\Core\Csrf;
?>
<footer class="mt-auto bg-slate-950 text-slate-300 border-t-4 border-red-600">
  <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
      <div>
        <div class="flex items-center gap-2.5 mb-3">
          <span class="text-xl font-extrabold text-white">Drive<span class="text-red-500">X</span> <span class="text-sky-400">Assam</span></span>
        </div>
        <p class="text-sm text-slate-400 leading-relaxed">
          Trusted self-drive and chauffeur car rentals across Guwahati and Northeast India. Transparent daily pricing, verified fleet, and loyalty token rewards on every completed journey.
        </p>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4">Quick Links</h3>
        <ul class="space-y-2 text-sm">
          <li><a href="car-listing.php" class="hover:text-sky-300 transition">Browse Fleet</a></li>
          <li><a href="page.php?type=aboutus" class="hover:text-sky-300 transition">About Us</a></li>
          <li><a href="page.php?type=faqs" class="hover:text-sky-300 transition">FAQs &amp; Token Rules</a></li>
          <li><a href="page.php?type=privacy" class="hover:text-sky-300 transition">Privacy Policy</a></li>
          <li><a href="page.php?type=terms" class="hover:text-sky-300 transition">Terms &amp; Conditions</a></li>
          <li><a href="contact-us.php" class="hover:text-sky-300 transition">Contact Support</a></li>
        </ul>
      </div>

      <div>
        <h3 class="text-sm font-bold uppercase tracking-wider text-white mb-4">Newsletter &amp; Connect</h3>
        <form method="post" class="flex gap-2 mb-4">
          <?php echo Csrf::field(); ?>
          <input type="email" name="subscriberemail" required placeholder="Enter email address"
                 class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3.5 py-2 text-sm text-white placeholder-slate-400 focus:border-sky-400 focus:outline-none">
          <button type="submit" name="emailsubscribe" class="btn-primary !bg-blue-600 hover:!bg-blue-700 shrink-0">Subscribe</button>
        </form>
        <p class="text-xs text-slate-400 mb-4">Seasonal offers and fleet updates delivered directly to your inbox.</p>
        <div class="flex items-center gap-3 text-xs font-semibold">
          <a href="https://www.facebook.com/p/Drivex-61575801312906/" target="_blank" rel="noopener" class="rounded-md bg-slate-900 border border-slate-800 px-3 py-1.5 text-slate-200 hover:border-sky-400 hover:text-sky-300">Facebook</a>
          <a href="https://www.instagram.com/drivex_assam/?locale=en-GB&hl=ar" target="_blank" rel="noopener" class="rounded-md bg-slate-900 border border-slate-800 px-3 py-1.5 text-slate-200 hover:border-red-500 hover:text-red-400">Instagram</a>
          <a href="admin/index.php" class="rounded-md bg-slate-900 border border-slate-800 px-3 py-1.5 text-slate-200 hover:border-blue-500 hover:text-blue-300">Admin</a>
        </div>
      </div>
    </div>

    <div class="mt-10 border-t border-slate-800 pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
      <p>&copy; <?php echo date('Y'); ?> DriveX Assam. All rights reserved.</p>
      <p>Guwahati, Assam &bull; Secure Payments &bull; Verified Vehicles</p>
    </div>
  </div>
</footer>

<?php include __DIR__ . '/login.php'; ?>
<?php include __DIR__ . '/registration.php'; ?>
<?php include __DIR__ . '/forgotpassword.php'; ?>

<script>
window.openAuthModal = function(type) {
  ['loginform', 'signupform', 'forgotpassword'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('hidden');
  });
  var map = { login: 'loginform', signup: 'signupform', forgot: 'forgotpassword' };
  var target = document.getElementById(map[type] || 'loginform');
  if (target) target.classList.remove('hidden');
};
window.closeAuthModals = function() {
  ['loginform', 'signupform', 'forgotpassword'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) el.classList.add('hidden');
  });
};
</script>
<?php if (file_exists(__DIR__ . '/ai_assistant_widget.php')) { include __DIR__ . '/ai_assistant_widget.php'; } ?>
</body>
</html>
