<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Zero-configuration SQLite Portfolio Demo database (auto-created & seeded in /tmp or storage/cache).
 * Enables full interactive testing on Vercel or locally without provisioning MySQL.
 */
final class DemoDatabase
{
    public const DEMO_CUSTOMER_EMAIL = 'demo@drivexassam.com';
    public const DEMO_CUSTOMER_PASS = 'DemoPass#123';
    public const DEMO_ADMIN_USER = 'admin';
    public const DEMO_ADMIN_PASS = 'AdminDemo#123';

    public static function connect(): PDO
    {
        $isVercel = Env::bool('VERCEL', false) || (getenv('VERCEL_ENV') !== false && getenv('VERCEL_ENV') !== '');
        $cacheDir = APP_ROOT . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        $dbPath = (!$isVercel && is_dir($cacheDir) && is_writable($cacheDir))
            ? $cacheDir . DIRECTORY_SEPARATOR . 'drivex_demo.sqlite'
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'drivex_demo.sqlite';

        $pdo = new SqliteCompatPdo('sqlite:' . $dbPath);
        self::ensureSchemaAndSeed($pdo);
        return $pdo;
    }

    private static function ensureSchemaAndSeed(PDO $pdo): void
    {
        // Fast check if already initialized
        $hasVehicles = $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='tblvehicles' LIMIT 1")->fetchColumn();
        if ($hasVehicles) {
            $cnt = (int) $pdo->query('SELECT COUNT(*) FROM tblvehicles')->fetchColumn();
            if ($cnt > 0) {
                return;
            }
        }

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS admin (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                UserName TEXT NOT NULL UNIQUE,
                Password TEXT NOT NULL,
                updationDate TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblbrands (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                BrandName TEXT NOT NULL UNIQUE,
                CreationDate TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdationDate TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS tblusers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                FullName TEXT,
                EmailId TEXT UNIQUE,
                Password TEXT,
                ContactNo TEXT,
                dob TEXT,
                Address TEXT,
                City TEXT,
                Country TEXT,
                Role TEXT NOT NULL DEFAULT 'customer',
                TokenBalance INTEGER NOT NULL DEFAULT 0,
                TotalTokensEarned INTEGER NOT NULL DEFAULT 0,
                RegDate TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdationDate TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS tblvehicles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                VehiclesTitle TEXT,
                VehiclesBrand INTEGER,
                VehiclesOverview TEXT,
                PricePerDay INTEGER,
                FuelType TEXT,
                ModelYear INTEGER,
                SeatingCapacity INTEGER,
                Transmission TEXT NOT NULL DEFAULT 'Manual',
                Vimage1 TEXT,
                Vimage2 TEXT,
                Vimage3 TEXT,
                Vimage4 TEXT,
                Vimage5 TEXT,
                AirConditioner INTEGER DEFAULT 0,
                PowerDoorLocks INTEGER DEFAULT 0,
                AntiLockBrakingSystem INTEGER DEFAULT 0,
                BrakeAssist INTEGER DEFAULT 0,
                PowerSteering INTEGER DEFAULT 0,
                DriverAirbag INTEGER DEFAULT 0,
                PassengerAirbag INTEGER DEFAULT 0,
                PowerWindows INTEGER DEFAULT 0,
                CDPlayer INTEGER DEFAULT 0,
                CentralLocking INTEGER DEFAULT 0,
                CrashSensor INTEGER DEFAULT 0,
                LeatherSeats INTEGER DEFAULT 0,
                RegDate TEXT DEFAULT CURRENT_TIMESTAMP,
                UpdationDate TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS tblbooking (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                BookingNumber INTEGER UNIQUE,
                userEmail TEXT,
                VehicleId INTEGER,
                FromDate TEXT,
                ToDate TEXT,
                message TEXT,
                Status INTEGER NOT NULL DEFAULT 0,
                PaymentMethod TEXT DEFAULT NULL,
                PaymentStatus TEXT NOT NULL DEFAULT 'pending',
                PaymentDate TEXT DEFAULT NULL,
                Amount REAL NOT NULL DEFAULT 0.00,
                DiscountAmount REAL NOT NULL DEFAULT 0.00,
                TaxAmount REAL NOT NULL DEFAULT 0.00,
                FinalAmount REAL NOT NULL DEFAULT 0.00,
                TokensUsed INTEGER NOT NULL DEFAULT 0,
                PriceLockUntil TEXT DEFAULT NULL,
                PickupLocation TEXT DEFAULT 'DriveX Hub, Guwahati, Assam',
                DropoffLocation TEXT DEFAULT 'DriveX Hub, Guwahati, Assam',
                PostingDate TEXT DEFAULT CURRENT_TIMESTAMP,
                LastUpdationDate TEXT DEFAULT NULL
            );
            CREATE TABLE IF NOT EXISTS tblcontactusinfo (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                Address TEXT,
                EmailId TEXT,
                ContactNo TEXT
            );
            CREATE TABLE IF NOT EXISTS tblcontactusquery (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT,
                EmailId TEXT,
                ContactNumber TEXT,
                Message TEXT,
                PostingDate TEXT DEFAULT CURRENT_TIMESTAMP,
                status INTEGER DEFAULT 0
            );
            CREATE TABLE IF NOT EXISTS tblpages (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                PageName TEXT,
                type TEXT NOT NULL UNIQUE,
                detail TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS tblsubscribers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                SubscriberEmail TEXT UNIQUE,
                PostingDate TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tbltestimonial (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                UserEmail TEXT NOT NULL,
                Testimonial TEXT NOT NULL,
                PostingDate TEXT DEFAULT CURRENT_TIMESTAMP,
                status INTEGER DEFAULT 0,
                AiModerationFlag TEXT DEFAULT 'clean'
            );
            CREATE TABLE IF NOT EXISTS tbltokenledger (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_email TEXT NOT NULL,
                booking_id INTEGER DEFAULT NULL,
                booking_number INTEGER DEFAULT NULL,
                entry_type TEXT NOT NULL,
                delta INTEGER NOT NULL,
                discount_amount REAL NOT NULL DEFAULT 0.00,
                note TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(booking_id, entry_type)
            );
            CREATE TABLE IF NOT EXISTS tblpaymenttransactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                booking_id INTEGER NOT NULL,
                user_email TEXT NOT NULL,
                provider TEXT NOT NULL DEFAULT 'sandbox',
                payment_method TEXT NOT NULL,
                amount REAL NOT NULL,
                transaction_id TEXT UNIQUE,
                idempotency_key TEXT UNIQUE,
                gateway_order_id TEXT DEFAULT NULL,
                gateway_signature TEXT DEFAULT NULL,
                payment_status TEXT NOT NULL DEFAULT 'pending',
                gateway_response TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblpasswordresets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                otp_hash TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                expires_at TEXT NOT NULL,
                used_at TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblmailoutbox (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                recipient TEXT NOT NULL,
                subject TEXT NOT NULL,
                body TEXT NOT NULL,
                status TEXT NOT NULL DEFAULT 'queued_fallback',
                error_message TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblailogs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                feature TEXT NOT NULL,
                tier_used TEXT NOT NULL,
                provider TEXT NOT NULL,
                latency_ms INTEGER NOT NULL DEFAULT 0,
                success INTEGER NOT NULL DEFAULT 1,
                error_note TEXT DEFAULT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblaicache (
                cache_key TEXT PRIMARY KEY,
                feature TEXT NOT NULL,
                response_json TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
            CREATE TABLE IF NOT EXISTS tblratelimits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                bucket_key TEXT NOT NULL,
                attempt_at TEXT NOT NULL
            );
            CREATE TABLE IF NOT EXISTS tblsessions (
                id TEXT PRIMARY KEY,
                data TEXT NOT NULL,
                expires_at INTEGER NOT NULL
            );
        ");

        self::seedDemoData($pdo);
    }

    private static function seedDemoData(PDO $pdo): void
    {
        // Admin
        $pdo->prepare('INSERT OR IGNORE INTO admin (UserName, Password) VALUES (?, ?)')
            ->execute([self::DEMO_ADMIN_USER, password_hash(self::DEMO_ADMIN_PASS, PASSWORD_DEFAULT)]);

        // Brands
        $brands = [
            [1, 'Maruti'],
            [2, 'Mahindra'],
            [3, 'TATA'],
            [4, 'Nissan'],
            [5, 'Hyundai'],
            [7, 'Volkswagen'],
        ];
        $bStmt = $pdo->prepare('INSERT OR IGNORE INTO tblbrands (id, BrandName) VALUES (?, ?)');
        foreach ($brands as $b) {
            $bStmt->execute($b);
        }

        // Vehicles (using committed images in admin/img/vehicleimages/)
        $vehicles = [
            ['Vitara Brezza ZDi+', 1, 'Compact SUV ideal for both Guwahati city commutes and Meghalaya highway drives with strong mileage and generous boot space.', 2400, 'Diesel', 2023, 5, 'Manual', 'brezza-exterior-right-front-three-quarter-6.webp', 'brezza-exterior-right-side-view.webp', 'brezza-exterior-right-rear-three-quarter.webp', 'brezza-interior-bootspace.webp', 'brezzamarutisuzukibrezzafrontrowseats.webp'],
            ['Fronx Turbo Alpha', 1, 'Sleek crossover with responsive turbo petrol engine, comfortable 5-seat cabin, and modern safety suite.', 2200, 'Petrol', 2024, 5, 'Automatic', 'fronxmarutisuzukifronxrightfrontthreequarter1.webp', 'fronxfronxleftsideview.webp', 'fronxfronxbootspace.webp', 'fronxmarutisuzukifronxrearseats.webp', ''],
            ['XUV300 W8 Optional', 2, '5-star safety rated compact SUV with dual airbags, ABS, crash sensors, and refined highway handling.', 2600, 'Diesel', 2023, 5, 'Manual', 'xuv300[20192024]xuv300rightfrontthreequarter.webp', 'xuv300[20192024]rightfrontthreequarter1.webp', 'xuv300[20192024]rightfrontthreequarter6.webp', 'xuv300[20192024]rightfrontthreequarter42.webp', ''],
            ['Punch Creative', 3, 'High ground-clearance urban micro-SUV built for Assam roads and weekend getaways.', 1800, 'Petrol', 2024, 5, 'Manual', 'punchpunchrightfrontthreequarter.webp', 'punchtigorleftsideview.webp', 'punchpunchrearseats.webp', 'punchtatapunchbootreleaseleverfuellidreleaselever17.webp', ''],
            ['Fortuner Legender 4x4', 7, 'Full-size 7-seater touring SUV suited for family trips to Kaziranga, Tawang, and hill terrain.', 4800, 'Diesel', 2023, 7, 'Automatic', '2015_Toyota_Fortuner_(New_Zealand).jpg', 'toyota-fortuner-legender-rear-quarters-6e57.jpg', 'zw-toyota-fortuner-2020-2.jpg', 'boot-with-standard-luggage-202327489_930x620.jpg', ''],
            ['WagonR VXi', 1, 'Economical, easy-to-park hatchback with tall-boy headroom and practical luggage capacity.', 1400, 'CNG', 2023, 5, 'Manual', 'wagonrwagonrrightfrontthreequarter (1).webp', 'wagonrwagonrleftsideview (1).webp', 'wagonrwagonrrightrearthreequarter.webp', 'wagonrmarutisuzukiwagonropenboottrunk25.webp', ''],
        ];
        $vStmt = $pdo->prepare(
            'INSERT INTO tblvehicles (VehiclesTitle, VehiclesBrand, VehiclesOverview, PricePerDay, FuelType, ModelYear, SeatingCapacity, Transmission, Vimage1, Vimage2, Vimage3, Vimage4, Vimage5, AirConditioner, PowerDoorLocks, AntiLockBrakingSystem, BrakeAssist, PowerSteering, DriverAirbag, PassengerAirbag, PowerWindows, CDPlayer, CentralLocking, CrashSensor, LeatherSeats)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 0)'
        );
        foreach ($vehicles as $v) {
            $vStmt->execute($v);
        }

        // Demo Customer (with 25 loyalty tokens ready to redeem!)
        $pdo->prepare(
            'INSERT OR IGNORE INTO tblusers (FullName, EmailId, Password, ContactNo, City, Country, Role, TokenBalance, TotalTokensEarned)
             VALUES (?, ?, ?, ?, ?, ?, ?, 25, 25)'
        )->execute([
            'Aarav Sharma (Demo)',
            self::DEMO_CUSTOMER_EMAIL,
            password_hash(self::DEMO_CUSTOMER_PASS, PASSWORD_DEFAULT),
            '9876543210',
            'Guwahati',
            'India',
            'customer',
        ]);

        // Contact info
        $pdo->prepare('INSERT INTO tblcontactusinfo (Address, EmailId, ContactNo) VALUES (?, ?, ?)')
            ->execute(['GS Road, Guwahati, Assam 781005', 'support@drivexassam.com', '+91 9876543210']);

        // CMS Pages
        $pages = [
            ['About Us', 'aboutus', '<p><strong>DriveX Assam</strong> provides reliable self-drive and chauffeur-assisted car rentals across Guwahati, Kaziranga, Shillong, and Northeast India. Every vehicle undergoes a multi-point safety inspection prior to handover.</p>'],
            ['Privacy Policy', 'privacy', '<p>We collect only the details needed to confirm your rental reservation. Passwords use strong cryptographic hashing and payment amounts are always verified server-side.</p>'],
            ['Terms and Conditions', 'terms', '<p>Renters must carry a valid driving license and government photo ID at pickup. Cancelling a booking automatically refunds any redeemed loyalty tokens to your account wallet.</p>'],
            ['Frequently Asked Questions', 'faqs', '<p><strong>How do loyalty tokens work?</strong> Earn 5 tokens after every completed trip. Redeem 15 or more tokens (in multiples of 5) where 5 tokens = ₹100 discount.</p>'],
        ];
        $pStmt = $pdo->prepare('INSERT OR IGNORE INTO tblpages (PageName, type, detail) VALUES (?, ?, ?)');
        foreach ($pages as $p) {
            $pStmt->execute($p);
        }

        // Testimonials
        $tStmt = $pdo->prepare('INSERT INTO tbltestimonial (UserEmail, Testimonial, status, AiModerationFlag) VALUES (?, ?, 1, ?)');
        $tStmt->execute([self::DEMO_CUSTOMER_EMAIL, 'Booked the Vitara Brezza for a 3-day Shillong trip. Smooth airport pickup and transparent ₹ pricing.', 'clean']);
        $tStmt->execute([self::DEMO_CUSTOMER_EMAIL, 'Earned 5 tokens after my Kaziranga ride and redeemed them for ₹300 off on my next booking!', 'clean']);

        // Sample completed & confirmed bookings + payment transaction + token ledger for rich Demo dashboards
        $pastFrom = date('Y-m-d', strtotime('-14 days'));
        $pastTo = date('Y-m-d', strtotime('-12 days'));
        $futureFrom = date('Y-m-d', strtotime('+7 days'));
        $futureTo = date('Y-m-d', strtotime('+9 days'));

        $pdo->prepare(
            "INSERT INTO tblbooking (BookingNumber, userEmail, VehicleId, FromDate, ToDate, message, Status, PaymentMethod, PaymentStatus, PaymentDate, Amount, DiscountAmount, FinalAmount, TokensUsed, PostingDate)
             VALUES (482910345, ?, 1, ?, ?, 'Kaziranga weekend family trip', 3, 'upi', 'completed', datetime('now', '-12 days'), 7200.00, 0.00, 7200.00, 0, datetime('now', '-15 days'))"
        )->execute([self::DEMO_CUSTOMER_EMAIL, $pastFrom, $pastTo]);

        $pdo->prepare(
            "INSERT INTO tblbooking (BookingNumber, userEmail, VehicleId, FromDate, ToDate, message, Status, PaymentMethod, PaymentStatus, PaymentDate, Amount, DiscountAmount, FinalAmount, TokensUsed, PostingDate)
             VALUES (739102846, ?, 5, ?, ?, 'Shillong hill tour', 1, 'card', 'completed', datetime('now', '-1 day'), 14400.00, 0.00, 14400.00, 0, datetime('now', '-1 day'))"
        )->execute([self::DEMO_CUSTOMER_EMAIL, $futureFrom, $futureTo]);

        $pdo->prepare(
            "INSERT INTO tblpaymenttransactions (booking_id, user_email, provider, payment_method, amount, transaction_id, idempotency_key, payment_status, gateway_response, created_at)
             VALUES (482910345, ?, 'sandbox', 'upi', 7200.00, 'TXN_DEMO_1001', 'demo_ik_1', 'completed', '{\"mode\":\"sandbox\",\"status\":\"success\"}', datetime('now', '-15 days'))"
        )->execute([self::DEMO_CUSTOMER_EMAIL]);

        $pdo->prepare(
            "INSERT INTO tblpaymenttransactions (booking_id, user_email, provider, payment_method, amount, transaction_id, idempotency_key, payment_status, gateway_response, created_at)
             VALUES (739102846, ?, 'sandbox', 'card', 14400.00, 'TXN_DEMO_1002', 'demo_ik_2', 'completed', '{\"mode\":\"sandbox\",\"status\":\"success\"}', datetime('now', '-1 day'))"
        )->execute([self::DEMO_CUSTOMER_EMAIL]);

        $pdo->prepare(
            "INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
             VALUES (?, 1, 482910345, 'earn_completion', 5, 0.00, 'Completed trip reward for booking #482910345')"
        )->execute([self::DEMO_CUSTOMER_EMAIL]);

        $pdo->prepare(
            "INSERT INTO tbltokenledger (user_email, booking_id, booking_number, entry_type, delta, discount_amount, note)
             VALUES (?, NULL, NULL, 'admin_adjust', 20, 0.00, 'Welcome bonus tokens for portfolio demo')"
        )->execute([self::DEMO_CUSTOMER_EMAIL]);

        // Sample contact queries for Admin AI Reply Copilot
        $pdo->prepare('INSERT INTO tblcontactusquery (name, EmailId, ContactNumber, Message, status) VALUES (?, ?, ?, ?, 0)')
            ->execute(['Rohan Das', 'rohan.das@example.com', '9864011223', 'Hi, do you provide pickup at Guwahati Airport (GAU) for a 4-day Shillong trip?']);

        $pdo->prepare('INSERT INTO tblsubscribers (SubscriberEmail) VALUES (?)')
            ->execute([self::DEMO_CUSTOMER_EMAIL]);
    }
}
