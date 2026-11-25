<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\DemoDatabase;
use App\Core\Security;
?>
<div id="loginform" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
  <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
      <div>
        <h3 class="text-lg font-bold text-slate-950">Sign In to DriveX</h3>
        <p class="text-xs text-slate-500">Access your bookings, payments &amp; loyalty tokens</p>
      </div>
      <button type="button" onclick="window.closeAuthModals()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">&times;</button>
    </div>

    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Email or Phone Number</label>
        <input type="text" name="login_id" required class="form-input" placeholder="you@example.com or 9876543210">
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Password</label>
        <input type="password" name="password" required class="form-input" placeholder="Enter your password">
      </div>
      <div class="flex items-center justify-between text-xs">
        <span class="text-slate-500">Protected by rate limiting</span>
        <button type="button" onclick="window.openAuthModal('forgot')" class="font-semibold text-blue-700 hover:underline">Forgot Password?</button>
      </div>
      <button type="submit" name="auth_login" class="btn-primary w-full">Sign In</button>
    </form>

    <?php if (Database::isDemoMode()): ?>
      <div class="mt-4 rounded-xl border border-sky-200 bg-sky-50 p-3.5 text-xs">
        <div class="flex items-center justify-between gap-2">
          <div>
            <p class="font-bold text-slate-900">Demo Account</p>
            <p class="mt-0.5 text-slate-600">
              Email: <code class="font-mono text-slate-900"><?php echo Security::e(DemoDatabase::DEMO_CUSTOMER_EMAIL); ?></code>
              &bull; Pass: <code class="font-mono text-slate-900"><?php echo Security::e(DemoDatabase::DEMO_CUSTOMER_PASS); ?></code>
            </p>
          </div>
          <button type="button"
                  onclick="var f=this.closest('#loginform').querySelector('form');f.querySelector('[name=login_id]').value='<?php echo Security::e(DemoDatabase::DEMO_CUSTOMER_EMAIL); ?>';f.querySelector('[name=password]').value='<?php echo Security::e(DemoDatabase::DEMO_CUSTOMER_PASS); ?>';f.querySelector('[name=auth_login]').click();"
                  class="shrink-0 rounded-lg bg-blue-700 px-3 py-1.5 font-semibold text-white hover:bg-blue-800 transition">
            Demo Sign In
          </button>
        </div>
      </div>
    <?php endif; ?>

    <div class="mt-5 border-t border-slate-100 pt-4 text-center text-xs text-slate-600">
      Don't have an account?
      <button type="button" onclick="window.openAuthModal('signup')" class="font-bold text-red-600 hover:underline">Create Account</button>
    </div>
  </div>
</div>
