
<?php

require_once('session_auth.php'); 
error_reporting(E_ALL);
ini_set('display_errors', 1);
// CORRECT: This file now provides $con_patcom and $con_payroll
// include('include/config.php');

// 1. ========= SECURITY & SETUP =========
if (strlen($_SESSION['id']) == 0 || !isset($_SESSION['dlogin'])) {
    header('location:../../logout.php');
    exit();
}

$staff_id = $_SESSION['id']; // This is the UsrId from the umanusers table

// 2. ========= DATA FOR DASHBOARD CARDS (Uses Payroll DB) =========

// --- Card 1: Approved Leave Days (This Year) ---
$current_year = date('Y');
// CORRECT: No 'payroll.' prefix needed
$sql_leave_taken = "SELECT SUM(days_requested) as total_days FROM leave_requests WHERE empid=? AND status='approved' AND YEAR(start_date) = ?";
// CORRECT: Use the $con_payroll connection
$stmt_leave_taken = mysqli_prepare($con_payroll, $sql_leave_taken);
if ($stmt_leave_taken === false) { die('SQL Error (leave_taken): ' . mysqli_error($con_payroll)); }
mysqli_stmt_bind_param($stmt_leave_taken, "is", $staff_id, $current_year);
mysqli_stmt_execute($stmt_leave_taken);
$approved_leave_this_year = mysqli_stmt_get_result($stmt_leave_taken)->fetch_assoc()['total_days'] ?? 0;

// --- Card 2: Remaining Annual Leave Balance ---
$sql_balance = "SELECT elb.current_balance FROM employee_leave_balances elb JOIN leave_types lt ON elb.leave_type_id = lt.leave_type_id WHERE elb.empid = ? AND elb.year = ? AND lt.type_name LIKE 'Annual Leave%'";
$stmt_balance = mysqli_prepare($con_payroll, $sql_balance);
if ($stmt_balance === false) { die('SQL Error (balance): ' . mysqli_error($con_payroll)); }
mysqli_stmt_bind_param($stmt_balance, "is", $staff_id, $current_year);
mysqli_stmt_execute($stmt_balance);
$result_balance = mysqli_stmt_get_result($stmt_balance);
$remaining_leave_days = $result_balance ? ($result_balance->fetch_assoc()['current_balance'] ?? 'N/A') : 'N/A';

// --- Card 3: Pending Leave Requests ---
$sql_pending = "SELECT COUNT(request_id) as pending_count FROM leave_requests WHERE empid=? AND status='pending'";
$stmt_pending = mysqli_prepare($con_payroll, $sql_pending);
if ($stmt_pending === false) { die('SQL Error (pending): ' . mysqli_error($con_payroll)); }
mysqli_stmt_bind_param($stmt_pending, "i", $staff_id);
mysqli_stmt_execute($stmt_pending);
$pending_requests_count = mysqli_stmt_get_result($stmt_pending)->fetch_assoc()['pending_count'] ?? 0;

// --- Card 4: Days Present This Month ---
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$sql_attendance = "SELECT COUNT(id) as present_days FROM attendance WHERE empid=? AND status='Present' AND attendance_date BETWEEN ? AND ?";
$stmt_attendance = mysqli_prepare($con_payroll, $sql_attendance);
if ($stmt_attendance) {
    mysqli_stmt_bind_param($stmt_attendance, "iss", $staff_id, $month_start, $month_end);
    mysqli_stmt_execute($stmt_attendance);
    $days_present_this_month = mysqli_stmt_get_result($stmt_attendance)->fetch_assoc()['present_days'] ?? 0;
} else {
    $days_present_this_month = "N/A";
}

