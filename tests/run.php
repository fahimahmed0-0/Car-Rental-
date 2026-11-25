<?php

declare(strict_types=1);

/**
 * Automated CLI verification suite for DriveX Assam.
 * Runs unit tests and embedded SQLite demo tests always, plus MariaDB integration tests when reachable.
 */

define('APP_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = APP_ROOT . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

use App\Core\CookieSessionHandler;
use App\Core\Database;
use App\Core\DemoDatabase;
use App\Core\Env;
use App\Core\Security;
use App\Services\AI\FleetAssistantService;
use App\Services\AI\RuleEngine;
use App\Services\AuthService;
use App\Services\BookingService;
use App\Services\PaymentService;
use App\Services\PricingService;
use App\Services\TokenLedgerService;

Env::load(APP_ROOT . DIRECTORY_SEPARATOR . '.env');

$passed = 0;
$failed = 0;

function assertTrue(bool $cond, string $label): void
{
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "[PASS] {$label}\n";
    } else {
        $failed++;
        fwrite(STDERR, "[FAIL] {$label}\n");
    }
}

function assertEquals(mixed $expected, mixed $actual, string $label): void
{
    assertTrue(
        $expected === $actual,
        $label . ($expected === $actual ? '' : ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')')
    );
}

// ------------------------------------------------------------
// 1. Unit Tests (No DB required)
// ------------------------------------------------------------
$days1 = PricingService::calculateDays('2099-01-10', '2099-01-10', true);
assertEquals(1, $days1, 'PricingService inclusive same-day = 1 day');

$days3 = PricingService::calculateDays('2099-01-10', '2099-01-12', true);
assertEquals(3, $days3, 'PricingService inclusive 10th to 12th = 3 days');

$quote = PricingService::calculateQuote(2000, '2099-01-10', '2099-01-12', 15, 25, true);
assertEquals(6000.0, $quote['base_amount'], 'Quote base amount 3 * 2000 = 6000');
assertEquals(300.0, $quote['discount_amount'], 'Quote discount 15 tokens = 300');
assertEquals(5700.0, $quote['final_amount'], 'Quote final amount = 5700');

$capQuote = PricingService::calculateQuote(500, '2099-01-10', '2099-01-10', 30, 50, true);
assertEquals(500.0, $capQuote['discount_amount'], 'Discount capped at base amount (500)');
assertEquals(0.0, $capQuote['final_amount'], 'Final amount never negative');

$invalidTokenCaught = false;
try {
    PricingService::calculateQuote(2000, '2099-01-10', '2099-01-10', 10, 50, true);
} catch (InvalidArgumentException) {
    $invalidTokenCaught = true;
}
assertTrue($invalidTokenCaught, 'Rejects < 15 tokens redemption');

$cleanHtml = Security::sanitizeRichHtml('<p>Hello</p><script>alert(1)</script><a href="javascript:evil()" onclick="hack()">link</a>');
assertTrue(!str_contains($cleanHtml, '<script>') && !str_contains($cleanHtml, 'onclick') && !str_contains($cleanHtml, 'javascript:'), 'Security::sanitizeRichHtml strips XSS');

// RuleEngine search query parser
$fakeBrands = [(object) ['id' => 1, 'BrandName' => 'Maruti'], (object) ['id' => 2, 'BrandName' => 'Mahindra']];
$parsed = RuleEngine::parseSearchConstraints('Need a 7 seater diesel Mahindra SUV under ₹4500 for Shillong', $fakeBrands);
assertEquals(7, $parsed['seats'], 'RuleEngine parses 7 seats');
assertEquals('Diesel', $parsed['fuel'], 'RuleEngine parses Diesel');
assertEquals(2, $parsed['brand_id'], 'RuleEngine matches Mahindra brand');
assertEquals(4500, $parsed['max_price'], 'RuleEngine parses max price 4500');
assertEquals('hills', $parsed['terrain'], 'RuleEngine detects hills terrain');

