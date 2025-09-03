<?php
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
	header('location:index.php');
} else {
	if (isset($_REQUEST['eid'])) {
		$eid = intval($_GET['eid']);
		$status = "2";
		$sql = "UPDATE tblbooking SET Status=:status WHERE  id=:eid";
		$query = $dbh->prepare($sql);
		$query->bindParam(':status', $status, PDO::PARAM_STR);
		$query->bindParam(':eid', $eid, PDO::PARAM_STR);
		$query->execute();

		$msg = "Booking Successfully Cancelled";
	}


	if (isset($_REQUEST['aeid'])) {
		$aeid = intval($_GET['aeid']);
		$status = 1;

		$sql = "UPDATE tblbooking SET Status=:status WHERE  id=:aeid";
		$query = $dbh->prepare($sql);
		$query->bindParam(':status', $status, PDO::PARAM_STR);
		$query->bindParam(':aeid', $aeid, PDO::PARAM_STR);
		$query->execute();

		// Get booking details to award tokens
		$sql = "SELECT userEmail FROM tblbooking WHERE id=:aeid";
		$query = $dbh->prepare($sql);
		$query->bindParam(':aeid', $aeid, PDO::PARAM_STR);
		$query->execute();
		$booking = $query->fetch(PDO::FETCH_OBJ);

		if ($booking) {
			// Award 5 tokens
			$tokens = 5;

			// Update user's token balance
			$sql = "INSERT INTO tblcustomertokens (userEmail, tokenBalance, totalEarnedTokens) 
            VALUES (:email, :tokens, :tokens)
            ON DUPLICATE KEY UPDATE 
            tokenBalance = tokenBalance + :tokens,
            totalEarnedTokens = totalEarnedTokens + :tokens";
			$query = $dbh->prepare($sql);
			$query->bindParam(':email', $booking->userEmail, PDO::PARAM_STR);
			$query->bindParam(':tokens', $tokens, PDO::PARAM_INT);
			$query->execute();

			// Record token earning
			$sql = "INSERT INTO tbltokenearnings (userEmail, bookingId, tokensEarned) 
            VALUES (:email, :bookingId, :tokens)";
			$query = $dbh->prepare($sql);
			$query->bindParam(':email', $booking->userEmail, PDO::PARAM_STR);
			$query->bindParam(':bookingId', $aeid, PDO::PARAM_INT);
			$query->bindParam(':tokens', $tokens, PDO::PARAM_INT);
			$query->execute();
		}

		$msg = "Booking Successfully Confirmed";
		echo "<script type='text/javascript'> window.location.href = 'confirmed-bookings.php'; </script>";
	}

	if (isset($_REQUEST['cid'])) {
		$cid = intval($_GET['cid']);
		$status = 3; // New status for completed bookings

		try {
			$dbh->beginTransaction();

			// First get the booking details to get user email
			$sql = "SELECT userEmail FROM tblbooking WHERE id=:cid";
			$query = $dbh->prepare($sql);
			$query->bindParam(':cid', $cid, PDO::PARAM_STR);
			$query->execute();
			$booking = $query->fetch(PDO::FETCH_OBJ);

			if ($booking) {
				// Update booking status to completed
				$sql = "UPDATE tblbooking SET Status=:status WHERE id=:cid";
				$query = $dbh->prepare($sql);
				$query->bindParam(':status', $status, PDO::PARAM_STR);
				$query->bindParam(':cid', $cid, PDO::PARAM_STR);
				$query->execute();

				// Award 5 tokens
				$tokens = 5;

				// Update user's token balance
				$sql = "INSERT INTO tblcustomertokens (userEmail, tokenBalance, totalEarnedTokens) 
                        VALUES (:email, :tokens, :tokens)
                        ON DUPLICATE KEY UPDATE 
                        tokenBalance = tokenBalance + :tokens,
                        totalEarnedTokens = totalEarnedTokens + :tokens";
				$query = $dbh->prepare($sql);
				$query->bindParam(':email', $booking->userEmail, PDO::PARAM_STR);
				$query->bindParam(':tokens', $tokens, PDO::PARAM_INT);
				$query->execute();

				// Record token earning
				$sql = "INSERT INTO tbltokenearnings (userEmail, bookingId, tokensEarned) 
                        VALUES (:email, :bookingId, :tokens)";
				$query = $dbh->prepare($sql);
				$query->bindParam(':email', $booking->userEmail, PDO::PARAM_STR);
				$query->bindParam(':bookingId', $cid, PDO::PARAM_INT);
				$query->bindParam(':tokens', $tokens, PDO::PARAM_INT);
				$query->execute();

				$dbh->commit();
				$msg = "Booking marked as completed and tokens awarded successfully";
			}
		} catch (Exception $e) {
			$dbh->rollBack();
			$error = "Something went wrong. Please try again";
			error_log($e->getMessage());
		}
	}


?>

	<!doctype html>
	<html lang="en" class="no-js">

	<head>
		<meta charset="UTF-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
		<meta name="description" content="">
		<meta name="author" content="">
		<meta name="theme-color" content="#3e454c">

		<title>Car Rental Portal | Manage Pending Bookings</title>

		<!-- Font awesome -->
		<link rel="stylesheet" href="css/font-awesome.min.css">
		<!-- Sandstone Bootstrap CSS -->
		<link rel="stylesheet" href="css/bootstrap.min.css">
		<!-- Bootstrap Datatables -->
		<link rel="stylesheet" href="css/dataTables.bootstrap.min.css">
		<!-- Bootstrap social button library -->
		<link rel="stylesheet" href="css/bootstrap-social.css">
		<!-- Bootstrap select -->
		<link rel="stylesheet" href="css/bootstrap-select.css">
		<!-- Bootstrap file input -->
		<link rel="stylesheet" href="css/fileinput.min.css">
		<!-- Awesome Bootstrap checkbox -->
		<link rel="stylesheet" href="css/awesome-bootstrap-checkbox.css">
		<!-- Admin Stye -->
		<link rel="stylesheet" href="css/style.css">
		<style>
			.errorWrap {
				padding: 10px;
				margin: 0 0 20px 0;
				background: #fff;
				border-left: 4px solid #dd3d36;
				-webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
				box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
			}

			.succWrap {
				padding: 10px;
				margin: 0 0 20px 0;
				background: #fff;
				border-left: 4px solid #5cb85c;
				-webkit-box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
				box-shadow: 0 1px 1px 0 rgba(0, 0, 0, .1);
			}
		</style>

	</head>

	<body>
		<?php include('includes/header.php'); ?>

		<div class="ts-main-content">
			<?php include('includes/leftbar.php'); ?>
			<div class="content-wrapper">
				<div class="container-fluid">

					<div class="row">
						<div class="col-md-12">

							<h2 class="page-title">Manage Pending Bookings</h2>

							<!-- Zero Configuration Table -->
							<div class="panel panel-default">
								<div class="panel-heading">Pending Bookings Info</div>
								<div class="panel-body">
									<?php if ($error) { ?><div class="errorWrap"><strong>ERROR</strong>:<?php echo htmlentities($error); ?> </div><?php } else if ($msg) { ?><div class="succWrap"><strong>SUCCESS</strong>:<?php echo htmlentities($msg); ?> </div><?php } ?>
									<table id="zctb" class="display table table-striped table-bordered table-hover" cellspacing="0" width="100%">
										<thead>
											<tr>
												<th>#</th>
												<th>Name</th>
												<th>Booking No.</th>
												<th>Vehicle</th>
												<th>From Date</th>
												<th>To Date</th>
												<th>Status</th>
												<th>Posting date</th>
												<th>Action</th>
											</tr>
										</thead>
										<tfoot>
											<tr>
												<th>#</th>
												<th>Name</th>
												<th>Booking No.</th>
												<th>Vehicle</th>
												<th>From Date</th>
												<th>To Date</th>
												<th>Status</th>
												<th>Posting date</th>
												<th>Action</th>
											</tr>
										</tfoot>
										<tbody>

											<?php
											// Only show bookings with Status = 0 (not confirmed/cancelled)
											$sql = "SELECT tblusers.FullName,tblbrands.BrandName,tblvehicles.VehiclesTitle,tblbooking.FromDate,tblbooking.ToDate,tblbooking.message,tblbooking.VehicleId as vid,tblbooking.Status,tblbooking.PostingDate,tblbooking.id,tblbooking.BookingNumber  
											from tblbooking 
											join tblvehicles on tblvehicles.id=tblbooking.VehicleId 
											join tblusers on tblusers.EmailId=tblbooking.userEmail 
											join tblbrands on tblvehicles.VehiclesBrand=tblbrands.id 
											where tblbooking.Status=0 
											order by tblbooking.id desc";
											$query = $dbh->prepare($sql);
											$query->execute();
											$results = $query->fetchAll(PDO::FETCH_OBJ);
											$cnt = 1;
											if ($query->rowCount() > 0) {
												foreach ($results as $result) {				?>
													<tr>
														<td><?php echo htmlentities($cnt); ?></td>
														<td><?php echo htmlentities($result->FullName); ?></td>
														<td><?php echo htmlentities($result->BookingNumber); ?></td>
														<td><a href="edit-vehicle.php?id=<?php echo htmlentities($result->vid); ?>"><?php echo htmlentities($result->BrandName); ?> , <?php echo htmlentities($result->VehiclesTitle); ?></td>
														<td><?php echo htmlentities($result->FromDate); ?></td>
														<td><?php echo htmlentities($result->ToDate); ?></td>
														<td><?php echo htmlentities('Not Confirmed yet'); ?></td>
														<td><?php echo htmlentities($result->PostingDate); ?></td>
														<td>
															<a href="bookig-details.php?bid=<?php echo htmlentities($result->id); ?>">View</a> /
															<a href="manage-bookings.php?aeid=<?php echo htmlentities($result->id); ?>" onclick="return confirm('Do you really want to Confirm this booking')">Confirm</a> /
															<a href="manage-bookings.php?eid=<?php echo htmlentities($result->id); ?>" onclick="return confirm('Do you really want to Cancel this Booking')">Cancel</a>
														</td>
													</tr>
											<?php $cnt = $cnt + 1;
												}
											} ?>

										</tbody>
									</table>



								</div>
							</div>



						</div>
					</div>

				</div>
			</div>
		</div>

		<!-- Loading Scripts -->
		<script src="js/jquery.min.js"></script>
		<script src="js/bootstrap-select.min.js"></script>
		<script src="js/bootstrap.min.js"></script>
		<script src="js/jquery.dataTables.min.js"></script>
		<script src="js/dataTables.bootstrap.min.js"></script>
		<script src="js/Chart.min.js"></script>
		<script src="js/fileinput.js"></script>
		<script src="js/chartData.js"></script>
		<script src="js/main.js"></script>
	</body>

	</html>
<?php } ?>