<?php
require_once('session_auth.php'); 

error_reporting(E_ALL);


// ========================================================================
// --- SECURITY & PERMISSIONS ---
// ========================================================================
if (strlen($_SESSION['id'] ?? '') == 0) {
    header('location: ../logout.php');
    exit();
}

// ========================================================================
// --- INITIALIZE VARIABLES & HANDLE FILTERS ---
// ========================================================================
$view = $_GET['view'] ?? 'summary';
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$search_name = trim($_GET['name'] ?? '');
$search_payroll = trim($_GET['payroll'] ?? '');
$search_department = trim($_GET['department'] ?? '');

$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstDayOfMonth = "$year-$month-01";
$lastDayOfMonth = date("Y-m-t", strtotime($firstDayOfMonth));

function build_query_string($overrides = []) {
    $base_params = ['month' => $GLOBALS['month'], 'year' => $GLOBALS['year'], 'name' => $GLOBALS['search_name'], 'payroll' => $GLOBALS['search_payroll'], 'department' => $GLOBALS['search_department']];
    $params = array_merge($base_params, $overrides);
    return http_build_query(array_filter($params));
}

// ========================================================================
// --- CORE DATA FETCHING LOGIC (Unchanged) ---
// ========================================================================
$departments = [];
$dept_result = mysqli_query($con_payroll, "SELECT DepartmentCode, DepartmentName FROM departments ORDER BY DepartmentName");
while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; }

$employee_sql = "SELECT e.empid, TRIM(e.PayrollNo) AS PayrollNo, CONCAT(e.Surname, ' ', e.OtherNames) AS fullName, d.DepartmentName 
                 FROM employees e 
                 LEFT JOIN departments d ON e.DepartmentCode = d.DepartmentCode 
                 WHERE e.Status = 'ACTIVE'";
$params = []; $types = '';
if (!empty($search_name)) { $employee_sql .= " AND CONCAT(e.Surname, ' ', e.OtherNames) LIKE ?"; $types .= 's'; $params[] = "%$search_name%"; }
if (!empty($search_payroll)) { $employee_sql .= " AND TRIM(e.PayrollNo) LIKE ?"; $types .= 's'; $params[] = "%$search_payroll%"; }
if (!empty($search_department)) { $employee_sql .= " AND e.DepartmentCode = ?"; $types .= 's'; $params[] = $search_department; }
$employee_sql .= " ORDER BY d.DepartmentName, e.Surname";
$stmt_emp = mysqli_prepare($con_payroll, $employee_sql);
if (!empty($params)) { mysqli_stmt_bind_param($stmt_emp, $types, ...$params); }
mysqli_stmt_execute($stmt_emp);
$employees = mysqli_fetch_all(mysqli_stmt_get_result($stmt_emp), MYSQLI_ASSOC);
mysqli_stmt_close($stmt_emp);

$employee_ids = array_column($employees, 'empid');
$payroll_nos = array_column($employees, 'PayrollNo');
$attendance_data = [];

