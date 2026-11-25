<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Services\BookingService;

$adminTitle = 'Dashboard';
$adminNav = 'dashboard';
include __DIR__ . '/includes/layout_head.php';

(new BookingService($dbh))->expireStalePendingLocks();

$regusers = (int) $dbh->query('SELECT COUNT(*) FROM tblusers')->fetchColumn();
$totalvehicle = (int) $dbh->query('SELECT COUNT(*) FROM tblvehicles')->fetchColumn();
$bookings = (int) $dbh->query('SELECT COUNT(*) FROM tblbooking')->fetchColumn();
$pendingBookings = (int) $dbh->query('SELECT COUNT(*) FROM tblbooking WHERE Status = 0')->fetchColumn();
$brands = (int) $dbh->query('SELECT COUNT(*) FROM tblbrands')->fetchColumn();
$subscribers = (int) $dbh->query('SELECT COUNT(*) FROM tblsubscribers')->fetchColumn();
$queries = (int) $dbh->query('SELECT COUNT(*) FROM tblcontactusquery')->fetchColumn();
$testimonials = (int) $dbh->query('SELECT COUNT(*) FROM tbltestimonial')->fetchColumn();
$totalRevenue = (float) $dbh->query("SELECT COALESCE(SUM(amount), 0) FROM tblpaymenttransactions WHERE payment_status = 'completed'")->fetchColumn();
?>

<div class="space-y-6">
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h1 class="text-2xl font-extrabold text-slate-950">Admin Command Center</h1>
      <p class="text-xs text-slate-500">Live fleet, booking, revenue, and AI performance overview</p>
    </div>
    <div class="flex gap-2">
      <a href="manage-bookings.php" class="btn-red !py-2 !text-xs">Pending Bookings (<?php echo $pendingBookings; ?>)</a>
      <a href="post-avehical.php" class="btn-primary !py-2 !text-xs">+ Add Vehicle</a>
    </div>
  </div>

  <!-- KPI Grid -->
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
    <a href="manage-bookings.php" class="card p-5 border-t-4 border-t-red-600 hover:border-red-600 transition">
      <div class="text-xs font-bold uppercase text-slate-500">Total Bookings</div>
      <div class="mt-2 text-3xl font-extrabold text-slate-950"><?php echo $bookings; ?></div>
      <div class="mt-1 text-xs font-semibold text-red-600"><?php echo $pendingBookings; ?> pending review &rarr;</div>
    </a>
    <div class="card p-5 border-t-4 border-t-blue-700">
      <div class="text-xs font-bold uppercase text-slate-500">Verified Revenue</div>
      <div class="mt-2 text-3xl font-extrabold text-blue-700">₹<?php echo number_format($totalRevenue, 0); ?></div>
      <div class="mt-1 text-xs text-slate-500">Completed transactions</div>
    </div>
    <a href="manage-vehicles.php" class="card p-5 border-t-4 border-t-slate-900 hover:border-slate-900 transition">
      <div class="text-xs font-bold uppercase text-slate-500">Listed Vehicles</div>
      <div class="mt-2 text-3xl font-extrabold text-slate-950"><?php echo $totalvehicle; ?></div>
      <div class="mt-1 text-xs text-slate-600">Across <?php echo $brands; ?> brands &rarr;</div>
    </a>
    <a href="reg-users.php" class="card p-5 border-t-4 border-t-sky-500 hover:border-sky-500 transition">
      <div class="text-xs font-bold uppercase text-slate-500">Registered Users</div>
      <div class="mt-2 text-3xl font-extrabold text-slate-950"><?php echo $regusers; ?></div>
      <div class="mt-1 text-xs text-slate-600"><?php echo $queries; ?> queries &bull; <?php echo $testimonials; ?> reviews &rarr;</div>
    </a>
  </div>

  <!-- Analytics Charts (Wired to real DB API) -->
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
    <div class="card p-6 lg:col-span-7">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 mb-4">Revenue Trend (Last 6 Months)</h2>
      <div class="h-64">
        <canvas id="revenueChart"></canvas>
      </div>
    </div>
    <div class="card p-6 lg:col-span-5">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 mb-4">Booking Status Breakdown</h2>
      <div class="h-64">
        <canvas id="statusChart"></canvas>
      </div>
    </div>
  </div>

  <!-- AI Dynamic Pricing Advisor + Secondary Stats -->
  <div class="grid grid-cols-1 gap-6 lg:grid-cols-12" x-data="pricingAdvisor()">
    <div class="card p-6 lg:col-span-8">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3 mb-4">
        <div>
          <h2 class="text-base font-extrabold text-slate-950">Fleet Utilization &amp; Pricing Advisor</h2>
          <p class="text-xs text-slate-500">Data-backed rate recommendations with deterministic SQL fallback</p>
        </div>
        <button type="button" @click="loadInsights()" :disabled="pricingLoading" class="btn-outline !py-1.5 !text-xs">
          <span x-show="!pricingLoading">Generate Insights</span>
          <span x-show="pricingLoading">Analyzing...</span>
        </button>
      </div>
      <div x-show="pricingMeta" x-text="pricingMeta" class="mb-3 text-[11px] font-semibold text-blue-700"></div>
      <template x-if="pricingTips.length === 0">
        <p class="text-xs text-slate-500">Click "Generate Insights" to analyze vehicle demand, idle inventory, and suggested seasonal rate adjustments.</p>
      </template>
      <div class="space-y-2.5">
        <template x-for="(tip, idx) in pricingTips" :key="idx">
          <div class="rounded-lg border border-sky-200 bg-blue-50/60 p-3 text-xs">
            <div class="font-bold text-slate-950" x-text="tip.title"></div>
            <div class="mt-0.5 text-slate-700" x-text="tip.detail"></div>
          </div>
        </template>
      </div>
    </div>

    <div class="card p-6 lg:col-span-4 space-y-3">
      <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 border-b border-slate-100 pb-2">Quick Operations</h2>
      <a href="manage-conactusquery.php" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-xs font-semibold hover:bg-blue-50">
        <span>Contact Inquiries</span><span class="font-extrabold text-blue-700"><?php echo $queries; ?></span>
      </a>
      <a href="testimonials.php" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-xs font-semibold hover:bg-blue-50">
        <span>Testimonials</span><span class="font-extrabold text-blue-700"><?php echo $testimonials; ?></span>
      </a>
      <a href="manage-subscribers.php" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-xs font-semibold hover:bg-blue-50">
        <span>Newsletter Subscribers</span><span class="font-extrabold text-blue-700"><?php echo $subscribers; ?></span>
      </a>
      <a href="mail-outbox.php" class="flex justify-between rounded-lg bg-slate-50 px-3 py-2.5 text-xs font-semibold hover:bg-blue-50">
        <span>Local Mail Outbox</span><span class="font-extrabold text-red-600">View &rarr;</span>
      </a>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
