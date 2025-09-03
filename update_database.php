<?php
// Database update script for payment functionality
include('includes/config.php');

try {
    // Add payment-related columns to tblbooking table if they don't exist
    $columns_to_add = [
        'PaymentMethod' => 'varchar(50) DEFAULT NULL',
        'PaymentStatus' => 'varchar(20) DEFAULT "pending"',
        'PaymentDate' => 'timestamp NULL DEFAULT NULL',
        'Amount' => 'decimal(10,2) DEFAULT 0.00',
        'DiscountAmount' => 'decimal(10,2) DEFAULT 0.00',
        'FinalAmount' => 'decimal(10,2) DEFAULT 0.00'
    ];

    foreach ($columns_to_add as $column_name => $column_definition) {
        // Check if column exists
        $sql = "SHOW COLUMNS FROM tblbooking LIKE '$column_name'";
        $result = $dbh->query($sql);

        if (!$result->fetch()) {
            // Add column if it doesn't exist
            $sql = "ALTER TABLE tblbooking ADD COLUMN $column_name $column_definition";
            $dbh->exec($sql);
            echo "Added column: $column_name<br>";
        } else {
            echo "Column already exists: $column_name<br>";
        }
    }

    // Create payment transactions table
    $sql = "CREATE TABLE IF NOT EXISTS tblpaymenttransactions (
        id int(11) NOT NULL AUTO_INCREMENT,
        booking_id bigint(12) NOT NULL,
        user_email varchar(100) NOT NULL,
        payment_method varchar(50) NOT NULL,
        amount decimal(10,2) NOT NULL,
        transaction_id varchar(100) DEFAULT NULL,
        payment_status varchar(20) DEFAULT 'pending',
        gateway_response text,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY booking_id (booking_id),
        KEY user_email (user_email)
    )";
    $dbh->exec($sql);
    echo "Created payment transactions table<br>";

    echo "<br>Database update completed successfully!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}

