<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Security;
use App\Services\BookingService;

$pageTitle = 'DriveX Assam | Fleet & Smart Search';
$activeNav = 'cars';

include __DIR__ . '/includes/layout_head.php';

$bookingSvc = new BookingService($dbh);
$bookingSvc->expireStalePendingLocks();

$q = trim((string) ($_GET['q'] ?? $_POST['searchdata'] ?? ''));
$brand = isset($_REQUEST['brand']) && $_REQUEST['brand'] !== '' && is_numeric($_REQUEST['brand']) ? (int) $_REQUEST['brand'] : '';
$fuel = trim((string) ($_REQUEST['fueltype'] ?? $_GET['fuel'] ?? ''));
$transmission = trim((string) ($_GET['transmission'] ?? ''));
$seats = isset($_GET['seats']) && $_GET['seats'] !== '' ? (int) $_GET['seats'] : '';
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (int) $_GET['max_price'] : '';
$fromDate = trim((string) ($_GET['from_date'] ?? ''));
$toDate = trim((string) ($_GET['to_date'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$aiOpen = isset($_GET['ai']) && $_GET['ai'] === '1';

$brands = $dbh->query('SELECT id, BrandName FROM tblbrands ORDER BY BrandName ASC')->fetchAll();
$today = date('Y-m-d');
?>

<section class="bg-slate-950 text-white py-10 border-b-4 border-blue-700">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
    <div>
      <p class="text-xs font-bold uppercase tracking-wider text-sky-400">DriveX Assam Fleet</p>
      <h1 class="mt-1 text-2xl font-extrabold sm:text-3xl">Explore Available Vehicles</h1>
      <p class="mt-1 text-sm text-slate-300">Filter by brand, fuel type, seats, budget, or live trip dates.</p>
    </div>
    <button type="button" onclick="window.dispatchEvent(new CustomEvent('toggle-ai-finder'))"
            class="btn-primary !bg-blue-600 hover:!bg-blue-700 shrink-0">
      Smart Trip Car Finder
    </button>
  </div>
</section>

<section class="py-10 flex-1"
         x-data="fleetCatalog({
           q: <?php echo Security::e(json_encode($q)); ?>,
           brand: <?php echo Security::e(json_encode((string) $brand)); ?>,
           fuel: <?php echo Security::e(json_encode($fuel)); ?>,
           transmission: <?php echo Security::e(json_encode($transmission)); ?>,
           seats: <?php echo Security::e(json_encode((string) $seats)); ?>,
           maxPrice: <?php echo Security::e(json_encode((string) $maxPrice)); ?>,
           fromDate: <?php echo Security::e(json_encode($fromDate)); ?>,
           toDate: <?php echo Security::e(json_encode($toDate)); ?>,
           sort: <?php echo Security::e(json_encode($sort)); ?>,
           aiOpen: <?php echo $aiOpen ? 'true' : 'false'; ?>
         })"
         @toggle-ai-finder.window="aiOpen = !aiOpen">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

    <!-- Smart Trip Car Finder -->
    <div x-show="aiOpen" x-transition style="display:none;" class="mb-6 rounded-xl border border-sky-200 bg-blue-50/70 p-5 shadow-sm">
      <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <div>
          <h2 class="text-base font-bold text-slate-950">Smart Trip Car Finder</h2>
          <p class="text-xs text-slate-600">Describe your trip in plain English — verified directly against live fleet availability.</p>
        </div>
        <span x-show="aiMeta" x-text="aiMeta" class="rounded-full border border-sky-200 bg-white px-3 py-1 text-[11px] font-semibold text-blue-800"></span>
      </div>
      <form @submit.prevent="runAiFinder()" class="flex flex-col sm:flex-row gap-2.5">
        <input type="text" x-model="aiPrompt" placeholder="e.g. 7-seater diesel SUV under ₹4500/day for a Shillong family trip" class="form-input bg-white">
        <button type="submit" :disabled="aiLoading" class="btn-primary shrink-0">
          <span x-show="!aiLoading">Match Vehicles</span>
          <span x-show="aiLoading">Analyzing...</span>
        </button>
      </form>
      <div x-show="aiExplanation" class="mt-3 rounded-lg border border-blue-200 bg-white px-4 py-2.5 text-xs text-slate-700" x-text="aiExplanation"></div>
    </div>

    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12">
      <!-- Filter Sidebar -->
      <aside class="lg:col-span-3">
        <div class="card p-5 sticky top-24 space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-950">Filter Fleet</h2>
            <button type="button" @click="resetFilters()" class="text-xs font-bold text-red-600 hover:underline">Reset</button>
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Keyword</label>
            <input type="text" x-model.debounce.300ms="filters.q" @input="fetchCars()" class="form-input" placeholder="Title, brand, year...">
          </div>

          <div>
            <label class="mb-1 block text-xs font-semibold text-slate-600">Brand</label>
            <select x-model="filters.brand" @change="fetchCars()" class="form-input">
              <option value="">All Brands</option>
              <?php foreach ($brands as $b): ?>
                <option value="<?php echo (int) $b->id; ?>"><?php echo Security::e($b->BrandName); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Fuel</label>
              <select x-model="filters.fuel" @change="fetchCars()" class="form-input">
                <option value="">Any</option>
                <option value="Petrol">Petrol</option>
                <option value="Diesel">Diesel</option>
                <option value="CNG">CNG</option>
                <option value="Electric">Electric</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Gearbox</label>
              <select x-model="filters.transmission" @change="fetchCars()" class="form-input">
                <option value="">Any</option>
                <option value="Manual">Manual</option>
                <option value="Automatic">Automatic</option>
              </select>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Min Seats</label>
              <select x-model="filters.seats" @change="fetchCars()" class="form-input">
                <option value="">Any</option>
                <option value="4">4+</option>
                <option value="5">5+</option>
                <option value="7">7+</option>
              </select>
            </div>
            <div>
              <label class="mb-1 block text-xs font-semibold text-slate-600">Max ₹/Day</label>
              <input type="number" x-model.debounce.300ms="filters.maxPrice" @input="fetchCars()" min="500" step="200" class="form-input" placeholder="e.g. 3500">
            </div>
          </div>

          <div class="border-t border-slate-100 pt-3 space-y-2">
            <label class="block text-xs font-bold uppercase tracking-wider text-blue-800">Check Date Availability</label>
            <input type="date" x-model="filters.fromDate" @change="fetchCars()" min="<?php echo $today; ?>" class="form-input">
            <input type="date" x-model="filters.toDate" @change="fetchCars()" :min="filters.fromDate || '<?php echo $today; ?>'" class="form-input">
          </div>
        </div>
      </aside>

      <!-- Results Grid -->
      <div class="lg:col-span-9">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
          <div class="text-sm font-semibold text-slate-800">
            <span class="text-blue-700 font-extrabold" x-text="vehicles.length">0</span> vehicles found
            <span x-show="loading" class="ml-2 text-xs text-slate-500">Updating...</span>
          </div>
          <div class="flex items-center gap-2">
            <label class="text-xs font-semibold text-slate-600">Sort:</label>
            <select x-model="filters.sort" @change="fetchCars()" class="form-input !w-auto !py-1.5 !text-xs">
              <option value="newest">Newest Listed</option>
              <option value="price_asc">Price: Low to High</option>
              <option value="price_desc">Price: High to Low</option>
              <option value="year_desc">Model Year: Newest</option>
            </select>
          </div>
        </div>

        <template x-if="!loading && vehicles.length === 0">
          <div class="card p-12 text-center">
            <h3 class="text-lg font-bold text-slate-900">No Matching Vehicles</h3>
            <p class="mt-1 text-sm text-slate-600">Try clearing one of your filters or adjusting your daily budget.</p>
            <button type="button" @click="resetFilters()" class="btn-primary mt-4">Reset All Filters</button>
          </div>
        </template>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
          <template x-for="car in vehicles" :key="car.id">
            <article class="card overflow-hidden flex flex-col transition hover:border-blue-500 hover:shadow-md">
              <a :href="'vehicle-details.php?vhid=' + car.id + dateQuery()" class="relative block h-48 bg-slate-100">
                <img :src="(car.Vimage1 && (car.Vimage1.startsWith('http://') || car.Vimage1.startsWith('https://'))) ? car.Vimage1 : ('admin/img/vehicleimages/' + (car.Vimage1 || 'car_755x430.png'))" :alt="car.VehiclesTitle" class="h-full w-full object-cover">
                <span class="absolute top-3 left-3 rounded-md bg-slate-950/85 px-2.5 py-1 text-xs font-bold text-white" x-text="car.BrandName"></span>
                <template x-if="filters.fromDate && filters.toDate">
                  <span class="absolute top-3 right-3 rounded-md px-2.5 py-1 text-xs font-bold text-white"
                        :class="Number(car.is_available) === 1 ? 'bg-blue-700' : 'bg-red-600'"
                        x-text="Number(car.is_available) === 1 ? 'Available' : 'Booked'"></span>
                </template>
              </a>
              <div class="flex flex-1 flex-col p-4">
                <div class="flex items-start justify-between gap-2">
                  <h3 class="text-base font-bold text-slate-950">
                    <a :href="'vehicle-details.php?vhid=' + car.id + dateQuery()" class="hover:text-blue-700" x-text="car.VehiclesTitle"></a>
                  </h3>
                  <div class="text-right shrink-0">
                    <span class="block text-base font-extrabold text-red-600" x-text="'₹' + Number(car.PricePerDay).toLocaleString('en-IN')"></span>
                    <span class="block text-[10px] text-slate-500">/day</span>
                  </div>
                </div>

                <template x-if="car.ai_reason">
                  <p class="mt-2 rounded-md border border-sky-200 bg-blue-50 px-2.5 py-1.5 text-[11px] font-medium text-blue-900" x-text="car.ai_reason"></p>
                </template>

                <div class="mt-3 grid grid-cols-3 gap-1.5 text-center text-[11px] font-semibold text-slate-700">
                  <div class="rounded bg-slate-100 py-1" x-text="car.FuelType"></div>
                  <div class="rounded bg-slate-100 py-1" x-text="car.SeatingCapacity + ' Seats'"></div>
                  <div class="rounded bg-slate-100 py-1" x-text="car.Transmission || 'Manual'"></div>
                </div>

                <div class="mt-4 pt-2">
                  <a :href="'vehicle-details.php?vhid=' + car.id + dateQuery()"
                     class="w-full"
                     :class="(filters.fromDate && filters.toDate && Number(car.is_available) === 0) ? 'btn-outline w-full' : 'btn-primary w-full'">
                    <span x-text="(filters.fromDate && filters.toDate && Number(car.is_available) === 0) ? 'Check Other Dates' : 'View Details & Book'"></span>
                  </a>
                </div>
              </div>
            </article>
          </template>
        </div>
      </div>
    </div>
  </div>
</section>

<script>
function fleetCatalog(initial) {
  return {
    filters: {
      q: initial.q || '',
      brand: initial.brand || '',
      fuel: initial.fuel || '',
      transmission: initial.transmission || '',
      seats: initial.seats || '',
      maxPrice: initial.maxPrice || '',
      fromDate: initial.fromDate || '',
      toDate: initial.toDate || '',
      sort: initial.sort || 'newest'
    },
    vehicles: [],
    loading: true,
    aiOpen: Boolean(initial.aiOpen),
    aiPrompt: initial.q || '',
    aiLoading: false,
    aiExplanation: '',
    aiMeta: '',
    init() {
      this.fetchCars();
    },
    dateQuery() {
      if (this.filters.fromDate && this.filters.toDate) {
        return '&from_date=' + encodeURIComponent(this.filters.fromDate) + '&to_date=' + encodeURIComponent(this.filters.toDate);
      }
      return '';
    },
    async fetchCars() {
      this.loading = true;
      var params = new URLSearchParams();
      if (this.filters.q) params.set('q', this.filters.q);
      if (this.filters.brand) params.set('brand', this.filters.brand);
      if (this.filters.fuel) params.set('fuel', this.filters.fuel);
      if (this.filters.transmission) params.set('transmission', this.filters.transmission);
      if (this.filters.seats) params.set('seats', this.filters.seats);
      if (this.filters.maxPrice) params.set('max_price', this.filters.maxPrice);
      if (this.filters.fromDate) params.set('from_date', this.filters.fromDate);
      if (this.filters.toDate) params.set('to_date', this.filters.toDate);
      params.set('sort', this.filters.sort || 'newest');

      try {
        var res = await fetch('api/v1/vehicles.php?' + params.toString());
        var data = await res.json();
        if (data.success) {
          this.vehicles = data.vehicles || [];
        }
      } catch (e) {
      } finally {
        this.loading = false;
      }
    },
    async runAiFinder() {
      if (!this.aiPrompt.trim()) return;
      this.aiLoading = true;
      this.aiExplanation = '';
      var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
      try {
        var res = await fetch('api/v1/ai.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
          body: JSON.stringify({
            action: 'search',
            query: this.aiPrompt,
            from_date: this.filters.fromDate,
            to_date: this.filters.toDate
          })
        });
        var data = await res.json();
        if (data.success) {
          this.vehicles = data.vehicles || [];
          this.aiExplanation = data.explanation || '';
          this.aiMeta = 'Engine: ' + (data.tier_label || 'Smart Match');
        } else {
          this.filters.q = this.aiPrompt;
          await this.fetchCars();
        }
      } catch (e) {
        this.filters.q = this.aiPrompt;
        await this.fetchCars();
      } finally {
        this.aiLoading = false;
      }
    },
    resetFilters() {
      this.filters = { q: '', brand: '', fuel: '', transmission: '', seats: '', maxPrice: '', fromDate: '', toDate: '', sort: 'newest' };
      this.aiExplanation = '';
      this.aiMeta = '';
      this.fetchCars();
    }
  };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