<script>
function pricingAdvisor() {
  return {
    pricingLoading: false,
    pricingTips: [],
    pricingMeta: '',
    async loadInsights() {
      this.pricingLoading = true;
      var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      try {
        var res = await fetch('../api/v1/ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({ action: 'pricing_insights' })
        });
        var data = await res.json();
        if (data.success) {
          this.pricingTips = data.insights || [];
          this.pricingMeta = 'Engine: ' + (data.tier_label || 'SQL Utilization Engine');
        }
      } catch (e) {}
      this.pricingLoading = false;
    }
  };
}

document.addEventListener('DOMContentLoaded', async function() {
  try {
    var res = await fetch('../api/v1/admin-analytics.php');
    var data = await res.json();
    if (!data.success) return;

    var labels = (data.monthly || []).map(function(m) { return m.label; });
    var revenues = (data.monthly || []).map(function(m) { return Number(m.revenue); });
    if (labels.length === 0) {
      labels = ['No Data'];
      revenues = [0];
    }

    var revCtx = document.getElementById('revenueChart');
    if (revCtx && typeof Chart !== 'undefined') {
      new Chart(revCtx, {
        type: 'bar',
        data: {
          labels: labels,
          datasets: [{ label: 'Revenue (₹)', data: revenues, backgroundColor: '#1d4ed8', borderRadius: 6 }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }

    var sc = data.status_counts || {};
    var stCtx = document.getElementById('statusChart');
    if (stCtx && typeof Chart !== 'undefined') {
      new Chart(stCtx, {
        type: 'doughnut',
        data: {
          labels: ['Pending', 'Confirmed', 'Active', 'Completed', 'Cancelled', 'Expired'],
          datasets: [{
            data: [sc.pending || 0, sc.confirmed || 0, sc.active || 0, sc.completed || 0, sc.cancelled || 0, sc.expired || 0],
            backgroundColor: ['#38bdf8', '#1d4ed8', '#0f172a', '#2563eb', '#dc2626', '#94a3b8']
          }]
        },
        options: { responsive: true, maintainAspectRatio: false }
      });
    }
  } catch (e) {}
});
</script>

<?php include __DIR__ . '/includes/layout_foot.php'; ?>
