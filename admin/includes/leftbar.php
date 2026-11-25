<?php

declare(strict_types=1);

use App\Core\Security;

$links = [
    ['id' => 'dashboard', 'label' => 'Dashboard', 'href' => 'dashboard.php', 'group' => 'Overview'],
    ['id' => 'bookings', 'label' => 'All / Pending Bookings', 'href' => 'manage-bookings.php', 'group' => 'Bookings'],
    ['id' => 'confirmed', 'label' => 'Confirmed Bookings', 'href' => 'confirmed-bookings.php', 'group' => 'Bookings'],
    ['id' => 'completed', 'label' => 'Completed Bookings', 'href' => 'completed-bookings.php', 'group' => 'Bookings'],
    ['id' => 'canceled', 'label' => 'Cancelled Bookings', 'href' => 'canceled-bookings.php', 'group' => 'Bookings'],
    ['id' => 'vehicles', 'label' => 'Manage Vehicles', 'href' => 'manage-vehicles.php', 'group' => 'Fleet'],
    ['id' => 'post-vehicle', 'label' => 'Post a Vehicle', 'href' => 'post-avehical.php', 'group' => 'Fleet'],
    ['id' => 'brands', 'label' => 'Manage Brands', 'href' => 'manage-brands.php', 'group' => 'Fleet'],
    ['id' => 'create-brand', 'label' => 'Create Brand', 'href' => 'create-brand.php', 'group' => 'Fleet'],
    ['id' => 'tokens', 'label' => 'Loyalty Tokens', 'href' => 'token-management.php', 'group' => 'Operations'],
    ['id' => 'users', 'label' => 'Registered Users', 'href' => 'reg-users.php', 'group' => 'Operations'],
    ['id' => 'queries', 'label' => 'Contact Queries + AI', 'href' => 'manage-conactusquery.php', 'group' => 'Operations'],
    ['id' => 'testimonials', 'label' => 'Testimonials + AI Flag', 'href' => 'testimonials.php', 'group' => 'Operations'],
    ['id' => 'outbox', 'label' => 'Mail Outbox (Fallback)', 'href' => 'mail-outbox.php', 'group' => 'Operations'],
    ['id' => 'pages', 'label' => 'Manage Pages', 'href' => 'manage-pages.php', 'group' => 'Settings'],
    ['id' => 'contactinfo', 'label' => 'Update Contact Info', 'href' => 'update-contactinfo.php', 'group' => 'Settings'],
    ['id' => 'subscribers', 'label' => 'Subscribers', 'href' => 'manage-subscribers.php', 'group' => 'Settings'],
];

$grouped = [];
foreach ($links as $l) {
    $grouped[$l['group']][] = $l;
}
?>
<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
       class="fixed inset-y-16 left-0 z-30 w-64 bg-slate-900 text-slate-200 border-r border-slate-800 overflow-y-auto transition-transform lg:static lg:inset-auto">
  <div class="p-4 space-y-5">
    <?php foreach ($grouped as $groupName => $items): ?>
      <div>
        <div class="px-3 mb-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400"><?php echo Security::e($groupName); ?></div>
        <div class="space-y-0.5">
          <?php foreach ($items as $item):
            $isActive = ($adminNav === $item['id']);
          ?>
            <a href="<?php echo Security::e($item['href']); ?>"
               class="block rounded-lg px-3 py-2 text-xs font-semibold transition <?php echo $isActive ? 'bg-blue-700 text-white' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>">
              <?php echo Security::e($item['label']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</aside>
