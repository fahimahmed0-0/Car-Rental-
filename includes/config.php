<?php
// DB credentials - Using localhost settings for local development
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'carrental');
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

    // Add tokens and total_tokens_earned columns to tblusers if they don't exist
    $sql = "SHOW COLUMNS FROM tblusers LIKE 'tokens'";
    $result = $dbh->query($sql);
    if (!$result->fetch()) {
        $dbh->exec("ALTER TABLE tblusers ADD COLUMN tokens int(11) NOT NULL DEFAULT 0");
    }

    $sql = "SHOW COLUMNS FROM tblusers LIKE 'total_tokens_earned'";
    $result = $dbh->query($sql);
    if (!$result->fetch()) {
        $dbh->exec("ALTER TABLE tblusers ADD COLUMN total_tokens_earned int(11) NOT NULL DEFAULT 0");
    }

    // Create token-related tables if they don't exist
    $sql = "CREATE TABLE IF NOT EXISTS tbltokenearnings (
        id int(11) NOT NULL AUTO_INCREMENT,
        userEmail varchar(100) NOT NULL,
        bookingId int(11) NOT NULL,
        tokensEarned int(11) NOT NULL,
        dateEarned timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_booking (bookingId)
    )";
    $dbh->exec($sql);

    $sql = "CREATE TABLE IF NOT EXISTS tblcustomertokens (
        id int(11) NOT NULL AUTO_INCREMENT,
        userEmail varchar(100) NOT NULL,
        tokenBalance int(11) NOT NULL DEFAULT 0,
        totalEarnedTokens int(11) NOT NULL DEFAULT 0,
        lastUpdated timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_user (userEmail)
    )";
    $dbh->exec($sql);

    $sql = "CREATE TABLE IF NOT EXISTS tbltokenredemption (
        id int(11) NOT NULL AUTO_INCREMENT,
        userEmail varchar(100) NOT NULL,
        tokensRedeemed int(11) NOT NULL,
        discountAmount decimal(10,2) NOT NULL,
        bookingId int(11) NOT NULL,
        redemptionDate timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    )";
    $dbh->exec($sql);
} catch (PDOException $e) {
    exit("Error: " . $e->getMessage());
}
