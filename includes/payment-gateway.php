<?php
// Payment Gateway Integration
class PaymentGateway
{
    private $dbh;

    public function __construct($dbh)
    {
        $this->dbh = $dbh;
    }

    // Process payment based on selected method
    public function processPayment($booking_id, $user_email, $amount, $payment_method)
    {
        try {
            // Generate transaction ID
            $transaction_id = $this->generateTransactionId();

            // Store transaction record
            $sql = "INSERT INTO tblpaymenttransactions (booking_id, user_email, payment_method, amount, transaction_id, payment_status) 
                    VALUES (:booking_id, :user_email, :payment_method, :amount, :transaction_id, 'pending')";
            $query = $this->dbh->prepare($sql);
            $query->bindParam(':booking_id', $booking_id, PDO::PARAM_STR);
            $query->bindParam(':user_email', $user_email, PDO::PARAM_STR);
            $query->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
            $query->bindParam(':amount', $amount, PDO::PARAM_STR);
            $query->bindParam(':transaction_id', $transaction_id, PDO::PARAM_STR);
            $query->execute();

            // Process based on payment method
            switch ($payment_method) {
                case 'upi':
                    return $this->processUPIPayment($transaction_id, $amount);
                case 'card':
                    return $this->processCardPayment($transaction_id, $amount);
                case 'wallet':
                    return $this->processWalletPayment($transaction_id, $amount);
                case 'netbanking':
                    return $this->processNetBankingPayment($transaction_id, $amount);
                default:
                    return ['success' => false, 'message' => 'Invalid payment method'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Payment processing error: ' . $e->getMessage()];
        }
    }

    // Process UPI Payment
    private function processUPIPayment($transaction_id, $amount)
    {
        // In real implementation, this would integrate with UPI gateway
        // For demo purposes, we'll simulate a successful payment

        $upi_data = [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'upi_id' => 'demo@upi',
            'status' => 'success',
            'response_code' => '00',
            'response_message' => 'Transaction successful'
        ];

        $this->updateTransactionStatus($transaction_id, 'completed', json_encode($upi_data));

        return [
            'success' => true,
            'message' => 'UPI payment processed successfully',
            'transaction_id' => $transaction_id,
            'gateway_data' => $upi_data
        ];
    }

    // Process Card Payment
    private function processCardPayment($transaction_id, $amount)
    {
        // In real implementation, this would integrate with card payment gateway
        // For demo purposes, we'll simulate a successful payment

        $card_data = [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'card_type' => 'VISA',
            'card_last4' => '1234',
            'status' => 'success',
            'response_code' => '00',
            'response_message' => 'Transaction successful'
        ];

        $this->updateTransactionStatus($transaction_id, 'completed', json_encode($card_data));

        return [
            'success' => true,
            'message' => 'Card payment processed successfully',
            'transaction_id' => $transaction_id,
            'gateway_data' => $card_data
        ];
    }

    // Process Wallet Payment
    private function processWalletPayment($transaction_id, $amount)
    {
        // In real implementation, this would integrate with wallet payment gateway
        // For demo purposes, we'll simulate a successful payment

        $wallet_data = [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'wallet_type' => 'Paytm',
            'status' => 'success',
            'response_code' => '00',
            'response_message' => 'Transaction successful'
        ];

        $this->updateTransactionStatus($transaction_id, 'completed', json_encode($wallet_data));

        return [
            'success' => true,
            'message' => 'Wallet payment processed successfully',
            'transaction_id' => $transaction_id,
            'gateway_data' => $wallet_data
        ];
    }

    // Process Net Banking Payment
    private function processNetBankingPayment($transaction_id, $amount)
    {
        // In real implementation, this would integrate with net banking gateway
        // For demo purposes, we'll simulate a successful payment

        $netbanking_data = [
            'transaction_id' => $transaction_id,
            'amount' => $amount,
            'bank_name' => 'State Bank of India',
            'status' => 'success',
            'response_code' => '00',
            'response_message' => 'Transaction successful'
        ];

        $this->updateTransactionStatus($transaction_id, 'completed', json_encode($netbanking_data));

        return [
            'success' => true,
            'message' => 'Net banking payment processed successfully',
            'transaction_id' => $transaction_id,
            'gateway_data' => $netbanking_data
        ];
    }

    // Update transaction status
    private function updateTransactionStatus($transaction_id, $status, $gateway_response = null)
    {
        $sql = "UPDATE tblpaymenttransactions SET payment_status = :status, gateway_response = :gateway_response WHERE transaction_id = :transaction_id";
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':status', $status, PDO::PARAM_STR);
        $query->bindParam(':gateway_response', $gateway_response, PDO::PARAM_STR);
        $query->bindParam(':transaction_id', $transaction_id, PDO::PARAM_STR);
        $query->execute();
    }

    // Generate unique transaction ID
    private function generateTransactionId()
    {
        return 'TXN' . date('YmdHis') . rand(1000, 9999);
    }

    // Get payment methods available
    public function getAvailablePaymentMethods()
    {
        return [
            'upi' => [
                'name' => 'UPI',
                'icon' => 'fa-bolt',
                'description' => 'Google Pay, PhonePe, BHIM UPI',
                'enabled' => true
            ],
            'card' => [
                'name' => 'Credit / Debit / ATM Card',
                'icon' => 'fa-credit-card',
                'description' => 'Please ensure your card is enabled for online transactions',
                'enabled' => true
            ],
            'wallet' => [
                'name' => 'Mobile Wallet',
                'icon' => 'fa-wallet',
                'description' => 'All major wallets are supported',
                'enabled' => true
            ],
            'netbanking' => [
                'name' => 'Net Banking',
                'icon' => 'fa-university',
                'description' => 'All major banks are supported',
                'enabled' => true
            ]
        ];
    }

    // Calculate booking amount (you can customize this based on your pricing logic)
    public function calculateBookingAmount($vehicle_id, $from_date, $to_date, $discount_amount = 0)
    {
        // Get vehicle daily rate
        $sql = "SELECT PricePerDay FROM tblvehicles WHERE id = :vehicle_id";
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_STR);
        $query->execute();
        $vehicle = $query->fetch(PDO::FETCH_OBJ);

        if (!$vehicle) {
            return 0;
        }

        // Calculate number of days
        $from = new DateTime($from_date);
        $to = new DateTime($to_date);
        $days = $to->diff($from)->days;

        // Base amount (using daily rate)
        $base_amount = $vehicle->PricePerDay * $days;

        // Apply discount
        $final_amount = $base_amount - $discount_amount;

        return max(0, $final_amount); // Ensure amount is not negative
    }

    // Get transaction details
    public function getTransactionDetails($transaction_id)
    {
        $sql = "SELECT * FROM tblpaymenttransactions WHERE transaction_id = :transaction_id";
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':transaction_id', $transaction_id, PDO::PARAM_STR);
        $query->execute();
        return $query->fetch(PDO::FETCH_OBJ);
    }

    // Get user's payment history
    public function getUserPaymentHistory($user_email, $limit = 10)
    {
        $sql = "SELECT pt.*, b.BookingNumber, v.VehiclesTitle 
                FROM tblpaymenttransactions pt 
                JOIN tblbooking b ON pt.booking_id = b.BookingNumber 
                JOIN tblvehicles v ON b.VehicleId = v.id 
                WHERE pt.user_email = :user_email 
                ORDER BY pt.created_at DESC 
                LIMIT :limit";
        $query = $this->dbh->prepare($sql);
        $query->bindParam(':user_email', $user_email, PDO::PARAM_STR);
        $query->bindParam(':limit', $limit, PDO::PARAM_INT);
        $query->execute();
        return $query->fetchAll(PDO::FETCH_OBJ);
    }
}