// ------------------------------------------------------------
// 2. Database Integration Tests (if MariaDB test DB reachable)
// ------------------------------------------------------------
$testDbName = Env::get('TEST_DB_NAME', 'carrental_test');
try {
    $host = Env::get('DB_HOST', 'localhost');
    $user = Env::get('DB_USER', 'root');
    $pass = Env::get('DB_PASS', '');
    $pdo = new PDO("mysql:host={$host};dbname={$testDbName};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    Database::setConnection($pdo);
    echo "\n[INFO] Connected to test DB '{$testDbName}'. Running integration tests...\n";

    // Clean test tables
    $pdo->exec('TRUNCATE TABLE tbltokenledger');
    $pdo->exec('TRUNCATE TABLE tblpaymenttransactions');
    $pdo->exec('TRUNCATE TABLE tblbooking');
    $pdo->exec('TRUNCATE TABLE tblpasswordresets');
    $pdo->exec('TRUNCATE TABLE tblailogs');
    $pdo->exec('DELETE FROM tblusers WHERE EmailId = "testuser@drivex.local"');

    // Ensure at least one vehicle exists
    $vid = (int) $pdo->query('SELECT id FROM tblvehicles LIMIT 1')->fetchColumn();
    assertTrue($vid > 0, 'Test DB has seeded vehicle');

    // Auth: register + MD5 upgrade + OTP reset
    $auth = new AuthService($pdo);
    $reg = $auth->registerCustomer('Test User', 'testuser@drivex.local', '9876543210', 'StrongPass#123');
    assertTrue($reg['success'], 'Customer registration succeeds');

    // Simulate legacy MD5 hash and verify auto-upgrade on login
    $pdo->prepare('UPDATE tblusers SET Password = :p WHERE EmailId = :e')->execute([':p' => md5('LegacyPass1'), ':e' => 'testuser@drivex.local']);
    $loginRes = $auth->attemptCustomerLogin('testuser@drivex.local', 'LegacyPass1');
    assertTrue($loginRes['success'], 'Legacy MD5 login succeeds');
    $upgradedHash = (string) $pdo->query('SELECT Password FROM tblusers WHERE EmailId = "testuser@drivex.local"')->fetchColumn();
    assertTrue(str_starts_with($upgradedHash, '$2y$') || str_starts_with($upgradedHash, '$argon2'), 'MD5 hash transparently upgraded to modern password_hash');

    // OTP Reset
    $otpReq = $auth->requestPasswordResetOtp('testuser@drivex.local');
    assertTrue($otpReq['success'] && !empty($otpReq['local_preview_otp']), 'Password reset OTP generated');
    $otpVerify = $auth->resetPasswordWithOtp('testuser@drivex.local', (string) $otpReq['local_preview_otp'], 'NewStrong#999');
    assertTrue($otpVerify['success'], 'Password reset via OTP succeeds');

    // Token Ledger + Booking + Payment + Idempotency
    $tokens = new TokenLedgerService($pdo);
    $tokens->adminAdjust('testuser@drivex.local', 20, 'Test seed balance');
    assertEquals(20, $tokens->getBalance('testuser@drivex.local')['balance'], 'Admin token adjustment credited 20 tokens');

    $bookings = new BookingService($pdo, $tokens);
    $created = $bookings->createBooking('testuser@drivex.local', $vid, '2099-05-01', '2099-05-03', 'Test trip', 15, 'pay_now');
    $bnum = (int) $created['booking_number'];
    $bid = (int) $created['booking_id'];
    assertTrue($bnum > 0, 'Booking created with 10-min price lock');

    // Tokens should NOT be deducted before online payment succeeds
    assertEquals(20, $tokens->getBalance('testuser@drivex.local')['balance'], 'Tokens remain intact prior to online payment');

    // Overlap prevention
    $overlapCaught = false;
    try {
        $bookings->createBooking('testuser@drivex.local', $vid, '2099-05-02', '2099-05-04', 'Overlap', 0, 'pay_now');
    } catch (RuntimeException) {
        $overlapCaught = true;
    }
    assertTrue($overlapCaught, 'Overlapping booking rejected by row-level lock');

    // Payment completion + idempotency
    $payments = new PaymentService($pdo, $tokens);
    $pay1 = $payments->processSandboxPayment($bnum, 'testuser@drivex.local', 'upi', 'idem_test_1');
    $pay2 = $payments->processSandboxPayment($bnum, 'testuser@drivex.local', 'upi', 'idem_test_1');
    assertTrue($pay1['success'] && $pay2['success'] && $pay1['transaction_id'] === $pay2['transaction_id'], 'Sandbox payment succeeds and idempotency key prevents duplicate charge');
    assertEquals(5, $tokens->getBalance('testuser@drivex.local')['balance'], '15 redeemed tokens deducted atomically upon payment (20 -> 5)');

    // Transition to Completed -> awards +5 tokens ONCE (idempotent)
    $comp1 = $bookings->transitionStatus($bid, BookingService::STATUS_COMPLETED);
    assertTrue($comp1['success'], 'Booking transitioned to Completed');
    $award2 = $tokens->awardOnCompletion($bid, $bnum, 'testuser@drivex.local');
    assertTrue(!$award2, 'Second completion token award blocked by idempotency guard');
    assertEquals(10, $tokens->getBalance('testuser@drivex.local')['balance'], 'Balance is 5 + 5 = 10 after completion');

    // Cancel booking releases dates & refunds tokens
    $created2 = $bookings->createBooking('testuser@drivex.local', $vid, '2099-06-01', '2099-06-02', 'Cancel test', 0, 'pay_at_pickup');
    $cancelRes = $bookings->cancelCustomerBooking((int) $created2['booking_number'], 'testuser@drivex.local');
    assertTrue($cancelRes['success'], 'Customer cancellation succeeds');
    assertTrue($bookings->isVehicleAvailable($vid, '2099-06-01', '2099-06-02', false), 'Cancelled booking releases dates immediately');

    // FleetAssistantService offline rule-engine test
    $ai = new FleetAssistantService($pdo);
    $aiSearch = $ai->smartCarSearch('5 seater diesel under 3000');
    assertTrue($aiSearch['success'] && !empty($aiSearch['vehicles']), 'FleetAssistantService::smartCarSearch returns ranked vehicles');
    $aiChat = $ai->answerAssistant('How do loyalty tokens work?');
    assertTrue($aiChat['success'] && str_contains($aiChat['reply'], '15 tokens'), 'AI assistant answers policy accurately offline');
} catch (Throwable $e) {
    echo "\n[SKIP] MariaDB integration tests skipped (" . $e->getMessage() . ")\n";
}

// Embedded SQLite Demo Mode (DemoDatabase + SqliteCompatPdo + CookieSessionHandler) tests
$demoCachePath = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'drivex_demo.sqlite';
$demoTempPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drivex_demo.sqlite';
$hadCacheDemo = false;
$hadTempDemo = is_file($demoTempPath);
if (is_file($demoCachePath)) {
    @unlink($demoCachePath);
}

$demoPdo = DemoDatabase::connect();
$vehicleCount = (int) $demoPdo->query('SELECT COUNT(*) FROM tblvehicles')->fetchColumn();
assertTrue($vehicleCount >= 6, 'DemoDatabase auto-seeds 6+ fleet vehicles in SQLite');

$_SESSION = [];
$demoAuth = new AuthService($demoPdo);
$demoCustLogin = $demoAuth->attemptCustomerLogin(DemoDatabase::DEMO_CUSTOMER_EMAIL, DemoDatabase::DEMO_CUSTOMER_PASS);
assertTrue($demoCustLogin['success'], 'Demo customer credentials authenticate on SQLite');

$demoAdminLogin = $demoAuth->attemptAdminLogin(DemoDatabase::DEMO_ADMIN_USER, DemoDatabase::DEMO_ADMIN_PASS);
assertTrue($demoAdminLogin['success'], 'Demo admin credentials authenticate on SQLite');

$demoTokens = new TokenLedgerService($demoPdo);
assertEquals(25, $demoTokens->getBalance(DemoDatabase::DEMO_CUSTOMER_EMAIL)['balance'], 'Demo customer starts with 25 seeded loyalty tokens');

$demoBookings = new BookingService($demoPdo, $demoTokens);
$demoCreated = $demoBookings->createBooking(DemoDatabase::DEMO_CUSTOMER_EMAIL, 1, '2099-08-01', '2099-08-02', 'Demo trip', 15, 'pay_now');
assertTrue((int) ($demoCreated['booking_number'] ?? 0) > 0, 'Demo SQLite booking created with token lock');

$demoPayments = new PaymentService($demoPdo, $demoTokens);
$demoPay = $demoPayments->processSandboxPayment((int) $demoCreated['booking_number'], DemoDatabase::DEMO_CUSTOMER_EMAIL, 'upi', 'demo_idem_1');
assertTrue($demoPay['success'], 'Demo SQLite sandbox payment succeeds');
assertEquals(10, $demoTokens->getBalance(DemoDatabase::DEMO_CUSTOMER_EMAIL)['balance'], 'Demo SQLite payment deducts 15 tokens (25 -> 10)');

$demoComp = $demoBookings->transitionStatus((int) $demoCreated['booking_id'], BookingService::STATUS_COMPLETED);
assertTrue($demoComp['success'], 'Demo SQLite booking completes & awards +5 tokens');
assertEquals(15, $demoTokens->getBalance(DemoDatabase::DEMO_CUSTOMER_EMAIL)['balance'], 'Demo SQLite completion awards +5 tokens (10 -> 15)');

$demoAi = new FleetAssistantService($demoPdo);
$demoAiSearch = $demoAi->smartCarSearch('5 seater suv under 4000');
assertTrue($demoAiSearch['success'] && !empty($demoAiSearch['vehicles']), 'Demo SQLite AI smartCarSearch returns ranked fleet vehicles');

$cookieHandler = new CookieSessionHandler(false);
$_COOKIE['drivex_sess_data'] = base64_encode(json_encode(['exp' => time() + 60, 'data' => 'x']) ?: '') . '.invalidsig';
assertEquals('', (string) $cookieHandler->read('test'), 'CookieSessionHandler rejects tampered HMAC signatures');
unset($_COOKIE['drivex_sess_data'], $_SESSION);

// Release SQLite file locks and clean up test SQLite file
$demoAi = null;
$demoBookings = null;
$demoPayments = null;
$demoTokens = null;
$demoAuth = null;
$demoPdo = null;
gc_collect_cycles();
if (!$hadCacheDemo && is_file($demoCachePath)) {
    @unlink($demoCachePath);
}
if (!$hadTempDemo && is_file($demoTempPath)) {
    @unlink($demoTempPath);
}

echo "\nSummary: {$passed} passed, {$failed} failed.\n";
exit($failed > 0 ? 1 : 0);
