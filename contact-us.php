<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\MailService;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send'])) {
    Csrf::requireValid();
    $name = trim((string) ($_POST['fullname'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $contactno = preg_replace('/\D+/', '', (string) ($_POST['contactno'] ?? '')) ?? '';
    $message = trim((string) ($_POST['message'] ?? ''));

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
        Session::setFlash('error', 'Please fill in all required contact fields accurately.');
    } else {
        $ins = $dbh->prepare('INSERT INTO tblcontactusquery (name, EmailId, ContactNumber, Message) VALUES (:name, :email, :phone, :msg)');
        $ins->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $contactno,
            ':msg' => $message,
        ]);

        $mailer = new MailService($dbh);
        $mailer->notifyAdmin(
            'New Contact Inquiry from ' . $name,
            '<p><strong>Name:</strong> ' . Security::e($name) . '</p>'
            . '<p><strong>Email:</strong> ' . Security::e($email) . '</p>'
            . '<p><strong>Phone:</strong> ' . Security::e($contactno) . '</p>'
            . '<p><strong>Message:</strong><br>' . nl2br(Security::e($message)) . '</p>',
            $email
        );

        Session::setFlash('success', 'Query sent! Our Guwahati support team will get back to you shortly.');
    }
    Security::redirect('contact-us.php');
}

$contactStmt = $dbh->query('SELECT Address, EmailId, ContactNo FROM tblcontactusinfo LIMIT 1');
$info = $contactStmt ? $contactStmt->fetch() : null;

$pageTitle = 'DriveX Assam | Contact Us';
$activeNav = 'contact';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-10 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <p class="text-xs font-bold uppercase tracking-wider text-sky-400">24/7 Customer Support</p>
    <h1 class="mt-1 text-3xl font-extrabold">Contact DriveX Assam</h1>
    <p class="mt-1 text-sm text-slate-300">Questions about long-term rentals, airport pickup, or fleet availability? Reach out below.</p>
  </div>
</section>

<section class="py-12 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-6">
        <div class="card p-6 sm:p-8">
          <h2 class="text-xl font-extrabold text-slate-950 border-b border-slate-100 pb-3 mb-5">Send Us a Message</h2>
          <form method="post" class="space-y-4">
            <?php echo Csrf::field(); ?>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Full Name *</label>
              <input type="text" name="fullname" required class="form-input" placeholder="Your full name">
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Email Address *</label>
                <input type="email" name="email" required class="form-input" placeholder="you@example.com">
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Phone Number *</label>
                <input type="text" name="contactno" required maxlength="10" pattern="[0-9]{10}" class="form-input" placeholder="10-digit mobile">
              </div>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Message *</label>
              <textarea name="message" rows="4" required class="form-input" placeholder="Tell us about your travel dates, destination, or inquiry..."></textarea>
            </div>
            <button type="submit" name="send" class="btn-red w-full sm:w-auto">Send Message</button>
          </form>
        </div>
      </div>

      <div class="lg:col-span-6 space-y-6">
        <div class="card p-6 space-y-4">
          <h2 class="text-xl font-extrabold text-slate-950 border-b border-slate-100 pb-3">Office &amp; Support Details</h2>
          <div class="space-y-3 text-sm">
            <div class="flex items-start gap-3">
              <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">Address</span>
              <span class="text-slate-700"><?php echo Security::e($info->Address ?? 'GS Road, Guwahati, Assam'); ?></span>
            </div>
            <div class="flex items-start gap-3">
              <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">Phone</span>
              <a href="tel:<?php echo Security::e($info->ContactNo ?? ''); ?>" class="font-semibold text-slate-900 hover:text-blue-700">
                <?php echo Security::e($info->ContactNo ?? '+91 9876543210'); ?>
              </a>
            </div>
            <div class="flex items-start gap-3">
              <span class="rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-bold text-blue-700">Email</span>
              <a href="mailto:<?php echo Security::e($info->EmailId ?? ''); ?>" class="font-semibold text-slate-900 hover:text-blue-700">
                <?php echo Security::e($info->EmailId ?? 'support@drivexassam.com'); ?>
              </a>
            </div>
          </div>
        </div>

        <div class="card overflow-hidden">
          <div class="border-b border-slate-100 px-5 py-3 text-sm font-bold text-slate-950">Find Us on Map</div>
          <div class="aspect-[16/10] w-full bg-slate-100">
            <iframe
              src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3564.5028488546827!2d92.8324141!3d26.6963781!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3744ebbb5c5463f7%3A0x418fff7737cff274!2sDriveX!5e0!3m2!1sen!2sin!4v1748452177173!5m2!1sen!2sin"
              class="h-full w-full border-0" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
