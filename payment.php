<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/config.php';

use App\Core\Csrf;
use App\Core\Security;
use App\Core\Session;
use App\Services\BookingService;
use App\Services\PaymentService;

if (empty($_SESSION['login'])) {
    Session::setFlash('error', 'Please sign in to complete your payment.');
    Security::redirect('index.php');
}

$userEmail = (string) $_SESSION['login'];
$bookingNumber = (int) ($_GET['booking_id'] ?? $_SESSION['current_booking_id'] ?? 0);

if ($bookingNumber <= 0) {
    Security::redirect('my-booking.php');
}

$bookingSvc = new BookingService($dbh);
$bookingSvc->expireStalePendingLocks();
$booking = $bookingSvc->getBookingByNumberForUser($bookingNumber, $userEmail);

if (!$booking) {
    Session::setFlash('error', 'Booking not found for your account.');
    Security::redirect('my-booking.php');
}

if ((string) $booking->PaymentStatus === 'completed') {
    Security::redirect('payment-success.php?booking_id=' . $bookingNumber);
}

$paymentSvc = new PaymentService($dbh);

// Handle direct POST checkout BEFORE HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_payment'])) {
    Csrf::requireValid();
    $method = (string) ($_POST['payment_method'] ?? 'upi');
    $idemKey = (string) ($_POST['idempotency_key'] ?? '');

    $res = $paymentSvc->processSandboxPayment($bookingNumber, $userEmail, $method, $idemKey ?: null);
    if ($res['success']) {
        Session::setFlash('success', 'Payment successful! Transaction ID: ' . ($res['transaction_id'] ?? ''));
        Security::redirect('payment-success.php?booking_id=' . $bookingNumber);
    }
    Session::setFlash('error', $res['message']);
    Security::redirect('payment.php?booking_id=' . $bookingNumber);
}

try {
    $order = $paymentSvc->createOrderForBooking($bookingNumber, $userEmail);
} catch (Throwable $e) {
    Session::setFlash('error', $e->getMessage());
    Security::redirect('my-booking.php');
}

$secondsRemaining = (int) ($booking->lock_seconds_remaining ?? 600);
$idempotencyKey = 'pay_' . $bookingNumber . '_' . bin2hex(random_bytes(8));

$pageTitle = 'DriveX Assam | Secure Checkout #' . $bookingNumber;
include __DIR__ . '/includes/layout_head.php';
?>

<section class="bg-slate-950 text-white py-6 border-b border-slate-800">
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex flex-wrap items-center justify-between gap-4">
    <div>
      <a href="my-booking.php" class="text-xs font-semibold text-sky-300 hover:underline">&larr; Back to My Bookings</a>
      <h1 class="mt-1 text-2xl font-extrabold">Secure Payment Checkout</h1>
    </div>
    <div class="flex items-center gap-3">
      <?php if ($order['provider'] === 'sandbox'): ?>
        <span class="rounded-full border border-sky-400/40 bg-blue-950 px-3 py-1 text-xs font-bold text-sky-300">TEST PAYMENT GATEWAY</span>
      <?php endif; ?>
      <div class="rounded-lg border border-red-500/40 bg-red-950/60 px-4 py-2 text-center" x-data="countdownTimer(<?php echo $secondsRemaining; ?>)">
        <div class="text-[10px] font-bold uppercase tracking-wider text-red-300">Price Lock Expires</div>
        <div class="text-base font-extrabold text-white font-mono" x-text="formatted">10:00</div>
      </div>
    </div>
  </div>
</section>

