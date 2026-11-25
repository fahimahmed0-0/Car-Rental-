<?php

declare(strict_types=1);

/**
 * Idempotent CLI seed script for initial admin, CMS pages, contact info, and sample vehicles.
 */
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Forbidden: Run seed script from CLI only.');
}

require_once __DIR__ . '/../src/bootstrap.php';

use App\Core\Database;
use App\Core\Env;

$dbh = Database::connection();

try {
    // 1. Seed or update placeholder admin password safely
    $adminStmt = $dbh->prepare('SELECT id, Password FROM admin WHERE UserName = :u LIMIT 1');
    $adminStmt->execute([':u' => 'admin']);
    $adminRow = $adminStmt->fetch();

    if (!$adminRow || $adminRow->Password === 'YOUR_HASHED_PASSWORD' || $adminRow->Password === '') {
        $rawPassword = Env::get('ADMIN_INIT_PASSWORD');
        $generated = false;
        if ($rawPassword === null || $rawPassword === '') {
            $rawPassword = 'DriveX#' . bin2hex(random_bytes(4));
            $generated = true;
        }
        $hash = password_hash($rawPassword, PASSWORD_DEFAULT);

        if ($adminRow) {
            $upd = $dbh->prepare('UPDATE admin SET Password = :p WHERE id = :id');
            $upd->execute([':p' => $hash, ':id' => $adminRow->id]);
        } else {
            $ins = $dbh->prepare('INSERT INTO admin (UserName, Password) VALUES (:u, :p)');
            $ins->execute([':u' => 'admin', ':p' => $hash]);
        }

        echo "[+] Admin account 'admin' configured.\n";
        if ($generated) {
            echo "[!] Generated one-time dev password for 'admin': {$rawPassword}\n";
            echo "    Change it immediately in Admin -> Change Password.\n";
        }
    } else {
        echo "[=] Admin account already configured.\n";
    }

    // 2. Seed contact info if empty
    $contactCount = (int) $dbh->query('SELECT COUNT(*) FROM tblcontactusinfo')->fetchColumn();
    if ($contactCount === 0) {
        $stmt = $dbh->prepare('INSERT INTO tblcontactusinfo (Address, EmailId, ContactNo) VALUES (:addr, :email, :phone)');
        $stmt->execute([
            ':addr' => 'GS Road, Guwahati, Assam 781005',
            ':email' => 'support@drivexassam.com',
            ':phone' => '+91 9876543210',
        ]);
        echo "[+] Seeded default contact info.\n";
    }

    // 3. Seed CMS pages if missing
    $defaultPages = [
        'aboutus' => [
            'About Us',
            '<p><strong>DriveX Assam</strong> provides reliable self-drive and chauffeur-assisted car rentals across Guwahati, Kaziranga, Shillong, and Northeast India. Every vehicle undergoes multi-point inspection prior to pickup.</p>',
        ],
        'privacy' => [
            'Privacy Policy',
            '<p>We collect only the contact, identity, and booking details required to fulfill your rental reservation. Passwords are stored using modern cryptographic hashing and never shared with third parties.</p>',
        ],
        'terms' => [
            'Terms and Conditions',
            '<p>Renters must carry a valid driving license and government photo ID at pickup. Cancellations made at least 24 hours before pickup receive a full refund of redeemed loyalty tokens; standard cancellation fees apply otherwise.</p>',
        ],
        'faqs' => [
            'Frequently Asked Questions',
            '<p><strong>How do loyalty tokens work?</strong> Earn 5 tokens after every completed trip. Redeem 15 or more tokens (in multiples of 5) where 5 tokens = ₹100 discount.</p>',
        ],
    ];

    foreach ($defaultPages as $type => [$name, $detail]) {
        $chk = $dbh->prepare('SELECT id FROM tblpages WHERE type = :t LIMIT 1');
        $chk->execute([':t' => $type]);
        if (!$chk->fetch()) {
            $ins = $dbh->prepare('INSERT INTO tblpages (PageName, type, detail) VALUES (:n, :t, :d)');
            $ins->execute([':n' => $name, ':t' => $type, ':d' => $detail]);
            echo "[+] Seeded CMS page '{$type}'.\n";
        }
    }

    // Ensure Toyota brand exists for Fortuner seed record
    $toyStmt = $dbh->prepare('SELECT id FROM tblbrands WHERE BrandName = :b LIMIT 1');
    $toyStmt->execute([':b' => 'Toyota']);
    $toyotaId = (int) ($toyStmt->fetchColumn() ?: 0);
    if ($toyotaId === 0) {
        $dbh->prepare('INSERT INTO tblbrands (BrandName) VALUES (:b)')->execute([':b' => 'Toyota']);
        $toyotaId = (int) $dbh->lastInsertId();
    }

    // 4. Seed sample vehicles if empty (using images already committed in admin/img/vehicleimages/)
    $vehCount = (int) $dbh->query('SELECT COUNT(*) FROM tblvehicles')->fetchColumn();
    if ($vehCount === 0) {
        $sampleVehicles = [
            ['Vitara Brezza ZDi+', 1, 'Compact SUV ideal for both Guwahati city commutes and Meghalaya highway drives with strong mileage and generous boot space.', 2400, 'Diesel', 2023, 5, 'Manual', 'brezza-exterior-right-front-three-quarter-6.webp', 'brezza-exterior-right-side-view.webp', 'brezza-exterior-right-rear-three-quarter.webp', 'brezza-interior-bootspace.webp', 'brezzamarutisuzukibrezzafrontrowseats.webp'],
            ['Fronx Turbo Alpha', 1, 'Sleek crossover with responsive turbo petrol engine, comfortable 5-seat cabin, and modern safety suite.', 2200, 'Petrol', 2024, 5, 'Automatic', 'fronxmarutisuzukifronxrightfrontthreequarter1.webp', 'fronxfronxleftsideview.webp', 'fronxfronxbootspace.webp', 'fronxmarutisuzukifronxrearseats.webp', ''],
            ['XUV300 W8 Optional', 2, '5-star safety rated compact SUV with dual airbags, ABS, crash sensors, and refined highway handling.', 2600, 'Diesel', 2023, 5, 'Manual', 'xuv300[20192024]xuv300rightfrontthreequarter.webp', 'xuv300[20192024]rightfrontthreequarter1.webp', 'xuv300[20192024]rightfrontthreequarter6.webp', 'xuv300[20192024]rightfrontthreequarter42.webp', ''],
            ['Punch Creative', 3, 'High ground-clearance urban micro-SUV built for Assam roads and weekend getaways.', 1800, 'Petrol', 2024, 5, 'Manual', 'punchpunchrightfrontthreequarter.webp', 'punchtigorleftsideview.webp', 'punchpunchrearseats.webp', 'punchtatapunchbootreleaseleverfuellidreleaselever17.webp', ''],
            ['Fortuner Legender 4x4', $toyotaId, 'Full-size 7-seater touring SUV suited for family trips to Kaziranga, Tawang, and hill terrain.', 4800, 'Diesel', 2023, 7, 'Automatic', '2015_Toyota_Fortuner_(New_Zealand).jpg', 'toyota-fortuner-legender-rear-quarters-6e57.jpg', 'zw-toyota-fortuner-2020-2.jpg', 'boot-with-standard-luggage-202327489_930x620.jpg', ''],
            ['WagonR VXi', 1, 'Economical, easy-to-park hatchback with tall-boy headroom and practical luggage capacity.', 1400, 'CNG', 2023, 5, 'Manual', 'wagonrwagonrrightfrontthreequarter (1).webp', 'wagonrwagonrleftsideview (1).webp', 'wagonrwagonrrightrearthreequarter.webp', 'wagonrmarutisuzukiwagonropenboottrunk25.webp', ''],
        ];

        $insVeh = $dbh->prepare(
            'INSERT INTO tblvehicles (VehiclesTitle, VehiclesBrand, VehiclesOverview, PricePerDay, FuelType, ModelYear, SeatingCapacity, Transmission, Vimage1, Vimage2, Vimage3, Vimage4, Vimage5, AirConditioner, PowerDoorLocks, AntiLockBrakingSystem, BrakeAssist, PowerSteering, DriverAirbag, PassengerAirbag, PowerWindows, CDPlayer, CentralLocking, CrashSensor, LeatherSeats)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1, 1, 1, 1, 1, 1, 0, 1, 1, 0)'
        );
        foreach ($sampleVehicles as $v) {
            $insVeh->execute($v);
        }
        echo "[+] Seeded 6 sample vehicles.\n";
    }

    echo "Seed completed successfully.\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Seed failed: ' . $e->getMessage() . "\n");
    exit(1);
}
