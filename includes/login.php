<?php

declare(strict_types=1);

use App\Core\Csrf;
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

    <div class="mt-5 border-t border-slate-100 pt-4 text-center text-xs text-slate-600">
      Don't have an account?
      <button type="button" onclick="window.openAuthModal('signup')" class="font-bold text-red-600 hover:underline">Create Account</button>
    </div>
  </div>
</div>