if (!empty($employees)) {
    // Bulk fetch rosters, leave, and punches (This entire block is the same)
    $id_placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
    $payroll_placeholders = implode(',', array_fill(0, count($payroll_nos), '?'));
    $types_emp = str_repeat('i', count($employee_ids));
    $types_payroll = str_repeat('s', count($payroll_nos));
    $rosters = []; $leaves = []; $punches = [];
    $sql_roster = "SELECT sr.user_id, DAY(sr.roster_date) as day, s.shift_code, s.start_time, s.end_time FROM shift_roster sr JOIN shifts s ON sr.shift_id = s.shift_id WHERE sr.user_id IN ($id_placeholders) AND MONTH(sr.roster_date) = ? AND YEAR(sr.roster_date) = ?";
    $stmt_roster = mysqli_prepare($con_payroll, $sql_roster);
    mysqli_stmt_bind_param($stmt_roster, $types_emp . 'ii', ...array_merge($employee_ids, [$month, $year]));
    mysqli_stmt_execute($stmt_roster);
    $result_roster = mysqli_stmt_get_result($stmt_roster);
    while ($row = mysqli_fetch_assoc($result_roster)) { $rosters[$row['user_id']][$row['day']] = $row; }
    mysqli_stmt_close($stmt_roster);
    $sql_leave = "SELECT lr.empid, lr.start_date, lr.end_date, lt.type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.empid IN ($id_placeholders) AND TRIM(LOWER(lr.status)) = 'approved' AND lr.start_date <= ? AND lr.end_date >= ?";
    $stmt_leave = mysqli_prepare($con_payroll, $sql_leave);
    mysqli_stmt_bind_param($stmt_leave, $types_emp . 'ss', ...array_merge($employee_ids, [$lastDayOfMonth, $firstDayOfMonth]));
    mysqli_stmt_execute($stmt_leave);
    $result_leave = mysqli_stmt_get_result($stmt_leave);
    while ($row = mysqli_fetch_assoc($result_leave)) { $current = new DateTime($row['start_date']); $end = new DateTime($row['end_date']); while ($current <= $end) { if($current->format('n') == $month) $leaves[$row['empid']][$current->format('j')] = $row['type_name']; $current->modify('+1 day'); }}
    mysqli_stmt_close($stmt_leave);
    $sql_punches = "SELECT TRIM(membernumber) AS membernumber, timein, statues FROM attendance WHERE membernumber IN ($payroll_placeholders) AND DATE(timein) BETWEEN ? AND ?";
    $stmt_punches = mysqli_prepare($con_patcom, $sql_punches);
    mysqli_stmt_bind_param($stmt_punches, $types_payroll . 'ss', ...array_merge($payroll_nos, [$firstDayOfMonth, $lastDayOfMonth]));
    mysqli_stmt_execute($stmt_punches);
    $result_punches = mysqli_stmt_get_result($stmt_punches);
    while ($row = mysqli_fetch_assoc($result_punches)) { $date = date('Y-m-d', strtotime($row['timein'])); $punches[$row['membernumber']][$date][] = $row; }
    mysqli_stmt_close($stmt_punches);

    // ========================================================================
    // --- **REWRITTEN** DATA PROCESSING LOGIC WITH ADVANCED CALCULATIONS ---
    // ========================================================================
    $grace_period_minutes = 5;
    foreach ($employees as $employee) {
        $empid = $employee['empid']; $payrollNo = $employee['PayrollNo'];
        $summary = ['present_days'=>0, 'absent_days'=>0, 'leave_days'=>0, 'total_minutes'=>0, 'late_arrivals'=>0, 'early_outs'=>0, 'scheduled_off'=>0, 'missed_punches'=>0];
        $details = [];
        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDateStr = sprintf('%d-%02d-%02d', $year, $month, $d);
            $dayOfWeek = date('w', strtotime($currentDateStr));
            $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
            
            // **NEW**: Initialize record with all new fields
            $record = [
                'date' => date('d-m-Y (D)', strtotime($currentDateStr)),
                'check_in' => null, 'check_out' => null, 'status' => '', 'working_hours' => null, 'notes' => '',
                'late_arrival' => 'No', 'early_out' => 'No', 'overtime' => '0h 0m'
            ];
            $isRosteredOn = isset($rosters[$empid][$d]) && $rosters[$empid][$d]['shift_code'] !== 'OFF';

            if (isset($leaves[$empid][$d])) {
                $record['status'] = 'On Leave'; $summary['leave_days']++; $record['notes'] = $leaves[$empid][$d];
            } elseif ($isRosteredOn) {
                $shift = $rosters[$empid][$d]; $record['notes'] = "Shift: ".$shift['shift_code']." (".date('H:i', strtotime($shift['start_time'])).")";
                if (isset($punches[$payrollNo][$currentDateStr])) {
                    $checkInTimestamp=null; $checkOutTimestamp=null;
                    foreach ($punches[$payrollNo][$currentDateStr] as $punch) {
                        $punchTime=strtotime($punch['timein']); $status = trim(strtolower($punch['statues']));
                        if ($status === 'check in' && (!$checkInTimestamp || $punchTime < $checkInTimestamp)) $checkInTimestamp=$punchTime;
                        if ($status === 'check out' && (!$checkOutTimestamp || $punchTime > $checkOutTimestamp)) $checkOutTimestamp=$punchTime;
                    }
                    
                    if ($checkInTimestamp) {
                        $summary['present_days']++; $record['status']='Present'; $record['check_in']=date('h:i:s A', $checkInTimestamp);
                        $shiftStartTimestamp = strtotime($currentDateStr.' '.$shift['start_time']);
                        if ($checkInTimestamp > $shiftStartTimestamp + ($grace_period_minutes * 60)) {
                            $record['status']='Late'; $summary['late_arrivals']++;
                            $late_minutes = floor(($checkInTimestamp - $shiftStartTimestamp) / 60);
                            $record['late_arrival'] = "Yes ({$late_minutes} min)";
                        }
                        
                        if ($checkOutTimestamp) {
                            $record['check_out']=date('h:i:s A', $checkOutTimestamp); $diff=$checkOutTimestamp - $checkInTimestamp; $hours=floor($diff/3600); $mins=floor(($diff%3600)/60);
                            $summary['total_minutes']+=($hours * 60)+$mins; $record['working_hours']="{$hours}h {$mins}m";
                            
                            $shiftEndTimestamp = strtotime($currentDateStr.' '.$shift['end_time']);
                            if(strtotime($shift['end_time'])<strtotime($shift['start_time'])){$shiftEndTimestamp+=86400;}
                            
                            if($checkOutTimestamp < $shiftEndTimestamp - ($grace_period_minutes * 60)){
                                $record['status']=($record['status']==='Late')?'Late & Early Out':'Early Out'; $summary['early_outs']++;
                                $early_minutes = floor(($shiftEndTimestamp - $checkOutTimestamp) / 60);
                                $record['early_out'] = "Yes ({$early_minutes} min)";
                            }
                            
                            // **NEW** Overtime Calculation
                            if ($checkOutTimestamp > $shiftEndTimestamp) {
                                $overtime_seconds = $checkOutTimestamp - $shiftEndTimestamp;
                                $ot_hours = floor($overtime_seconds / 3600);
                                $ot_mins = floor(($overtime_seconds % 3600) / 60);
                                $record['overtime'] = "{$ot_hours}h {$ot_mins}m";
                            }
                        }
                    } elseif ($checkOutTimestamp) {
                        $record['status'] = 'Missed Punch'; $summary['missed_punches']++; $record['check_out'] = date('h:i:s A', $checkOutTimestamp); $record['notes'] .= " (No Check-In)";
                    } else { $record['status']='Absent'; $summary['absent_days']++; }
                } else { $record['status']='Absent'; $summary['absent_days']++; }
            } else {
                $record['status']=$isWeekend ?'Weekend':'Day Off'; $summary['scheduled_off']++;
                if(isset($rosters[$empid][$d]) && $rosters[$empid][$d]['shift_code'] === 'OFF'){ $record['notes'] = "Scheduled Day Off"; }
            }
            $details[] = $record;
        }
        $summary['total_hours'] = floor($summary['total_minutes'] / 60) . 'h ' . ($summary['total_minutes'] % 60) . 'm';
        $attendance_data[$empid] = ['summary' => $summary, 'details' => $details];
    }
}

