-- DriveX Assam Database Schema Template
-- Compatible with MariaDB 10.4+ / MySQL 8.0+

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------
-- Table: `admin`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `UserName` varchar(100) NOT NULL,
  `Password` varchar(255) NOT NULL,
  `updationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_admin_username` (`UserName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblbrands`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblbrands` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `BrandName` varchar(120) NOT NULL,
  `CreationDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_brand_name` (`BrandName`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `tblbrands` (`id`, `BrandName`) VALUES
(1, 'Maruti'),
(2, 'Mahindra'),
(3, 'TATA'),
(4, 'Nissan'),
(5, 'Hyundai'),
(7, 'Volkswagen');

-- --------------------------------------------------------
-- Table: `tblusers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblusers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `FullName` varchar(120) DEFAULT NULL,
  `EmailId` varchar(100) DEFAULT NULL,
  `Password` varchar(255) DEFAULT NULL,
  `ContactNo` varchar(20) DEFAULT NULL,
  `dob` varchar(100) DEFAULT NULL,
  `Address` varchar(255) DEFAULT NULL,
  `City` varchar(100) DEFAULT NULL,
  `Country` varchar(100) DEFAULT NULL,
  `Role` varchar(20) NOT NULL DEFAULT 'customer',
  `TokenBalance` int(11) NOT NULL DEFAULT 0,
  `TotalTokensEarned` int(11) NOT NULL DEFAULT 0,
  `RegDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_users_email` (`EmailId`),
  KEY `idx_users_contact` (`ContactNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblvehicles`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblvehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `VehiclesTitle` varchar(150) DEFAULT NULL,
  `VehiclesBrand` int(11) DEFAULT NULL,
  `VehiclesOverview` longtext DEFAULT NULL,
  `PricePerDay` int(11) DEFAULT NULL,
  `FuelType` varchar(100) DEFAULT NULL,
  `ModelYear` int(6) DEFAULT NULL,
  `SeatingCapacity` int(11) DEFAULT NULL,
  `Transmission` varchar(50) NOT NULL DEFAULT 'Manual',
  `Vimage1` varchar(500) DEFAULT NULL,
  `Vimage2` varchar(500) DEFAULT NULL,
  `Vimage3` varchar(500) DEFAULT NULL,
  `Vimage4` varchar(500) DEFAULT NULL,
  `Vimage5` varchar(500) DEFAULT NULL,
  `AirConditioner` tinyint(1) DEFAULT 0,
  `PowerDoorLocks` tinyint(1) DEFAULT 0,
  `AntiLockBrakingSystem` tinyint(1) DEFAULT 0,
  `BrakeAssist` tinyint(1) DEFAULT 0,
  `PowerSteering` tinyint(1) DEFAULT 0,
  `DriverAirbag` tinyint(1) DEFAULT 0,
  `PassengerAirbag` tinyint(1) DEFAULT 0,
  `PowerWindows` tinyint(1) DEFAULT 0,
  `CDPlayer` tinyint(1) DEFAULT 0,
  `CentralLocking` tinyint(1) DEFAULT 0,
  `CrashSensor` tinyint(1) DEFAULT 0,
  `LeatherSeats` tinyint(1) DEFAULT 0,
  `RegDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `UpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vehicles_brand` (`VehiclesBrand`),
  KEY `idx_vehicles_fuel_price` (`FuelType`, `PricePerDay`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblbooking`
-- Status codes: 0=Pending, 1=Confirmed, 2=Cancelled, 3=Completed, 4=Active, 5=Expired
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblbooking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `BookingNumber` bigint(12) DEFAULT NULL,
  `userEmail` varchar(100) DEFAULT NULL,
  `VehicleId` int(11) DEFAULT NULL,
  `FromDate` varchar(20) DEFAULT NULL,
  `ToDate` varchar(20) DEFAULT NULL,
  `message` varchar(255) DEFAULT NULL,
  `Status` int(11) NOT NULL DEFAULT 0,
  `PaymentMethod` varchar(50) DEFAULT NULL,
  `PaymentStatus` varchar(20) NOT NULL DEFAULT 'pending',
  `PaymentDate` timestamp NULL DEFAULT NULL,
  `Amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `DiscountAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `TaxAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `FinalAmount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `TokensUsed` int(11) NOT NULL DEFAULT 0,
  `PriceLockUntil` timestamp NULL DEFAULT NULL,
  `PickupLocation` varchar(150) DEFAULT 'DriveX Hub, Guwahati, Assam',
  `DropoffLocation` varchar(150) DEFAULT 'DriveX Hub, Guwahati, Assam',
  `PostingDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `LastUpdationDate` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_number` (`BookingNumber`),
  KEY `idx_booking_vehicle_status` (`VehicleId`, `Status`),
  KEY `idx_booking_user` (`userEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblcontactusinfo`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblcontactusinfo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Address` text DEFAULT NULL,
  `EmailId` varchar(255) DEFAULT NULL,
  `ContactNo` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblcontactusquery`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblcontactusquery` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `EmailId` varchar(120) DEFAULT NULL,
  `ContactNumber` varchar(20) DEFAULT NULL,
  `Message` longtext DEFAULT NULL,
  `PostingDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` int(11) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblpages`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblpages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `PageName` varchar(255) DEFAULT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `detail` longtext NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_page_type` (`type`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblsubscribers`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblsubscribers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `SubscriberEmail` varchar(120) DEFAULT NULL,
  `PostingDate` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_subscriber_email` (`SubscriberEmail`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tbltestimonial`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbltestimonial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `UserEmail` varchar(100) NOT NULL,
  `Testimonial` mediumtext NOT NULL,
  `PostingDate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `status` int(11) DEFAULT 0,
  `AiModerationFlag` varchar(20) DEFAULT 'clean',
  PRIMARY KEY (`id`),
  KEY `idx_testimonial_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tbltokenledger` (Immutable loyalty ledger)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tbltokenledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_email` varchar(100) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `booking_number` bigint(12) DEFAULT NULL,
  `entry_type` varchar(32) NOT NULL COMMENT 'earn_completion|redeem|refund_cancel|admin_adjust',
  `delta` int(11) NOT NULL,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_entry` (`booking_id`, `entry_type`),
  KEY `idx_ledger_email` (`user_email`),
  KEY `idx_ledger_booking_num` (`booking_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblpaymenttransactions`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblpaymenttransactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_id` bigint(12) NOT NULL,
  `user_email` varchar(100) NOT NULL,
  `provider` varchar(30) NOT NULL DEFAULT 'sandbox',
  `payment_method` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `idempotency_key` varchar(100) DEFAULT NULL,
  `gateway_order_id` varchar(100) DEFAULT NULL,
  `gateway_signature` varchar(255) DEFAULT NULL,
  `payment_status` varchar(20) NOT NULL DEFAULT 'pending',
  `gateway_response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_txn_id` (`transaction_id`),
  UNIQUE KEY `uniq_idempotency` (`idempotency_key`),
  KEY `idx_pt_booking` (`booking_id`),
  KEY `idx_pt_user` (`user_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblpasswordresets`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblpasswordresets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pr_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblmailoutbox` (SMTP fallback queue)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblmailoutbox` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recipient` varchar(150) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body` mediumtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'queued_fallback',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblailogs` & `tblaicache`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblailogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `feature` varchar(50) NOT NULL,
  `tier_used` varchar(30) NOT NULL,
  `provider` varchar(40) NOT NULL,
  `latency_ms` int(11) NOT NULL DEFAULT 0,
  `success` tinyint(1) NOT NULL DEFAULT 1,
  `error_note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_feature` (`feature`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tblaicache` (
  `cache_key` char(64) NOT NULL,
  `feature` varchar(50) NOT NULL,
  `response_json` mediumtext NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblratelimits`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblratelimits` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `bucket_key` varchar(120) NOT NULL,
  `attempt_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rl_bucket` (`bucket_key`, `attempt_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table: `tblsessions` (Serverless DB session store)
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tblsessions` (
  `id` varchar(128) NOT NULL,
  `data` mediumtext NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_sess_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
