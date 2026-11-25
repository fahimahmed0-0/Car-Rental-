<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;

$pagetype = trim((string) ($_GET['type'] ?? 'aboutus'));
$validTypes = ['aboutus' => 'About Us', 'privacy' => 'Privacy Policy', 'terms' => 'Terms and Conditions', 'faqs' => 'FAQs'];
if (!array_key_exists($pagetype, $validTypes)) {
    $pagetype = 'aboutus';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    Csrf::requireValid();
    $cleanHtml = Security::sanitizeRichHtml((string) ($_POST['pgedetails'] ?? ''));

    $chk = $dbh->prepare('SELECT id FROM tblpages WHERE type = :t LIMIT 1');
    $chk->execute([':t' => $pagetype]);
    if ($chk->fetch()) {
        $upd = $dbh->prepare('UPDATE tblpages SET detail = :d WHERE type = :t');
        $upd->execute([':d' => $cleanHtml, ':t' => $pagetype]);
    } else {
        $ins = $dbh->prepare('INSERT INTO tblpages (PageName, type, detail) VALUES (:n, :t, :d)');
        $ins->execute([':n' => $validTypes[$pagetype], ':t' => $pagetype, ':d' => $cleanHtml]);
    }

    Session::setFlash('success', "Page '{$validTypes[$pagetype]}' updated successfully.");
    Security::redirect('manage-pages.php?type=' . urlencode($pagetype));
}

$stmt = $dbh->prepare('SELECT detail FROM tblpages WHERE type = :t LIMIT 1');
$stmt->execute([':t' => $pagetype]);
$detail = (string) ($stmt->fetchColumn() ?: '');

$adminTitle = 'Manage CMS Pages';
$adminNav = 'pages';
include __DIR__ . '/includes/layout_head.php';
?>

<div class="max-w-3xl space-y-5">
  <h1 class="text-2xl font-extrabold text-slate-950">Manage CMS Pages</h1>

  <div class="flex flex-wrap gap-2">
    <?php foreach ($validTypes as $k => $lbl): ?>
      <a href="manage-pages.php?type=<?php echo $k; ?>"
         class="rounded-lg px-3.5 py-2 text-xs font-bold border transition <?php echo $pagetype === $k ? 'border-blue-700 bg-blue-700 text-white' : 'border-slate-300 bg-white text-slate-700 hover:border-blue-600'; ?>">
        <?php echo Security::e($lbl); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <div class="card p-6">
    <form method="post" class="space-y-4">
      <?php echo Csrf::field(); ?>
      <div>
        <label class="mb-1 block text-xs font-bold uppercase text-slate-600">
          <?php echo Security::e($validTypes[$pagetype]); ?> Content (Safe HTML supported: &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;, &lt;li&gt;)
        </label>
        <textarea name="pgedetails" rows="10" required class="form-input font-mono text-xs"><?php echo Security::e($detail); ?></textarea>
      </div>
      <button type="submit" name="submit" value="Update" class="btn-primary">Update Page</button>
    </form>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