// 3. ========= DATA FOR TABLES (Uses Payroll DB) =========
$sql_leave_history = "SELECT lt.type_name, lr.start_date, lr.end_date, lr.status FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.empid=? ORDER BY lr.created_at DESC LIMIT 5";
$stmt_history = mysqli_prepare($con_payroll, $sql_leave_history);
if ($stmt_history === false) { die('SQL Error (history): ' . mysqli_error($con_payroll)); }
mysqli_stmt_bind_param($stmt_history, "i", $staff_id);
mysqli_stmt_execute($stmt_history);
$leave_history = mysqli_fetch_all(mysqli_stmt_get_result($stmt_history), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<!-- Your HTML, CSS, and JS for the dashboard are correct and do not need to change. -->
<!-- Paste the full HTML/CSS/JS code from the previous working version here. -->
<html lang="en">
<head>
    <title>Staff Dashboard | Zion HMS</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css">
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
    <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
    <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
    <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
    <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
    <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color">
    <style>
        .dashboard-stat { background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); display: flex; align-items: center; transition: transform 0.2s; }
        .dashboard-stat:hover { transform: translateY(-5px); }
        .dashboard-stat-icon { font-size: 2.8em; margin-right: 20px; width: 60px; text-align: center; color: #fff; background-color: #5cb85c; border-radius: 50%; height: 60px; line-height: 60px; }
        .dashboard-stat .text-primary .dashboard-stat-icon { background-color: #337ab7; }
        .dashboard-stat .text-warning .dashboard-stat-icon { background-color: #f0ad4e; }
        .dashboard-stat .text-danger .dashboard-stat-icon { background-color: #d9534f; }
        .dashboard-stat-content h4 { margin: 0 0 5px 0; font-size: 1.6em; font-weight: 700; color: #333; }
        .dashboard-stat-content span { font-size: 0.9em; color: #888; }
        .panel-white .panel-heading { border-bottom: 2px solid #f4f4f4; }
        .table > thead > tr > th { font-weight: 600; color: #555; }
    </style>
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php');?>
        <div class="app-content">
            <?php include('include/header.php');?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8"><h1 class="mainTitle">Welcome, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?></h1></div>
                            <ol class="breadcrumb"><li><span>Dashboard</span></li><li class="active"><span>HR Overview</span></li></ol>
                        </div>
                    </section>
                    <div class="row">
                        <div class="col-sm-6 col-md-3">
                            <div class="dashboard-stat text-success">
                                <div class="dashboard-stat-icon"><i class="fa fa-calendar-check-o"></i></div>
                                <div class="dashboard-stat-content">
                                    <h4><?php echo (int)$approved_leave_this_year; ?> Day(s)</h4>
                                    <span>Leave Taken This Year</span>
                                </div>
                            </div>
                        </div>
                         <div class="col-sm-6 col-md-3">
                            <div class="dashboard-stat text-danger">
                                <div class="dashboard-stat-icon"><i class="fa fa-plane"></i></div>
                                <div class="dashboard-stat-content">
                                    <h4><?php echo is_numeric($remaining_leave_days) ? number_format($remaining_leave_days, 1) : $remaining_leave_days; ?> Day(s)</h4>
                                    <span>Remaining Annual Leave</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="dashboard-stat text-warning">
                                <div class="dashboard-stat-icon"><i class="fa fa-hourglass-half"></i></div>
                                <div class="dashboard-stat-content">
                                    <h4><?php echo (int)$pending_requests_count; ?></h4>
                                    <span>Pending Leave Requests</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <div class="dashboard-stat text-primary">
                                <div class="dashboard-stat-icon"><i class="fa fa-user-md"></i></div>
                                <div class="dashboard-stat-content">
                                    <h4><?php echo $days_present_this_month; ?> Day(s)</h4>
                                    <span>Present This Month</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="panel panel-white">
                                <div class="panel-heading"><h5 class="panel-title"><i class="fa fa-list-alt"></i> Recent Leave History</h5></div>
                                <div class="panel-body">
                                    <div class="table-responsive">
                                        <table class="table table-striped table-hover">
                                            <thead><tr><th>Leave Type</th><th>Start Date</th><th>End Date</th><th>Status</th></tr></thead>
                                            <tbody>
                                                <?php if(empty($leave_history)): ?>
                                                    <tr><td colspan="4" class="text-center">You have not requested any leave yet.</td></tr>
                                                <?php else: foreach($leave_history as $leave): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($leave['type_name']); ?></td>
                                                        <td><?php echo date('d M, Y', strtotime($leave['start_date'])); ?></td>
                                                        <td><?php echo date('d M, Y', strtotime($leave['end_date'])); ?></td>
                                                        <td>
                                                            <?php 
                                                                $status = htmlspecialchars($leave['status']);
                                                                $label_class = 'label-default';
                                                                if($status == 'approved') $label_class = 'label-success';
                                                                if($status == 'rejected') $label_class = 'label-danger';
                                                                if($status == 'pending') $label_class = 'label-warning';
                                                            ?>
                                                            <span class="label <?php echo $label_class; ?>"><?php echo ucfirst($status); ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('include/footer.php');?>
        <?php include('include/setting.php');?>
    </div>
    <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="assets/js/main.js"></script>
        
    <script>
        jQuery(document).ready(function() {
            Main.init();
        });
    </script>
</body>
</html>