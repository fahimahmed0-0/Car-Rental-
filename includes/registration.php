<?php

declare(strict_types=1);

use App\Core\Csrf;
?>
<div id="signupform" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4" role="dialog" aria-modal="true">
  <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-xl">
    <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
      <div>
        <h3 class="text-lg font-bold text-slate-950">Create DriveX Account</h3>
        <p class="text-xs text-slate-500">Earn 5 loyalty tokens on every completed trip</p>
      </div>
      <button type="button" onclick="window.closeAuthModals()" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">&times;</button>
    </div>

    <form method="post" class="space-y-3.5" onsubmit="return this.password.value.length >= 8 || (alert('Password must be at least 8 characters.'), false)">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Full Name</label>
        <input type="text" name="fullname" required class="form-input" placeholder="Your full name">
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Mobile Number</label>
        <input type="text" name="mobileno" maxlength="10" pattern="[0-9]{10}" required class="form-input" placeholder="10-digit mobile number">
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Email Address</label>
        <input type="email" name="emailid" id="signup_emailid" required class="form-input" placeholder="you@example.com" onblur="window.checkSignupEmail(this.value)">
        <span id="signup-email-status" class="mt-1 block text-xs"></span>
      </div>
      <div>
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-600">Password (min 8 characters)</label>
        <input type="password" name="password" minlength="8" required class="form-input" placeholder="At least 8 characters">
      </div>
      <label class="flex items-start gap-2 text-xs text-slate-600">
        <input type="checkbox" required checked class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-blue-600">
        <span>I agree to the <a href="page.php?type=terms" class="font-semibold text-blue-700 hover:underline">Terms &amp; Conditions</a> and <a href="page.php?type=privacy" class="font-semibold text-blue-700 hover:underline">Privacy Policy</a>.</span>
      </label>
      <button type="submit" name="auth_signup" id="signup_submit_btn" class="btn-red w-full">Create Account</button>
    </form>

    <div class="mt-5 border-t border-slate-100 pt-4 text-center text-xs text-slate-600">
      Already have an account?
      <button type="button" onclick="window.openAuthModal('login')" class="font-bold text-blue-700 hover:underline">Sign In</button>
    </div>
  </div>
</div>

<script>
window.checkSignupEmail = async function(email) {
  var statusEl = document.getElementById('signup-email-status');
  var btn = document.getElementById('signup_submit_btn');
  if (!email || !statusEl) return;
  try {
    var res = await fetch('check_availability.php?format=json&emailid=' + encodeURIComponent(email), {
      headers: { 'Accept': 'application/json' }
    });
    var data = await res.json();
    statusEl.textContent = data.message || '';
    statusEl.className = 'mt-1 block text-xs font-semibold ' + (data.available ? 'text-blue-700' : 'text-red-600');
    if (btn) btn.disabled = !data.available;
  } catch (e) {}
};
</script>
