<?php
session_start();
error_reporting(0);
include('includes/config.php');

// Check if user is logged in
if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit();
}

// Get booking details from session or URL parameters
$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : (isset($_SESSION['current_booking_id']) ? $_SESSION['current_booking_id'] : null);
$amount = isset($_GET['amount']) ? $_GET['amount'] : (isset($_SESSION['current_booking_amount']) ? $_SESSION['current_booking_amount'] : 0);

if (!$booking_id || !$amount) {
    header('location:my-booking.php');
    exit();
}

// Get booking details
$sql = "SELECT b.*, v.VehiclesTitle, v.VehiclesBrand, v.FuelType, v.ModelYear, v.SeatingCapacity, v.Vimage1 
        FROM tblbooking b 
        JOIN tblvehicles v ON b.VehicleId = v.id 
        WHERE b.BookingNumber = :booking_id";
$query = $dbh->prepare($sql);
$query->bindParam(':booking_id', $booking_id, PDO::PARAM_STR);
$query->execute();
$booking = $query->fetch(PDO::FETCH_OBJ);

if (!$booking) {
    header('location:my-booking.php');
    exit();
}

// Process payment
if (isset($_POST['process_payment'])) {
    $payment_method = $_POST['payment_method'];

    if (empty($payment_method)) {
        $error = "Please select a payment method.";
    } else {
        // Include payment gateway
        include('includes/payment-gateway.php');
        $paymentGateway = new PaymentGateway($dbh);

        // Process payment through gateway
        $result = $paymentGateway->processPayment($booking_id, $_SESSION['login'], $amount, $payment_method);

        if ($result['success']) {
            // Update booking with payment information
            $sql = "UPDATE tblbooking SET Status = 1, PaymentMethod = :payment_method, PaymentStatus = 'completed', PaymentDate = NOW() WHERE BookingNumber = :booking_id";
            $query = $dbh->prepare($sql);
            $query->bindParam(':payment_method', $payment_method, PDO::PARAM_STR);
            $query->bindParam(':booking_id', $booking_id, PDO::PARAM_STR);

            if ($query->execute()) {
                // Process token redemption if applicable
                $sql = "SELECT tr.* FROM tbltokenredemption tr WHERE tr.bookingId = :booking_id";
                $query = $dbh->prepare($sql);
                $query->bindParam(':booking_id', $booking_id, PDO::PARAM_STR);
                $query->execute();
                $redemption = $query->fetch(PDO::FETCH_OBJ);

                if ($redemption) {
                    // Deduct tokens from user account
                    $sql = "UPDATE tblcustomertokens SET tokenBalance = tokenBalance - :tokens WHERE userEmail = :email";
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':tokens', $redemption->tokensRedeemed, PDO::PARAM_INT);
                    $query->bindParam(':email', $_SESSION['login'], PDO::PARAM_STR);
                    $query->execute();
                }

                // Redirect to success page
                $_SESSION['payment_success'] = true;
                header('location:payment-success.php?booking_id=' . $booking_id);
                exit();
            } else {
                $error = "Payment processing failed. Please try again.";
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Calculate price lock expiry time (10 minutes from now)
$price_lock_expiry = date('H:i:s', strtotime('+10 minutes'));
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <title>Car Rental Portal | Payment</title>
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
        .payment-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 20px 0;
        }

        .payment-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .price-lock-banner {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #333;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin: 15px 0;
            font-weight: bold;
        }

        .timer {
            background: #333;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: bold;
        }

        .payment-methods {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .payment-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .payment-option:hover {
            border-color: #007bff;
            background: #f8f9fa;
        }

        .payment-option.selected {
            border-color: #007bff;
            background: #e3f2fd;
        }

        .payment-option input[type="radio"] {
            margin-right: 10px;
        }

        .payment-icon {
            width: 40px;
            height: 40px;
            background: #f8f9fa;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 20px;
        }

        .qr-section {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .qr-code {
            width: 200px;
            height: 200px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 20px auto;
            font-size: 60px;
            color: #6c757d;
        }

        .booking-details {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .car-image {
            width: 100%;
            max-width: 200px;
            border-radius: 8px;
            margin: 10px 0;
        }

        .location-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 10px;
        }

        .pickup-dot {
            background: #28a745;
        }

        .dropoff-dot {
            background: #dc3545;
        }

        .final-amount {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            font-size: 24px;
            font-weight: bold;
        }

        .payment-logos {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .payment-logo {
            font-size: 14px;
            font-weight: bold;
            color: #6c757d;
        }

        .upi-apps {
            display: flex;
            justify-content: space-around;
            margin-top: 15px;
        }

        .upi-app {
            text-align: center;
            font-size: 12px;
            color: #6c757d;
        }

        .upi-app i {
            font-size: 24px;
            margin-bottom: 5px;
            display: block;
        }
    </style>
</head>

<body>
    <!--Header-->
    <?php include('includes/header.php'); ?>
    <!-- /Header -->

    <div class="payment-container">
        <div class="container">
            <div class="row">
                <!-- Payment Methods Column -->
                <div class="col-md-8">
                    <!-- Payment Header -->
                    <div class="payment-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h4><i class="fa fa-arrow-left"></i> Pay ₹<?php echo number_format($amount, 2); ?></h4>
                                <p class="text-muted">
                                    <?php echo date('D, d M, h:i A', strtotime($booking->FromDate)); ?> →
                                    <?php echo date('D, d M, h:i A', strtotime($booking->ToDate)); ?>
                                </p>
                            </div>
                            <div class="col-md-6 text-right">
                                <div class="price-lock-banner">
                                    <div>PRICE LOCKED FOR 10 MINS</div>
                                    <small>Complete your payment soon</small>
                                </div>
                                <div class="timer" id="timer">10:00</div>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Methods -->
                    <div class="payment-methods">
                        <h5>Select a Payment method</h5>
                        <form method="post" id="payment-form">
                            <div class="payment-option" onclick="selectPayment('upi')">
                                <input type="radio" name="payment_method" value="upi" id="upi" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon">
                                        <i class="fa fa-bolt"></i>
                                    </div>
                                    <div>
                                        <strong>UPI</strong><br>
                                        <small class="text-muted">Google Pay, PhonePe, BHIM UPI</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-option" onclick="selectPayment('card')">
                                <input type="radio" name="payment_method" value="card" id="card" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon">
                                        <i class="fa fa-credit-card"></i>
                                    </div>
                                    <div>
                                        <strong>Credit / Debit / ATM Card</strong><br>
                                        <small class="text-muted">Please ensure your card is enabled for online transactions</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-option" onclick="selectPayment('wallet')">
                                <input type="radio" name="payment_method" value="wallet" id="wallet" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon">
                                        <i class="fa fa-wallet"></i>
                                    </div>
                                    <div>
                                        <strong>Mobile Wallet</strong><br>
                                        <small class="text-muted">All major wallets are supported</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-option" onclick="selectPayment('netbanking')">
                                <input type="radio" name="payment_method" value="netbanking" id="netbanking" style="display: none;">
                                <div class="d-flex align-items-center">
                                    <div class="payment-icon">
                                        <i class="fa fa-university"></i>
                                    </div>
                                    <div>
                                        <strong>Net Banking</strong><br>
                                        <small class="text-muted">All major banks are supported</small>
                                    </div>
                                </div>
                            </div>

                            <div class="payment-logos">
                                <div class="payment-logo">VISA</div>
                                <div class="payment-logo">Mastercard SecureCode</div>
                                <div class="payment-logo">RuPay</div>
                                <div class="payment-logo">SafeKey</div>
                            </div>
                        </form>
                    </div>

                    <!-- QR Code Section -->
                    <div class="qr-section">
                        <h5>OR</h5>
                        <h6>Scan QR code</h6>
                        <div class="qr-code">
                            <i class="fa fa-qrcode"></i>
                        </div>
                        <a href="#" class="btn btn-success">Click to see QR code</a>

                        <div class="upi-apps">
                            <div class="upi-app">
                                <i class="fa fa-mobile"></i>
                                G Pay
                            </div>
                            <div class="upi-app">
                                <i class="fa fa-mobile"></i>
                                PhonePe
                            </div>
                            <div class="upi-app">
                                <i class="fa fa-mobile"></i>
                                BHIM
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Booking Details Column -->
                <div class="col-md-4">
                    <div class="booking-details">
                        <h5>Booking Details</h5>

                        <!-- Car Information -->
                        <div class="mb-3">
                            <strong><?php echo htmlentities($booking->VehiclesTitle); ?></strong>
                            <ul class="list-unstyled text-muted">
                                <li>• <?php echo htmlentities($booking->FuelType); ?></li>
                                <li>• <?php echo htmlentities($booking->SeatingCapacity); ?> Seats</li>
                                <li>• <?php echo htmlentities($booking->ModelYear); ?></li>
                            </ul>
                        </div>

                        <!-- Car Image -->
                        <?php if ($booking->Vimage1): ?>
                            <img src="admin/img/vehicleimages/<?php echo htmlentities($booking->Vimage1); ?>"
                                alt="Car" class="car-image">
                        <?php endif; ?>

                        <!-- Pickup Details -->
                        <div class="mb-3">
                            <div class="location-dot pickup-dot"></div>
                            <strong><?php echo date('D, d M, h:i A', strtotime($booking->FromDate)); ?></strong><br>
                            <small class="text-muted">Self Savari Car rental, Guwahati, Assam</small>
                        </div>

                        <!-- Drop-off Details -->
                        <div class="mb-3">
                            <div class="location-dot dropoff-dot"></div>
                            <strong><?php echo date('D, d M, h:i A', strtotime($booking->ToDate)); ?></strong><br>
                            <small class="text-muted">Self Savari Car rental, Guwahati, Assam</small>
                        </div>

                        <!-- Cancellation Policy -->
                        <div class="alert alert-secondary">
                            <strong>Cancellation Policy:</strong><br>
                            <small>Cancellation Fee: 50% of trip amount or INR 4000 (whichever is lower) Until <?php echo date('d M Y, h:i A', strtotime($booking->FromDate . ' -1 day')); ?></small>
                        </div>

                        <!-- Final Amount -->
                        <div class="final-amount">
                            <div>Final Amount</div>
                            <div>₹<?php echo number_format($amount, 2); ?></div>
                        </div>

                        <!-- Pay Button -->
                        <button type="submit" form="payment-form" name="process_payment" class="btn btn-success btn-lg btn-block mt-3">
                            Pay ₹<?php echo number_format($amount, 2); ?>
                        </button>
                    </div>
                </div>
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

    <script>
        // Payment method selection
        function selectPayment(method) {
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });

            // Add selected class to clicked option
            event.currentTarget.classList.add('selected');

            // Check the radio button
            document.getElementById(method).checked = true;
        }

        // Timer countdown
        function startTimer(duration, display) {
            var timer = duration,
                minutes, seconds;
            setInterval(function() {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);

                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;

                display.textContent = minutes + ":" + seconds;

                if (--timer < 0) {
                    display.textContent = "00:00";
                    alert("Price lock expired. Please refresh to get updated pricing.");
                }
            }, 1000);
        }

        window.onload = function() {
            var tenMinutes = 60 * 10,
                display = document.querySelector('#timer');
            startTimer(tenMinutes, display);
        };
    </script>
</body>

</html>