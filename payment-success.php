<?php
session_start();
error_reporting(0);
include('includes/config.php');

// Check if user is logged in
if (strlen($_SESSION['login']) == 0) {
    header('location:index.php');
    exit();
}

$booking_id = isset($_GET['booking_id']) ? $_GET['booking_id'] : null;

if (!$booking_id) {
    header('location:my-booking.php');
    exit();
}

// Get booking details
$sql = "SELECT b.*, v.VehiclesTitle, v.VehiclesBrand, v.FuelType, v.ModelYear, v.SeatingCapacity, v.VehicleImage 
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
?>

<!DOCTYPE HTML>
<html lang="en">

<head>
    <title>Car Rental Portal | Payment Success</title>
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
        .success-container {
            background: #f8f9fa;
            min-height: 100vh;
            padding: 40px 0;
        }

        .success-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }

        .booking-details-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .car-image {
            width: 100%;
            max-width: 200px;
            border-radius: 8px;
            margin: 15px 0;
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

        .action-buttons {
            margin-top: 30px;
        }

        .action-buttons .btn {
            margin: 10px;
            padding: 12px 30px;
        }
    </style>
</head>

<body>
    <!--Header-->
    <?php include('includes/header.php'); ?>
    <!-- /Header -->

    <div class="success-container">
        <div class="container">
            <div class="success-card">
                <div class="success-icon">
                    <i class="fa fa-check"></i>
                </div>

                <h2 class="text-success">Payment Successful!</h2>
                <p class="text-muted">Your booking has been confirmed and payment has been processed successfully.</p>

                <div class="alert alert-success">
                    <strong>Booking ID:</strong> <?php echo $booking_id; ?><br>
                    <strong>Payment Status:</strong> Completed<br>
                    <strong>Payment Date:</strong> <?php echo date('d M Y, h:i A'); ?>
                </div>

                <div class="booking-details-card">
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
                    <?php if ($booking->VehicleImage): ?>
                        <img src="admin/img/vehicleimages/<?php echo htmlentities($booking->VehicleImage); ?>"
                            alt="Car" class="car-image">
                    <?php endif; ?>

                    <!-- Pickup Details -->
                    <div class="mb-3">
                        <div class="location-dot pickup-dot"></div>
                        <strong>Pickup:</strong> <?php echo date('D, d M, h:i A', strtotime($booking->FromDate)); ?><br>
                        <small class="text-muted">Self Savari Car rental, Guwahati, Assam</small>
                    </div>

                    <!-- Drop-off Details -->
                    <div class="mb-3">
                        <div class="location-dot dropoff-dot"></div>
                        <strong>Drop-off:</strong> <?php echo date('D, d M, h:i A', strtotime($booking->ToDate)); ?><br>
                        <small class="text-muted">Self Savari Car rental, Guwahati, Assam</small>
                    </div>
                </div>

                <div class="action-buttons">
                    <a href="my-booking.php" class="btn btn-primary">
                        <i class="fa fa-list"></i> View My Bookings
                    </a>
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fa fa-home"></i> Back to Home
                    </a>
                </div>

                <div class="mt-4">
                    <small class="text-muted">
                        A confirmation email has been sent to your registered email address.<br>
                        Please keep this booking ID for future reference.
                    </small>
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
</body>

</html>