// ========================================================================
// --- **REWRITTEN** CSV DOWNLOAD LOGIC ---
// ========================================================================
if (isset($_GET['download'])) {
    if ($_GET['download'] === 'csv_summary') {
        $filename = "attendance_summary_${year}_${month}.csv";
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Payroll No', 'Employee Name', 'Department', 'Worked Hours', 'Present', 'Absent', 'Late', 'Early Out', 'On Leave', 'Off Days', 'Missed Punches']);
        foreach ($employees as $employee) {
            $emp_data = $attendance_data[$employee['empid']]['summary'] ?? null;
            if ($emp_data) { fputcsv($output, [$employee['empid'], $employee['PayrollNo'], $employee['fullName'], $employee['DepartmentName'], $emp_data['total_hours'], $emp_data['present_days'], $emp_data['absent_days'], $emp_data['late_arrivals'], $emp_data['early_outs'], $emp_data['leave_days'], $emp_data['scheduled_off'], $emp_data['missed_punches']]); }
        }
        fclose($output); exit();
    } 
    elseif ($_GET['download'] === 'csv_details') {
        $filename = "attendance_details_all_${year}_${month}.csv";
        header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        
        // **NEW** Add title and subtitle to the CSV
        $report_title = "Detailed Attendance Report for " . date('F Y', mktime(0,0,0,$month,1,$year));
        fputcsv($output, [$report_title]);
        fputcsv($output, []); // Blank row for spacing
        
        // **NEW** Updated header row
        $header = ['Employee ID', 'Payroll No', 'Employee Name', 'Department', 'Date', 'Status', 'Check In', 'Check Out', 'Total Worked', 'Late Arrival', 'Early Departure', 'Overtime', 'Notes'];
        fputcsv($output, $header);

        foreach ($employees as $employee) {
            $emp_data = $attendance_data[$employee['empid']]['details'] ?? [];
            foreach ($emp_data as $record) {
                // **NEW** Data row with all the new columns
                fputcsv($output, [
                    $employee['empid'],
                    $employee['PayrollNo'],
                    $employee['fullName'],
                    $employee['DepartmentName'],
                    $record['date'],
                    $record['status'],
                    $record['check_in'],
                    $record['check_out'],
                    $record['working_hours'],
                    $record['late_arrival'],
                    $record['early_out'],
                    $record['overtime'],
                    $record['notes']
                ]);
            }
             fputcsv($output, []); // Add a blank row between employees for readability
        }
        fclose($output); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Attendance | HMS</title>
    <!-- Meta and CSS Links -->
    <meta charset="utf-8"> <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../vendor/themify-icons/themify-icons.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/plugins.css">
    <link rel="stylesheet" href="../assets/css/themes/theme-1.css" id="skin_color" />
    <style>
        .form-inline .form-group { margin-right: 15px; margin-bottom: 10px; }
        .detail-row { display: none; background-color: #f9f9f9; }
        .detail-row table { margin-bottom: 0; }
        .toggle-details { cursor: pointer; }
    </style>
</head>
<body>
    <div id="app">
    <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title"><div class="row"><div class="col-sm-8"><h1 class="mainTitle">Manage Employee Attendance</h1></div><ol class="breadcrumb"><li><span>Admin</span></li><li class="active"><span>Attendance</span></li></ol></div></section>
                    
                    <!-- Filter Panel -->
                    <div class="panel panel-white">
                        <div class="panel-body">
                            <form id="filterForm" class="form-inline" method="GET">
                                <div class="form-group"><label>Month:</label><select name="month" class="form-control"><?php for($m=1;$m<=12;$m++){$selected=($m==$month)?'selected':'';echo '<option value="'.$m.'" '.$selected.'>'.date('F',mktime(0,0,0,$m,10)).'</option>';}?></select></div>
                                <div class="form-group"><label>Year:</label><select name="year" class="form-control"><?php for($y=date('Y');$y>=date('Y')-5;$y--){$selected=($y==$year)?'selected':'';echo '<option value="'.$y.'" '.$selected.'>'.$y.'</option>';}?></select></div>
                                <div class="form-group"><label>Name:</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="Search name..."></div>
                                <div class="form-group"><label>Payroll:</label><input type="text" name="payroll" class="form-control" value="<?= htmlspecialchars($search_payroll) ?>" placeholder="Search payroll..."></div>
                                <div class="form-group"><label>Dept:</label><select name="department" class="form-control"><option value="">All</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['DepartmentCode']) ?>" <?= ($search_department == $dept['DepartmentCode'] ? 'selected' : '') ?>><?= htmlspecialchars($dept['DepartmentName']) ?></option><?php endforeach; ?></select></div>
                                <button type="submit" class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
                                <a href="<?= basename($_SERVER['PHP_SELF']) ?>" class="btn btn-default">Reset</a>
                            </form>
                        </div>
                    </div>

                    <!-- Main Report Panel -->
                    <div class="panel panel-white">
                        <div class="panel-heading">
                            <h5 class="panel-title" style="display: inline-block;">Attendance Summary for <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h5>
                            <div class="pull-right">
                                <button id="expand-all" class="btn btn-info btn-sm"><i class="fa fa-plus-square-o"></i> Expand All</button>
                                <button id="collapse-all" class="btn btn-info btn-sm"><i class="fa fa-minus-square-o"></i> Collapse All</button>
                                <a href="?<?= build_query_string(['download' => 'csv_summary']) ?>" class="btn btn-success btn-sm"><i class="fa fa-download"></i> Download Summary</a>
                                <a href="?<?= build_query_string(['download' => 'csv_details']) ?>" class="btn btn-primary btn-sm"><i class="fa fa-download"></i> Download Details</a>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead><tr><th>Actions</th><th>Employee Name</th><th>Department</th><th>Worked Hours</th><th>Present</th><th>Absent</th><th>Late</th><th>On Leave</th><th>Missed Punch</th></tr></thead>
                                    <tbody>
                                        <?php if(empty($employees)): ?>
                                            <tr><td colspan="9" class="text-center">No employees found.</td></tr>
                                        <?php else: foreach ($employees as $employee): $summary = $attendance_data[$employee['empid']]['summary']; ?>
                                            <tr class="summary-row">
                                                <td><button class="btn btn-xs btn-default toggle-details" data-empid="<?= $employee['empid'] ?>"><i class="fa fa-plus"></i></button></td>
                                                <td><?= htmlspecialchars($employee['fullName']) ?><br><small class="text-muted">ID: <?= htmlspecialchars($employee['PayrollNo']) ?></small></td>
                                                <td><?= htmlspecialchars($employee['DepartmentName']) ?></td>
                                                <td><span class="label label-primary"><?= $summary['total_hours'] ?></span></td>
                                                <td><span class="label label-success"><?= $summary['present_days'] ?></span></td>
                                                <td><span class="label label-danger"><?= $summary['absent_days'] ?></span></td>
                                                <td><span class="label label-warning"><?= $summary['late_arrivals'] ?></span></td>
                                                <td><span class="label label-info"><?= $summary['leave_days'] ?></span></td>
                                                <td><span class="label label-warning"><?= $summary['missed_punches'] ?></span></td>
                                            </tr>
                                            <tr class="detail-row" data-empid="<?= $employee['empid'] ?>">
                                                <td colspan="9">
                                                    <div style="padding: 15px;">
                                                        <table class="table table-bordered table-condensed">
                                                            <!-- **NEW** Updated header for expandable view -->
                                                            <thead><tr><th>Date</th><th>Status</th><th>Check In/Out</th><th>Total Hours</th><th>Late</th><th>Early</th><th>Overtime</th><th>Notes</th></tr></thead>
                                                            <tbody>
                                                                <?php foreach($attendance_data[$employee['empid']]['details'] as $record): ?>
                                                                <tr>
                                                                    <td><?= $record['date'] ?></td>
                                                                    <td><?= htmlspecialchars($record['status']) ?></td>
                                                                    <td><?= $record['check_in'] ?? 'N/A' ?> - <?= $record['check_out'] ?? 'N/A' ?></td>
                                                                    <td><?= $record['working_hours'] ?? 'N/A' ?></td>
                                                                    <td><?= $record['late_arrival'] ?></td>
                                                                    <td><?= $record['early_out'] ?></td>
                                                                    <td><?= $record['overtime'] ?></td>
                                                                    <td><?= htmlspecialchars($record['notes']) ?></td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
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
    <!-- JS Includes -->
    <script src="../vendor/jquery/jquery.min.js"></script>
    <script src="../vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="../vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="../assets/js/main.js"></script>
    <script>
    $(document).ready(function() {
        Main.init();
        $('.toggle-details').on('click', function() {
            var btn = $(this); var empid = btn.data('empid'); var icon = btn.find('i');
            var detailRow = $('tr.detail-row[data-empid="' + empid + '"]');
            detailRow.slideToggle(200);
            if (icon.hasClass('fa-plus')) { icon.removeClass('fa-plus').addClass('fa-minus'); } 
            else { icon.removeClass('fa-minus').addClass('fa-plus'); }
        });
        $('#expand-all').on('click', function() { $('.detail-row').slideDown(200); $('.toggle-details i').removeClass('fa-plus').addClass('fa-minus'); });
        $('#collapse-all').on('click', function() { $('.detail-row').slideUp(200); $('.toggle-details i').removeClass('fa-minus').addClass('fa-plus'); });
    });
    </script>
</body>
</html>