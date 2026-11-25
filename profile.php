<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

if (empty($_SESSION['login'])) {
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateprofile'])) {
    Csrf::requireValid();
    $name = trim((string) ($_POST['fullname'] ?? ''));
    $mobile = preg_replace('/\D+/', '', (string) ($_POST['mobilenumber'] ?? '')) ?? '';
    $dob = trim((string) ($_POST['dob'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $city = trim((string) ($_POST['city'] ?? ''));
    $country = trim((string) ($_POST['country'] ?? ''));

    $upd = $dbh->prepare(
        'UPDATE tblusers SET FullName = :name, ContactNo = :mobile, dob = :dob, Address = :addr, City = :city, Country = :country WHERE EmailId = :email'
    );
    $upd->execute([
        ':name' => $name,
        ':mobile' => $mobile,
        ':dob' => $dob,
        ':addr' => $address,
        ':city' => $city,
        ':country' => $country,
        ':email' => $userEmail,
    ]);
    $_SESSION['fname'] = $name;
    Session::setFlash('success', 'Profile updated successfully.');
    Security::redirect('profile.php');
}

$stmt = $dbh->prepare('SELECT * FROM tblusers WHERE EmailId = :email LIMIT 1');
$stmt->execute([':email' => $userEmail]);
$user = $stmt->fetch();

$pageTitle = 'DriveX Assam | Profile Settings';
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-8 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <h1 class="text-2xl font-extrabold">Profile Settings</h1>
    <p class="text-xs text-slate-300 mt-1">Keep your contact details up to date for smooth vehicle handover.</p>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <div class="lg:col-span-3">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>
      </div>
      <div class="lg:col-span-9">
        <div class="card p-6 sm:p-8">
          <div class="border-b border-slate-100 pb-4 mb-6 flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="text-lg font-extrabold text-slate-950">Personal Information</h2>
              <p class="text-xs text-slate-500">Member since <?php echo Security::e($user->RegDate ?? ''); ?></p>
            </div>
            <?php if (!empty($user->UpdationDate)): ?>
              <span class="text-xs text-slate-500">Last updated: <?php echo Security::e($user->UpdationDate); ?></span>
            <?php endif; ?>
          </div>

          <form method="post" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <?php echo Csrf::field(); ?>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Full Name</label>
              <input type="text" name="fullname" required value="<?php echo Security::e($user->FullName ?? ''); ?>" class="form-input">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Email Address</label>
              <input type="email" value="<?php echo Security::e($user->EmailId ?? ''); ?>" readonly class="form-input bg-slate-100 text-slate-500 cursor-not-allowed">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Phone Number</label>
              <input type="text" name="mobilenumber" required value="<?php echo Security::e($user->ContactNo ?? ''); ?>" class="form-input">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Date of Birth</label>
              <input type="text" name="dob" placeholder="dd/mm/yyyy" value="<?php echo Security::e($user->dob ?? ''); ?>" class="form-input">
            </div>
            <div class="sm:col-span-2">
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Address</label>
              <textarea name="address" rows="3" class="form-input"><?php echo Security::e($user->Address ?? ''); ?></textarea>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">City</label>
              <input type="text" name="city" value="<?php echo Security::e($user->City ?? ''); ?>" class="form-input">
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold uppercase text-slate-600">Country</label>
              <input type="text" name="country" value="<?php echo Security::e($user->Country ?? ''); ?>" class="form-input">
            </div>
            <div class="sm:col-span-2 pt-2">
              <button type="submit" name="updateprofile" class="btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
