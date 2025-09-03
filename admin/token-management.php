<?php
session_start();
error_reporting(0);
include('includes/config.php');
if (strlen($_SESSION['alogin']) == 0) {
    header('location:index.php');
} else {
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

        <title>Car Rental Portal | Token Management</title>

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

                            <h2 class="page-title">Token Management</h2>

                            <!-- Zero Configuration Table -->
                            <div class="panel panel-default">
                                <div class="panel-heading">Token Statistics</div>
                                <div class="panel-body">
                                    <?php
                                    // Get total tokens issued
                                    $sql = "SELECT SUM(totalEarnedTokens) as total_tokens FROM tblcustomertokens";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $total_tokens = $query->fetch(PDO::FETCH_ASSOC)['total_tokens'];

                                    // Get total tokens redeemed
                                    $sql = "SELECT SUM(tokensRedeemed) as redeemed_tokens FROM tbltokenredemption";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $redeemed_tokens = $query->fetch(PDO::FETCH_ASSOC)['redeemed_tokens'];

                                    // Get total discount given
                                    $sql = "SELECT SUM(discountAmount) as total_discount FROM tbltokenredemption";
                                    $query = $dbh->prepare($sql);
                                    $query->execute();
                                    $total_discount = $query->fetch(PDO::FETCH_ASSOC)['total_discount'];
                                    ?>

                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="well">
                                                <h4>Total Tokens Issued</h4>
                                                <h3><?php echo $total_tokens ? $total_tokens : '0'; ?></h3>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="well">
                                                <h4>Total Tokens Redeemed</h4>
                                                <h3><?php echo $redeemed_tokens ? $redeemed_tokens : '0'; ?></h3>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="well">
                                                <h4>Total Discount Given</h4>
                                                <h3>₹<?php echo $total_discount ? $total_discount : '0'; ?></h3>
                                            </div>
                                        </div>
                                    </div>

                                    <table id="zctb" class="display table table-striped table-bordered table-hover" cellspacing="0" width="100%">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>User Email</th>
                                                <th>Current Balance</th>
                                                <th>Total Earned</th>
                                                <th>Last Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $sql = "SELECT * from tblcustomertokens ORDER BY lastUpdated DESC";
                                            $query = $dbh->prepare($sql);
                                            $query->execute();
                                            $results = $query->fetchAll(PDO::FETCH_OBJ);
                                            $cnt = 1;
                                            if ($query->rowCount() > 0) {
                                                foreach ($results as $result) {
                                            ?>
                                                    <tr>
                                                        <td><?php echo htmlentities($cnt); ?></td>
                                                        <td><?php echo htmlentities($result->userEmail); ?></td>
                                                        <td><?php echo htmlentities($result->tokenBalance); ?></td>
                                                        <td><?php echo htmlentities($result->totalEarnedTokens); ?></td>
                                                        <td><?php echo htmlentities($result->lastUpdated); ?></td>
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