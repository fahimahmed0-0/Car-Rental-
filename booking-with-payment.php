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

$vehicle_id = isset($_GET['vhid']) ? $_GET['vhid'] : null;

if (!$vehicle_id) {
    header('location:car-listing.php');
    exit();
}

// Get vehicle details
$sql = "SELECT v.*, b.BrandName 
        FROM tblvehicles v 
        JOIN tblbrands b ON v.VehiclesBrand = b.id 
        WHERE v.id = :vehicle_id";
$query = $dbh->prepare($sql);
$query->bindParam(':vehicle_id', $vehicle_id, PDO::PARAM_STR);
$query->execute();
$vehicle = $query->fetch(PDO::FETCH_OBJ);

if (!$vehicle) {
    header('location:car-listing.php');
    exit();
}

// Initialize payment gateway
$paymentGateway = new PaymentGateway($dbh);

// Process booking form
if (isset($_POST['submit'])) {
    $fromdate = $_POST['fromdate'];
    $todate = $_POST['todate'];
    $message = $_POST['message'];
    $useremail = $_SESSION['login'];

    // Check if the car is available for the selected dates
    $ret = "SELECT * FROM tblbooking where (:fromdate BETWEEN date(FromDate) and date(ToDate) || :todate BETWEEN date(FromDate) and date(ToDate) || date(FromDate) BETWEEN :fromdate and :todate) and VehicleId=:vhid";
    $query1 = $dbh->prepare($ret);
    $query1->bindParam(':vhid', $vehicle_id, PDO::PARAM_STR);
    $query1->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
    $query1->bindParam(':todate', $todate, PDO::PARAM_STR);
    $query1->execute();
    $results1 = $query1->fetchAll(PDO::FETCH_OBJ);

    if ($query1->rowCount() > 0) {
        $error = "Vehicle is not available for the selected dates. Please choose different dates.";
    } else {
        // Calculate amount
        $base_amount = $paymentGateway->calculateBookingAmount($vehicle_id, $fromdate, $todate);

        // Handle token redemption
        $tokensToRedeem = isset($_POST['redeem_tokens']) ? intval($_POST['redeem_tokens']) : 0;
        $discount_amount = 0;

        if ($tokensToRedeem > 0) {
            // Check if user has enough tokens
            $sql = "SELECT tokenBalance FROM tblcustomertokens WHERE userEmail = :email";
            $query = $dbh->prepare($sql);
            $query->bindParam(':email', $useremail, PDO::PARAM_STR);
            $query->execute();
            $currentTokens = $query->fetchColumn();

            if ($tokensToRedeem < 15) {
                $error = "Minimum 15 tokens required for redemption";
            } elseif ($currentTokens < $tokensToRedeem) {
                $error = "Insufficient token balance";
            } else {
                // Calculate discount (5 tokens = ₹100)
                $discount_amount = ($tokensToRedeem / 5) * 100;
            }
        }

        if (!isset($error)) {
            $final_amount = $base_amount - $discount_amount;

            // Create temporary booking record
            $bookingno = mt_rand(100000000, 999999999);
            $status = 0; // Pending payment

            $sql = "INSERT INTO tblbooking(BookingNumber,userEmail,VehicleId,FromDate,ToDate,message,Status,Amount,DiscountAmount,FinalAmount) 
                    VALUES(:bookingno,:useremail,:vhid,:fromdate,:todate,:message,:status,:amount,:discount,:final_amount)";
            $query = $dbh->prepare($sql);
            $query->bindParam(':bookingno', $bookingno, PDO::PARAM_STR);
            $query->bindParam(':useremail', $useremail, PDO::PARAM_STR);
            $query->bindParam(':vhid', $vehicle_id, PDO::PARAM_STR);
            $query->bindParam(':fromdate', $fromdate, PDO::PARAM_STR);
            $query->bindParam(':todate', $todate, PDO::PARAM_STR);
            $query->bindParam(':message', $message, PDO::PARAM_STR);
            $query->bindParam(':status', $status, PDO::PARAM_STR);
            $query->bindParam(':amount', $base_amount, PDO::PARAM_STR);
            $query->bindParam(':discount', $discount_amount, PDO::PARAM_STR);
            $query->bindParam(':final_amount', $final_amount, PDO::PARAM_STR);

            if ($query->execute()) {
                // Store token redemption info if applicable
                if ($tokensToRedeem > 0) {
                    $sql = "INSERT INTO tbltokenredemption (userEmail, tokensRedeemed, discountAmount, bookingId) 
                            VALUES (:email, :tokens, :discount, :booking)";
                    $query = $dbh->prepare($sql);
                    $query->bindParam(':email', $useremail, PDO::PARAM_STR);
                    $query->bindParam(':tokens', $tokensToRedeem, PDO::PARAM_INT);
                    $query->bindParam(':discount', $discount_amount, PDO::PARAM_STR);
                    $query->bindParam(':booking', $bookingno, PDO::PARAM_STR);
                    $query->execute();
                }

                // Redirect to payment page
                header('location:payment.php?booking_id=' . $bookingno . '&amount=' . $final_amount);
                exit();
            } else {
                $error = "Something went wrong. Please try again.";
            }
        }
    }
}

