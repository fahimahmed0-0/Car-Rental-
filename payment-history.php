<?php
session_start();
error_reporting(0);
include('includes/config.php');
include('includes/payment-gateway.php');

// Check if user is logged in
if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit();
}

// Initialize payment gateway
$paymentGateway = new PaymentGateway($dbh);

// Get user's payment history
$paymentHistory = $paymentGateway->getUserPaymentHistory($_SESSION['login'], 50);
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <title>Car Rental Portal | Payment History</title>
    <!--Bootstrap -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css" type="text/css">
    <!--Custome Style -->
    <link rel="stylesheet" href="assets/css/style.css" type="text/css">
    <!--OWL Carousel slider-->
    <link rel="stylesheet" href="assets/css/owl.carousel.css" type="text/css">
    <link rel="stylesheet" href="assets/css/owl.transitions.css" type="text/css">
    <!--slick-slider -->
    <link href="assets/css/slick.css" rel="stylesheet">
    <!--bootstrap-slider -->
    <link href="assets/css/bootstrap-slider.min.css" rel="stylesheet">
    <!--FontAwesome Font Style -->
    <link href="assets/css/font-awesome.min.css" rel="stylesheet">

    <style>
        .history-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 40px 0;
        }

        .history-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .transaction-item {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .transaction-item:hover {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .payment-method-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .payment-method-upi {
            background: #e3f2fd;
            color: #1976d2;
        }

        .payment-method-card {
            background: #f3e5f5;
            color: #7b1fa2;
        }

        .payment-method-wallet {
            background: #e8f5e8;
            color: #388e3c;
        }

        .payment-method-netbanking {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-completed {
            background: #e8f5e8;
            color: #388e3c;
        }

        .status-pending {
            background: #fff3e0;
            color: #f57c00;
        }

        .status-failed {
            background: #ffebee;
            color: #d32f2f;
        }

        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #1976d2;
        }

        .transaction-id {
            font-family: monospace;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 12px;
        }
    </style>
</head>

<body>
    <!--Header-->
    <?php include('includes/header.php'); ?>
    <!-- /Header -->

    <div class="history-container">
        <div class="container">
            <div class="history-card">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fa fa-history"></i> Payment History</h2>
                    <a href="my-booking.php" class="btn btn-outline-primary">
                        <i class="fa fa-list"></i> View Bookings
                    </a>
                </div>

                <?php if (empty($paymentHistory)): ?>
                    <div class="text-center py-5">
                        <i class="fa fa-credit-card fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No Payment History</h4>
                        <p class="text-muted">You haven't made any payments yet.</p>
                        <a href="car-listing.php" class="btn btn-primary">
                            <i class="fa fa-car"></i> Rent a Car
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($paymentHistory as $transaction): ?>
                            <div class="col-md-6 mb-3">
                                <div class="transaction-item">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlentities($transaction->VehiclesTitle); ?></h6>
                                            <small class="text-muted">Booking #<?php echo $transaction->booking_id; ?></small>
                                        </div>
                                        <div class="text-right">
                                            <div class="amount">₹<?php echo number_format($transaction->amount, 2); ?></div>
                                            <span class="status-badge status-<?php echo $transaction->payment_status; ?>">
                                                <?php echo ucfirst($transaction->payment_status); ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row mb-2">
                                        <div class="col-6">
                                            <small class="text-muted">Payment Method:</small><br>
                                            <span class="payment-method-badge payment-method-<?php echo $transaction->payment_method; ?>">
                                                <?php echo ucfirst($transaction->payment_method); ?>
                                            </span>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted">Date:</small><br>
                                            <small><?php echo date('d M Y, h:i A', strtotime($transaction->created_at)); ?></small>
                                        </div>
                                    </div>

                                    <div class="mb-2">
                                        <small class="text-muted">Transaction ID:</small><br>
                                        <span class="transaction-id"><?php echo $transaction->transaction_id; ?></span>
                                    </div>

                                    <?php if ($transaction->gateway_response): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">Gateway Response:</small><br>
                                            <small><?php echo htmlentities(substr($transaction->gateway_response, 0, 100)); ?>...</small>
                                        </div>
                                    <?php endif; ?>

                                    <div class="text-right">
                                        <a href="my-booking.php" class="btn btn-sm btn-outline-primary">
                                            <i class="fa fa-eye"></i> View Booking
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Payment Statistics -->
                    <div class="row mt-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count($paymentHistory); ?></h4>
                                    <p class="mb-0">Total Transactions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h4>₹<?php echo number_format(array_sum(array_column($paymentHistory, 'amount')), 2); ?></h4>
                                    <p class="mb-0">Total Amount Paid</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h4><?php echo count(array_filter($paymentHistory, function ($t) {
                                            return $t->payment_status == 'completed';
                                        })); ?></h4>
                                    <p class="mb-0">Successful Payments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!--Footer -->
    <?php include('includes/footer.php'); ?>
    <!-- /Footer-->

    <!-- Scripts -->
    <script src="assets/js/jquery.min.js"></script>
    <script src="assets/js/bootstrap.min.js"></script>
    <script src="assets/js/interface.js"></script>
</body>

</html>