<section class="py-10 flex-1">
  <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 gap-8 lg:grid-cols-12" x-data="{ method: 'upi', showQr: true }">
      <!-- Payment Methods -->
      <div class="lg:col-span-7 space-y-6">
        <div class="card p-6">
          <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-5">
            <h2 class="text-lg font-extrabold text-slate-950">Select Payment Method</h2>
            <span class="text-xs font-semibold text-slate-500">Order #<?php echo (int) $bookingNumber; ?></span>
          </div>

          <form method="post" id="checkout-form" class="space-y-3">
            <?php echo Csrf::field(); ?>
            <input type="hidden" name="idempotency_key" value="<?php echo Security::e($idempotencyKey); ?>">

            <?php foreach (PaymentService::VALID_METHODS as $key => $label): ?>
              <label @click="method = '<?php echo $key; ?>'"
                     :class="method === '<?php echo $key; ?>' ? 'border-blue-700 bg-blue-50/70 ring-1 ring-blue-700' : 'border-slate-200 hover:border-slate-300'"
                     class="flex cursor-pointer items-center justify-between rounded-xl border p-4 transition">
                <div class="flex items-center gap-3">
                  <input type="radio" name="payment_method" value="<?php echo $key; ?>" x-model="method" class="h-4 w-4 text-blue-700 focus:ring-blue-600">
                  <div>
                    <div class="text-sm font-bold text-slate-950"><?php echo Security::e($label); ?></div>
                    <div class="text-xs text-slate-500">Instant confirmation &amp; digital receipt</div>
                  </div>
                </div>
                <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-bold uppercase text-slate-700"><?php echo Security::e($key); ?></span>
              </label>
            <?php endforeach; ?>

            <?php if ($order['provider'] === 'razorpay' && !empty($order['razorpay_key'])): ?>
              <button type="button" id="rzp-pay-btn" class="btn-red w-full !py-3.5 text-base mt-4">
                Pay ₹<?php echo number_format((float) $order['amount'], 2); ?> via Razorpay
              </button>
            <?php else: ?>
              <button type="submit" name="process_payment" value="1" class="btn-red w-full !py-3.5 text-base mt-4">
                Complete Payment &bull; ₹<?php echo number_format((float) $order['amount'], 2); ?>
              </button>
            <?php endif; ?>
          </form>
        </div>

        <?php if (!empty($order['upi_uri'])): ?>
          <!-- Test UPI QR -->
          <div class="card p-6 text-center" x-show="method === 'upi'">
            <h3 class="text-sm font-extrabold uppercase tracking-wider text-slate-900">Scan Test UPI QR</h3>
            <p class="mt-1 text-xs text-slate-500">Amount locked to server-verified total: <strong>₹<?php echo number_format((float) $order['amount'], 2); ?></strong></p>
            <div class="my-4 inline-block rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
              <div id="sandbox-qr-box" class="flex items-center justify-center"></div>
            </div>
            <p class="text-[11px] font-mono text-slate-500 break-all"><?php echo Security::e($order['upi_uri']); ?></p>
          </div>
        <?php endif; ?>
      </div>

      <!-- Booking Summary -->
      <aside class="lg:col-span-5">
        <div class="card p-6 space-y-4">
          <h2 class="text-lg font-extrabold text-slate-950 border-b border-slate-100 pb-3">Booking Summary</h2>
          <div class="flex gap-4 items-center">
            <img src="<?php echo Security::e(Security::vehicleImageUrl($booking->Vimage1)); ?>" alt="<?php echo Security::e($booking->VehiclesTitle); ?>" class="h-20 w-28 rounded-lg object-cover border border-slate-200">
            <div>
              <div class="text-xs font-bold text-blue-700"><?php echo Security::e($booking->BrandName); ?></div>
              <div class="text-base font-bold text-slate-950"><?php echo Security::e($booking->VehiclesTitle); ?></div>
              <div class="text-xs text-slate-500"><?php echo Security::e($booking->FuelType); ?> &bull; <?php echo (int) $booking->SeatingCapacity; ?> Seats</div>
            </div>
          </div>

          <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs space-y-2">
            <div class="flex justify-between">
              <span class="text-slate-600">Pickup</span>
              <span class="font-bold text-slate-900"><?php echo Security::e($booking->FromDate); ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-600">Drop-off</span>
              <span class="font-bold text-slate-900"><?php echo Security::e($booking->ToDate); ?></span>
            </div>
            <div class="flex justify-between">
              <span class="text-slate-600">Hub</span>
              <span class="font-semibold text-slate-800"><?php echo Security::e($booking->PickupLocation ?? 'DriveX Hub, Guwahati'); ?></span>
            </div>
          </div>

          <div class="space-y-2 text-sm">
            <div class="flex justify-between text-slate-600">
              <span>Base Rental Amount</span>
              <span>₹<?php echo number_format((float) $booking->Amount, 2); ?></span>
            </div>
            <?php if ((float) $booking->DiscountAmount > 0): ?>
              <div class="flex justify-between text-blue-700 font-semibold">
                <span>Token Discount (<?php echo (int) $booking->TokensUsed; ?> tokens)</span>
                <span>- ₹<?php echo number_format((float) $booking->DiscountAmount, 2); ?></span>
              </div>
            <?php endif; ?>
            <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
              <span class="font-bold text-slate-950">Final Payable</span>
              <span class="text-2xl font-extrabold text-red-600">₹<?php echo number_format((float) $order['amount'], 2); ?></span>
            </div>
          </div>

          <div class="rounded-lg border border-sky-200 bg-blue-50 p-3 text-xs text-blue-900">
            <strong>Cancellation Policy:</strong> Cancel anytime before trip start to automatically refund redeemed loyalty tokens.
          </div>
        </div>
      </aside>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
function countdownTimer(seconds) {
  return {
    remaining: seconds,
    get formatted() {
      var m = String(Math.floor(this.remaining / 60)).padStart(2, '0');
      var s = String(this.remaining % 60).padStart(2, '0');
      return m + ':' + s;
    },
    init() {
      var self = this;
      var timer = setInterval(function() {
        if (self.remaining > 0) {
          self.remaining--;
        } else {
          clearInterval(timer);
          window.location.href = 'my-booking.php';
        }
      }, 1000);
    }
  };
}
document.addEventListener('DOMContentLoaded', function() {
  var qrBox = document.getElementById('sandbox-qr-box');
  var upiUri = <?php echo json_encode($order['upi_uri'] ?? ''); ?>;
  if (qrBox && upiUri && typeof QRCode !== 'undefined') {
    new QRCode(qrBox, { text: upiUri, width: 160, height: 160, colorDark: '#0f172a', colorLight: '#ffffff' });
  }
});
</script>

<?php if ($order['provider'] === 'razorpay' && !empty($order['razorpay_key'])): ?>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('rzp-pay-btn')?.addEventListener('click', function() {
  var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  var rzp = new Razorpay({
    key: <?php echo json_encode($order['razorpay_key']); ?>,
    amount: <?php echo (int) round((float) $order['amount'] * 100); ?>,
    currency: 'INR',
    name: 'DriveX Assam',
    description: 'Booking #<?php echo (int) $bookingNumber; ?>',
    order_id: <?php echo json_encode($order['order_id']); ?>,
    handler: async function(response) {
      var res = await fetch('api/v1/payments.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
        body: JSON.stringify({
          action: 'verify_razorpay',
          booking_number: <?php echo (int) $bookingNumber; ?>,
          razorpay_order_id: response.razorpay_order_id,
          razorpay_payment_id: response.razorpay_payment_id,
          razorpay_signature: response.razorpay_signature
        })
      });
      var data = await res.json();
      if (data.success) {
        window.location.href = 'payment-success.php?booking_id=<?php echo (int) $bookingNumber; ?>';
      } else {
        alert(data.message || 'Verification failed');
      }
    }
  });
  rzp.open();
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