// Get token balance for user
$tokenInfo = ['tokenBalance' => 0];
$sql = "SELECT tokenBalance FROM tblcustomertokens WHERE userEmail = :email";
$query = $dbh->prepare($sql);
$query->bindParam(':email', $_SESSION['login'], PDO::PARAM_STR);
$query->execute();
$result = $query->fetch(PDO::FETCH_OBJ);
if ($result) {
    $tokenInfo['tokenBalance'] = $result->tokenBalance;
}
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <title>Car Rental Portal | Book Vehicle</title>
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
        .booking-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 40px 0;
        }

        .booking-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
        }

        .vehicle-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .vehicle-image {
            width: 100%;
            max-width: 300px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .price-estimate {
            background: #e3f2fd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }

        .token-section {
            background: #fff3cd;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <!--Header-->
    <?php include('includes/header.php'); ?>
    <!-- /Header -->

    <div class="booking-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="booking-card">
                        <h2 class="text-center mb-4">Book Your Vehicle</h2>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>



                        <!-- Vehicle Information -->
                        <div class="vehicle-info">
                            <h4><?php echo htmlentities($vehicle->BrandName . ' ' . $vehicle->VehiclesTitle); ?></h4>

                            <div class="row">
                                <div class="col-md-6">
                                    <?php if ($vehicle->Vimage1): ?>
                                        <img src="admin/img/vehicleimages/<?php echo htmlentities($vehicle->Vimage1); ?>"
                                            alt="Vehicle" class="vehicle-image">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li><strong>Fuel Type:</strong> <?php echo htmlentities($vehicle->FuelType); ?></li>
                                        <li><strong>Seating Capacity:</strong> <?php echo htmlentities($vehicle->SeatingCapacity); ?> Seats</li>
                                        <li><strong>Model Year:</strong> <?php echo htmlentities($vehicle->ModelYear); ?></li>
                                        <li><strong>Daily Rate:</strong> ₹<?php echo number_format($vehicle->PricePerDay, 2); ?></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- Booking Form -->
                        <form method="post" id="booking-form">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>From Date</label>
                                        <input type="date" name="fromdate" class="form-control" required
                                            min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>To Date</label>
                                        <input type="date" name="todate" class="form-control" required
                                            min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Message (Optional)</label>
                                <textarea name="message" class="form-control" rows="3"
                                    placeholder="Any special requirements or notes..."></textarea>
                            </div>

                            <!-- Token Redemption Section -->
                            <?php if ($tokenInfo['tokenBalance'] >= 15): ?>
                                <div class="token-section">
                                    <h5><i class="fa fa-gift"></i> Token Redemption</h5>
                                    <p>Your current token balance: <strong><?php echo $tokenInfo['tokenBalance']; ?> tokens</strong></p>
                                    <p><small>• 5 tokens = ₹100 discount</small></p>
                                    <p><small>• Minimum redemption: 15 tokens</small></p>

                                    <select name="redeem_tokens" class="form-control" onchange="calculateTotal()">
                                        <option value="0">Don't use tokens</option>
                                        <?php
                                        $maxRedeemable = floor($tokenInfo['tokenBalance'] / 5) * 5;
                                        for ($i = 15; $i <= $maxRedeemable; $i += 5) {
                                            echo "<option value=\"$i\">Redeem $i tokens for ₹" . ($i * 20) . " discount</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <small>You need minimum 15 tokens to get a discount. Current balance: <?php echo $tokenInfo['tokenBalance']; ?> tokens</small>
                                </div>
                            <?php endif; ?>

                            <!-- Price Estimate -->
                            <div class="price-estimate">
                                <h5><i class="fa fa-calculator"></i> Price Estimate</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Base Amount:</strong> <span id="base-amount">₹0.00</span></p>
                                        <p><strong>Discount:</strong> <span id="discount-amount">₹0.00</span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <h4><strong>Final Amount:</strong> <span id="final-amount" class="text-primary">₹0.00</span></h4>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" name="submit" class="btn btn-primary btn-lg">
                                    <i class="fa fa-credit-card"></i> Proceed to Payment
                                </button>
                            </div>
                        </form>
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
        // Store daily rate in a variable
        const dailyRate = <?php echo intval($vehicle->PricePerDay); ?>;

        // Calculate total amount based on selected dates and tokens
        function calculateTotal() {
            const fromDateInput = document.querySelector('input[name="fromdate"]');
            const toDateInput = document.querySelector('input[name="todate"]');
            const tokensSelect = document.querySelector('select[name="redeem_tokens"]');

            const fromDate = fromDateInput ? fromDateInput.value : '';
            const toDate = toDateInput ? toDateInput.value : '';
            const tokensToRedeem = tokensSelect ? (parseInt(tokensSelect.value) || 0) : 0;

            if (fromDate && toDate && dailyRate > 0) {
                const from = new Date(fromDate);
                const to = new Date(toDate);

                // Calculate days difference
                const timeDiff = to.getTime() - from.getTime();
                const days = Math.ceil(timeDiff / (1000 * 3600 * 24));

                if (days > 0) {
                    const baseAmount = dailyRate * days;
                    const discountAmount = (tokensToRedeem / 5) * 100;
                    const finalAmount = Math.max(0, baseAmount - discountAmount);

                    // Update the display
                    const baseAmountElement = document.getElementById('base-amount');
                    const discountAmountElement = document.getElementById('discount-amount');
                    const finalAmountElement = document.getElementById('final-amount');

                    if (baseAmountElement) baseAmountElement.textContent = '₹' + baseAmount.toFixed(2);
                    if (discountAmountElement) discountAmountElement.textContent = '₹' + discountAmount.toFixed(2);
                    if (finalAmountElement) finalAmountElement.textContent = '₹' + finalAmount.toFixed(2);
                } else {
                    resetAmounts();
                }
            } else {
                resetAmounts();
            }
        }

        function resetAmounts() {
            const baseAmountElement = document.getElementById('base-amount');
            const discountAmountElement = document.getElementById('discount-amount');
            const finalAmountElement = document.getElementById('final-amount');

            if (baseAmountElement) baseAmountElement.textContent = '₹0.00';
            if (discountAmountElement) discountAmountElement.textContent = '₹0.00';
            if (finalAmountElement) finalAmountElement.textContent = '₹0.00';
        }

        // Add event listeners when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            const fromDateInput = document.querySelector('input[name="fromdate"]');
            const toDateInput = document.querySelector('input[name="todate"]');
            const tokensSelect = document.querySelector('select[name="redeem_tokens"]');

            if (fromDateInput) {
                fromDateInput.addEventListener('change', calculateTotal);
            }

            if (toDateInput) {
                toDateInput.addEventListener('change', calculateTotal);
            }

            if (tokensSelect) {
                tokensSelect.addEventListener('change', calculateTotal);
            }

            // Initial calculation if dates are already filled
            if (fromDateInput && toDateInput && fromDateInput.value && toDateInput.value) {
                calculateTotal();
            }
        });
    </script>
</body>

</html>