<?php
// DB credentials template - DO NOT add real credentials to this file
// Copy this file to config.php and add your credentials there

// Check if environment variables are set, otherwise use defaults for local development
define('DB_HOST', getenv('DB_HOST') ? getenv('DB_HOST') : 'localhost');
define('DB_USER', getenv('DB_USER') ? getenv('DB_USER') : '');
define('DB_PASS', getenv('DB_PASS') ? getenv('DB_PASS') : '');
define('DB_NAME', getenv('DB_NAME') ? getenv('DB_NAME') : 'carrental');

// Establish database connection.
try {
    $dbh = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8", DB_USER, DB_PASS);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create tblredemptions table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS tblredemptions (
        id int(11) NOT NULL AUTO_INCREMENT,
        userEmail varchar(100) NOT NULL,
        TokensRedeemed int(11) NOT NULL,
        DiscountAmount decimal(10,2) NOT NULL,
        BookingID int(11) NOT NULL,
        RedemptionDate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )";
    $dbh->exec($sql);

    // Create tbltokens table if it doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS tbltokens (
        id int(11) NOT NULL AUTO_INCREMENT,
        userEmail varchar(100) NOT NULL,
        TokenBalance int(11) NOT NULL DEFAULT 0,
        LastUpdated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY userEmail (userEmail)
    )";
    $dbh->exec($sql);

} catch (PDOException $e) {
    exit("Error: " . $e->getMessage());
}