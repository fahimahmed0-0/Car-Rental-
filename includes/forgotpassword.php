<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Security;

$pendingResetEmail = (string) ($_SESSION['_reset_email_pending'] ?? '');
?>
<div id="forgotpassword" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
  <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
      <div>
        <h3 class="text-lg font-bold text-slate-950">Reset Password via OTP</h3>
        <p class="text-xs text-slate-500">Request a 6-digit verification code sent to your email</p>
      </div>
      <button type="button" onclick="window.closeAuthModals()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">&times;</button>
    </div>

    <!-- Step 1: Request Code -->
    <form method="post" class="space-y-3 border-b border-slate-100 pb-5 mb-5">
      <?php echo Csrf::field(); ?>
      <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Step 1: Registered Email</label>
      <div class="flex gap-2">
        <input type="email" name="reset_email" required value="<?php echo Security::e($pendingResetEmail); ?>" class="form-input" placeholder="you@example.com">
        <button type="submit" name="auth_request_otp" class="btn-outline shrink-0">Send OTP</button>
      </div>
    </form>

    <!-- Step 2: Verify Code & Set Password -->
    <form method="post" class="space-y-3">
      <?php echo Csrf::field(); ?>
      <label class="block text-xs font-semibold uppercase tracking-wide text-slate-600">Step 2: Enter Code &amp; New Password</label>
      <input type="email" name="reset_email" required value="<?php echo Security::e($pendingResetEmail); ?>" class="form-input" placeholder="Registered email">
      <input type="text" name="otp_code" maxlength="6" pattern="[0-9]{6}" required class="form-input" placeholder="6-digit OTP code">
      <input type="password" name="newpassword" minlength="8" required class="form-input" placeholder="New password (min 8 chars)">
      <input type="password" name="confirmpassword" minlength="8" required class="form-input" placeholder="Confirm new password">
      <button type="submit" name="auth_verify_otp" class="btn-primary w-full">Verify &amp; Reset Password</button>
    </form>

    <div class="mt-4 text-center text-xs">
      <button type="button" onclick="window.openAuthModal('login')" class="font-semibold text-blue-700 hover:underline">&larr; Back to Sign In</button>
    </div>
  </div>
</div>
