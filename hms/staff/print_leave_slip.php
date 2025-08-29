<?php
session_start();
include('include/config.php');

// Security Check: Ensure user is logged in and a leave ID is provided
if (!isset($_SESSION['id']) || !isset($_GET['id'])) {
    die("Access Denied. Missing required information.");
}

$leave_request_id = intval($_GET['id']);
$session_user_id = $_SESSION['id'];

// Helper function to get the current user's employee ID for security
function getEmpIdFromUserId($db_conn_patcom, $db_conn_payroll, $user_id) {
    // Step 1: Get MemberNo from PatCom
    $sql_get_memberno = "SELECT MemberNo FROM umanusers WHERE UsrId = ? LIMIT 1";
    $stmt_get_memberno = mysqli_prepare($db_conn_patcom, $sql_get_memberno);
    if (!$stmt_get_memberno) { return null; }
    mysqli_stmt_bind_param($stmt_get_memberno, "i", $user_id);
    mysqli_stmt_execute($stmt_get_memberno);
    $user = mysqli_stmt_get_result($stmt_get_memberno)->fetch_assoc();
    mysqli_stmt_close($stmt_get_memberno);
    if (!$user || empty($user['MemberNo'])) { return null; }
    
    // Step 2: Use MemberNo to get empid from Payroll
    $memberNo = $user['MemberNo'];
    $sql_get_empid = "SELECT empid FROM employees WHERE PayrollNo = ? LIMIT 1";
    $stmt_get_empid = mysqli_prepare($db_conn_payroll, $sql_get_empid);
    if (!$stmt_get_empid) { return null; }
    mysqli_stmt_bind_param($stmt_get_empid, "s", $memberNo);
    mysqli_stmt_execute($stmt_get_empid);
    $employee = mysqli_stmt_get_result($stmt_get_empid)->fetch_assoc();
    mysqli_stmt_close($stmt_get_empid);
    
    return ($employee && !empty($employee['empid'])) ? $employee['empid'] : null;
}

$current_user_empid = getEmpIdFromUserId($con_patcom, $con_payroll, $session_user_id);
if (!$current_user_empid) {
    die("Could not verify your employee record. Please contact HR.");
}

// Corrected database query
$sql = "SELECT 
            lr.*, 
            lt.type_name,
            CONCAT(e.Surname, ' ', e.OtherNames) AS employee_name,
            e.PayrollNo,
            d.DepartmentName
        FROM leave_requests lr
        JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id
        JOIN employees e ON lr.empid = e.empid
        JOIN departments d ON e.DepartmentCode = d.DepartmentCode
        WHERE 
            lr.request_id = ? AND lr.empid = ?
        LIMIT 1";

$stmt = mysqli_prepare($con_payroll, $sql);
if ($stmt === false) {
    die("SQL Prepare Failed: " . mysqli_error($con_payroll));
}
mysqli_stmt_bind_param($stmt, "ii", $leave_request_id, $current_user_empid);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$leave = mysqli_fetch_assoc($result);

if (!$leave) {
    die("Leave request not found or you do not have permission to view it.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Print Leave Slip - <?php echo htmlspecialchars($leave['employee_name']); ?></title>
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
    <style>
        body { font-family: Arial, sans-serif; background-color: #fff; }
        .container { border: 2px solid #000; padding: 30px; margin-top: 30px; max-width: 800px; }
        .header { text-align: center; margin-bottom: 25px; }
        .header h3 { margin: 0; font-weight: bold; }
        .header p { margin: 2px 0; font-size: 12px; }
        .slip-title { margin-top: 20px; margin-bottom: 30px; font-weight: bold; font-size: 20px; text-decoration: underline; }
        .content-table { width: 100%; margin-bottom: 30px; }
        .content-table td { padding: 10px; border: 1px solid #ccc; }
        .content-table td:first-child { font-weight: bold; width: 30%; }
        .footer-section { margin-top: 60px; }
        .signature-line { border-top: 1px solid #000; margin-top: 40px; width: 250px; }
        @media print {
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- ====================================================== -->
        <!-- **NEW** Professional Letterhead Header                 -->
        <!-- ====================================================== -->
        <div class="header">
            <!-- You can place a logo here if you have one -->
            <!-- <img src="path/to/your/logo.png" alt="Company Logo" style="width: 100px; margin-bottom: 10px;"> -->
            
            <h3>ZION MEDICAL CENTRE BUNGOMA</h3>
            <p>
                Makos Place Building, Next to Marel Academy<br>
                P.O. BOX 50200, BUNGOMA
            </p>
            <p>
                <strong>Mobile:</strong> 0799676969 | <strong>Email:</strong> infozionmedical@gmail.com
            </p>

            <h2 class="slip-title">OFFICIAL LEAVE SLIP</h2>
        </div>
        <!-- ====================================================== -->

        <table class="content-table">
            <tr><td>Employee Name:</td><td><?php echo htmlspecialchars($leave['employee_name']); ?></td></tr>
            <tr><td>Payroll No:</td><td><?php echo htmlspecialchars($leave['PayrollNo']); ?></td></tr>
            <tr><td>Department:</td><td><?php echo htmlspecialchars($leave['DepartmentName']); ?></td></tr>
            <tr><td>Leave Type:</td><td><?php echo htmlspecialchars($leave['type_name']); ?></td></tr>
            <tr><td>Start Date:</td><td><?php echo date("d-m-Y", strtotime($leave['start_date'])); ?></td></tr>
            <tr><td>End Date:</td><td><?php echo date("d-m-Y", strtotime($leave['end_date'])); ?></td></tr>
            <tr><td>Total Days Requested:</td><td><?php echo htmlspecialchars($leave['days_requested']); ?></td></tr>
            <tr><td>Reason Provided:</td><td><?php echo nl2br(htmlspecialchars($leave['reason'])); ?></td></tr>
            <tr><td>Status:</td><td><strong><?php echo strtoupper(htmlspecialchars($leave['status'])); ?></strong></td></tr>
            <tr><td>Date Submitted:</td><td><?php echo date("d-m-Y H:i", strtotime($leave['created_at'])); ?></td></tr>
        </table>
        
        <div class="row footer-section">
            <div class="col-xs-6">
                <div class="signature-line"></div>
                <p>Employee Signature</p>
            </div>
            <div class="col-xs-6 text-right">
                <div class="signature-line pull-right"></div>
                <p class="pull-right">Authorized Signature (HR/Manager)</p>
            </div>
        </div>
    </div>

    <div class="text-center no-print" style="margin-top: 20px;">
        <button class="btn btn-primary" onclick="window.print();">Print</button>
        <a href="#" class="btn btn-default" onclick="window.close(); return false;">Close</a>
    </div>
</body>
</html>