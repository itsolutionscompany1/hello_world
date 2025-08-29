<?php
session_start();
error_reporting(E_ALL);
include('../include/config.php');

// ========================================================================
// SECURITY, SETUP, and FILTERS
// ========================================================================
if (strlen($_SESSION['id'] ?? '') == 0) { header('location:../logout.php'); exit(); }
if (!isset($con_payroll) || !$con_payroll instanceof mysqli) { die("FATAL ERROR: Database connection failed."); }

$message = '';
$month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$search_name = trim($_GET['name'] ?? '');
$search_payroll = trim($_GET['payroll'] ?? '');
$search_department = trim($_GET['department'] ?? '');

// ========================================================================
// HANDLE POST ACTIONS
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- CORRECTED AND ENHANCED BULK APPLY LOGIC ---
    if ($_POST['action'] == 'bulk_apply') {
        $bulk_dept = $_POST['bulk_department'] ?? null;
        $bulk_shift_id = $_POST['bulk_shift_id'] ?? null;
        $bulk_weekend_action = $_POST['bulk_weekend_action'] ?? 'ignore';
        $apply_to_days = $_POST['apply_to_days'] ?? []; 
        
        if (empty($bulk_dept) || (empty($bulk_shift_id) && $bulk_weekend_action == 'ignore')) {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><strong>Error!</strong> Please select a department AND either a shift to apply or a weekend action. <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $holidays_for_bulk = [];
            $sql_h = "SELECT holiday_date FROM public_holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?";
            $stmt_h = mysqli_prepare($con_payroll, $sql_h);
            if($stmt_h) { mysqli_stmt_bind_param($stmt_h, 'ii', $year, $month); mysqli_stmt_execute($stmt_h); $res_h = mysqli_stmt_get_result($stmt_h); while($row_h = mysqli_fetch_assoc($res_h)) { $holidays_for_bulk[] = $row_h['holiday_date']; } mysqli_stmt_close($stmt_h); }

            $emp_ids_for_dept = [];
            $sql_get_emps = "SELECT empid FROM employees WHERE DepartmentCode = ? AND Status = 'ACTIVE'";
            $stmt_get_emps = mysqli_prepare($con_payroll, $sql_get_emps);
            mysqli_stmt_bind_param($stmt_get_emps, 's', $bulk_dept); mysqli_stmt_execute($stmt_get_emps); $result_emps = mysqli_stmt_get_result($stmt_get_emps); while($row = mysqli_fetch_assoc($result_emps)) { $emp_ids_for_dept[] = $row['empid']; } mysqli_stmt_close($stmt_get_emps);

            $off_shift_id = null;
            $off_shift_res = mysqli_query($con_payroll, "SELECT shift_id FROM shifts WHERE shift_code = 'OFF' LIMIT 1");
            if ($off_shift_res && mysqli_num_rows($off_shift_res) > 0) { $off_shift_id = mysqli_fetch_assoc($off_shift_res)['shift_id']; }
            if ($bulk_weekend_action === 'set_off' && !$off_shift_id) { $message = '<div class="alert alert-danger"><strong>Error!</strong> The "OFF" shift is not defined. Please add it via the database before using this feature.</div>'; }

            if (!empty($emp_ids_for_dept) && empty($message)) {
                $values_to_insert = []; $placeholders = []; $types = '';
                
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $current_date_str = sprintf('%d-%02d-%02d', $year, $month, $d);
                    $day_of_week = date('w', strtotime($current_date_str));
                    
                    $day_type = 'weekday';
                    if (in_array($current_date_str, $holidays_for_bulk)) { $day_type = 'holiday'; } 
                    elseif ($day_of_week == 0 || $day_of_week == 6) { $day_type = 'weekend'; }

                    $shift_id_to_apply = null;
                    if ($day_type === 'weekend' && $bulk_weekend_action === 'set_off' && $off_shift_id) { $shift_id_to_apply = $off_shift_id; } 
                    elseif (in_array($day_type, $apply_to_days) && !empty($bulk_shift_id)) { $shift_id_to_apply = $bulk_shift_id; }

                    if ($shift_id_to_apply) {
                        foreach ($emp_ids_for_dept as $empid) {
                            $placeholders[] = '(?, ?, ?)'; array_push($values_to_insert, $empid, $shift_id_to_apply, $current_date_str); $types .= 'iis';
                        }
                    }
                }

                if (!empty($placeholders)) {
                    mysqli_begin_transaction($con_payroll);
                    try {
                        $sql_bulk_insert = "INSERT INTO shift_roster (user_id, shift_id, roster_date) VALUES " . implode(', ', $placeholders) . " ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)";
                        $stmt_bulk = mysqli_prepare($con_payroll, $sql_bulk_insert);
                        mysqli_stmt_bind_param($stmt_bulk, $types, ...$values_to_insert);
                        mysqli_stmt_execute($stmt_bulk);
                        $affected_rows = mysqli_stmt_affected_rows($stmt_bulk);
                        mysqli_commit($con_payroll);
                        $message = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Success!</strong> Bulk changes applied. ' . $affected_rows . ' records updated. <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
                    } catch (Exception $e) {
                        mysqli_rollback($con_payroll);
                        $message = '<div class="alert alert-danger"><strong>Error!</strong> A database error occurred. No changes were made.</div>';
                    }
                } else { $message = '<div class="alert alert-info">No days matched your criteria. No changes were made.</div>'; }
            }
        }
    }
    
    // --- COMPLETE CSV UPLOAD LOGIC ---
    if ($_POST['action'] == 'upload') {
        if (isset($_FILES['roster_csv']) && $_FILES['roster_csv']['error'] == UPLOAD_ERR_OK) {
            $shift_map = [];
            $map_result = mysqli_query($con_payroll, "SELECT shift_id, shift_code FROM shifts");
            while ($row = mysqli_fetch_assoc($map_result)) { $shift_map[strtoupper(trim($row['shift_code']))] = $row['shift_id']; }
            $file = fopen($_FILES['roster_csv']['tmp_name'], 'r'); fgetcsv($file); $updates = []; $deletes = [];
            while (($row = fgetcsv($file)) !== FALSE) {
                $empid = trim($row[0]); if (empty($empid) || !is_numeric($empid)) continue;
                for ($d = 1; $d <= $daysInMonth; $d++) {
                    $col_index = $d + 3; if (isset($row[$col_index])) {
                        $shift_code = strtoupper(trim($row[$col_index])); $roster_date = sprintf('%d-%02d-%02d', $year, $month, $d);
                        if (!empty($shift_code) && isset($shift_map[$shift_code])) { $updates[] = ['user_id' => $empid, 'shift_id' => $shift_map[$shift_code], 'roster_date' => $roster_date]; } 
                        else { $deletes[] = ['user_id' => $empid, 'roster_date' => $roster_date]; }
                    }
                }
            } fclose($file);
            mysqli_begin_transaction($con_payroll);
            try {
                $deletes_executed = 0; $updates_executed = 0;
                if (!empty($deletes)) {
                    $sql_delete = "DELETE FROM shift_roster WHERE (user_id = ? AND roster_date = ?)"; $stmt_delete = mysqli_prepare($con_payroll, $sql_delete);
                    foreach($deletes as $del) { mysqli_stmt_bind_param($stmt_delete, 'is', $del['user_id'], $del['roster_date']); mysqli_stmt_execute($stmt_delete); $deletes_executed += mysqli_stmt_affected_rows($stmt_delete); } mysqli_stmt_close($stmt_delete);
                }
                if (!empty($updates)) {
                    $placeholders = implode(', ', array_fill(0, count($updates), '(?, ?, ?)')); $types = str_repeat('iis', count($updates)); $values = [];
                    foreach($updates as $upd) { array_push($values, $upd['user_id'], $upd['shift_id'], $upd['roster_date']); }
                    $sql_update = "INSERT INTO shift_roster (user_id, shift_id, roster_date) VALUES $placeholders ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)";
                    $stmt_update = mysqli_prepare($con_payroll, $sql_update); mysqli_stmt_bind_param($stmt_update, $types, ...$values); mysqli_stmt_execute($stmt_update); $updates_executed = mysqli_stmt_affected_rows($stmt_update); mysqli_stmt_close($stmt_update);
                }
                mysqli_commit($con_payroll); $message = '<div class="alert alert-success alert-dismissible fade show" role="alert"><strong>Upload Successful!</strong><br>'.$updates_executed.' shifts inserted/updated.<br>'.$deletes_executed.' shifts cleared from roster.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            } catch (Exception $e) { mysqli_rollback($con_payroll); $message = '<div class="alert alert-danger"><strong>Upload Failed!</strong> A database error occurred. No changes were made.</div>'; }
        } else { $message = '<div class="alert alert-danger"><strong>Upload Error!</strong> Please select a valid CSV file.</div>'; }
    }
}

// ========================================================================
// FETCH DATA FOR PAGE DISPLAY
// ========================================================================
$departments = []; $dept_result = mysqli_query($con_payroll, "SELECT DepartmentCode, DepartmentName FROM departments ORDER BY DepartmentName"); while ($row = mysqli_fetch_assoc($dept_result)) { $departments[] = $row; }
$shifts = []; $shift_sql = "SELECT shift_id, shift_code, shift_name, TIME_FORMAT(start_time, '%H:%i') AS start_time_formatted, TIME_FORMAT(end_time, '%H:%i') AS end_time_formatted, IF(end_time > start_time, TIME_TO_SEC(TIMEDIFF(end_time, start_time)), TIME_TO_SEC(TIMEDIFF('24:00:00', start_time)) + TIME_TO_SEC(end_time)) as duration_seconds FROM shifts ORDER BY shift_code"; $shift_result = mysqli_query($con_payroll, $shift_sql); while ($row = mysqli_fetch_assoc($shift_result)) { $shifts[$row['shift_id']] = $row; }
$holidays_this_month = []; $sql_h_view = "SELECT holiday_date FROM public_holidays WHERE YEAR(holiday_date) = ? AND MONTH(holiday_date) = ?"; $stmt_h_view = mysqli_prepare($con_payroll, $sql_h_view); if($stmt_h_view) { mysqli_stmt_bind_param($stmt_h_view, 'ii', $year, $month); mysqli_stmt_execute($stmt_h_view); $res_h_view = mysqli_stmt_get_result($stmt_h_view); while($row_h_view = mysqli_fetch_assoc($res_h_view)) { $holidays_this_month[] = $row_h_view['holiday_date']; } mysqli_stmt_close($stmt_h_view); }
$employee_sql = "SELECT e.empid, e.PayrollNo, CONCAT(e.Surname, ' ', e.OtherNames) AS fullName, d.DepartmentName FROM employees e LEFT JOIN departments d ON e.DepartmentCode = d.DepartmentCode WHERE e.Status = 'ACTIVE'"; $params = []; $types = ''; $where_clauses = []; if (!empty($search_name)) { $where_clauses[] = "CONCAT(e.Surname, ' ', e.OtherNames) LIKE ?"; $types .= 's'; $params[] = "%$search_name%"; } if (!empty($search_payroll)) { $where_clauses[] = "e.PayrollNo LIKE ?"; $types .= 's'; $params[] = "%$search_payroll%"; } if (!empty($search_department)) { $where_clauses[] = "e.DepartmentCode = ?"; $types .= 's'; $params[] = $search_department; } if (!empty($where_clauses)) { $employee_sql .= " AND " . implode(" AND ", $where_clauses); } $employee_sql .= " ORDER BY d.DepartmentName, e.Surname, e.OtherNames"; $stmt_emp = mysqli_prepare($con_payroll, $employee_sql); if (!empty($params)) { mysqli_stmt_bind_param($stmt_emp, $types, ...$params); } mysqli_stmt_execute($stmt_emp); $employee_result = mysqli_stmt_get_result($stmt_emp); $employees = mysqli_fetch_all($employee_result, MYSQLI_ASSOC); mysqli_stmt_close($stmt_emp);
$rosterData = []; if (!empty($employees)) { $employee_ids = array_column($employees, 'empid'); $id_placeholders = implode(',', array_fill(0, count($employee_ids), '?')); $roster_sql = "SELECT sr.user_id, DAY(sr.roster_date) as day, s.shift_code, s.shift_id FROM shift_roster sr JOIN shifts s ON sr.shift_id = s.shift_id WHERE MONTH(sr.roster_date) = ? AND YEAR(sr.roster_date) = ? AND sr.user_id IN ($id_placeholders)"; $stmt_roster = mysqli_prepare($con_payroll, $roster_sql); if(count($employee_ids)>0){$roster_types = 'ii' . str_repeat('i', count($employee_ids)); $roster_params = array_merge([$month, $year], $employee_ids); mysqli_stmt_bind_param($stmt_roster, $roster_types, ...$roster_params); mysqli_stmt_execute($stmt_roster); $roster_result = mysqli_stmt_get_result($stmt_roster); while ($row = mysqli_fetch_assoc($roster_result)) { $rosterData[$row['user_id']][$row['day']] = ['code' => $row['shift_code'], 'id' => $row['shift_id']]; }} mysqli_stmt_close($stmt_roster); }

// ========================================================================
// HANDLE DOWNLOAD ACTION
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'download') {
    header('Content-Type: text/csv'); header('Content-Disposition: attachment; filename="shift_roster_'.$year.'_'.str_pad($month, 2, '0', STR_PAD_LEFT).'.csv"'); $output = fopen('php://output', 'w'); $header = ['empid', 'PayrollNo', 'EmployeeName', 'Department']; for ($d = 1; $d <= $daysInMonth; $d++) { $header[] = $d; } fputcsv($output, $header); foreach ($employees as $employee) { $row = [$employee['empid'], $employee['PayrollNo'], $employee['fullName'], $employee['DepartmentName']]; for ($d = 1; $d <= $daysInMonth; $d++) { $row[] = $rosterData[$employee['empid']][$d]['code'] ?? ''; } fputcsv($output, $row); } fclose($output); exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Shift Roster Management | HMS</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" /><link rel="stylesheet" href="assets/css/styles.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

<link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
<link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
<link rel="stylesheet" href="../vendor/fontawesome/css/font-awesome.min.css">
<link rel="stylesheet" href="../vendor/themify-icons/themify-icons.min.css">
<link rel="stylesheet" href="../assets/css/styles.css">
<link rel="stylesheet" href="../assets/css/plugins.css">
<link rel="stylesheet" href="../assets/css/themes/theme-1.css" id="skin_color" />
    
    <style>
        .roster-table th, .roster-table td { text-align: center; vertical-align: middle; min-width: 60px; }
        .roster-table th.employee-name, .roster-table td.employee-name { text-align: left; min-width: 220px; position: sticky; left: 0; background-color: #fff; z-index: 10; }
        .weekend { background-color: #f8f9fa; font-weight: bold; }
        .table-responsive { max-height: 80vh; }
        .roster-table th.total-hours, .roster-table td.total-hours { position: sticky; right: 0; background-color: #f1f5f9; z-index: 10; font-weight: bold; border-left: 2px solid #dee2e6; min-width: 100px; }
        .editable-cell { cursor: pointer; transition: background-color 0.2s; }
        .editable-cell:hover { background-color: #e9ecef; }
        .editable-cell select { width: 100%; border: 1px solid #0d6efd; border-radius: 4px; padding: 2px; }
        .saving { opacity: 0.5; pointer-events: none; background-color: #fff3cd; }
        .holiday { background-color: #d1e7dd !important; color: #0f5132; font-weight: bold; }
    </style>
</head>
<body>
<div id="app">
    <?php include('include/sidebar.php'); ?>
    <div class="app-content">
        <?php include('include/header.php'); ?>
        <div class="main-content">
            <div class="container-fluid">
                <h1 class="mainTitle">Manage Shift Roster</h1>
                <?= $message ?>
                <div class="row">
                    <div class="col-lg-3">
                        <form method="GET" action="">
                            <div class="card mb-4">
                                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-filter me-2"></i>Filter Roster</h5></div>
                                <div class="card-body">
                                    <div class="mb-3"><label for="month" class="form-label">Month:</label><select name="month" id="month" class="form-select"><?php for ($m = 1; $m <= 12; $m++) { echo '<option value="'.$m.'" '.($m == $month ? 'selected' : '').'>'.date('F', mktime(0,0,0,$m,1)).'</option>'; } ?></select></div>
                                    <div class="mb-3"><label for="year" class="form-label">Year:</label><select name="year" id="year" class="form-select"><?php for ($y = date('Y') - 2; $y <= date('Y') + 1; $y++) { echo '<option value="'.$y.'" '.($y == $year ? 'selected' : '').'>'.$y.'</option>'; } ?></select></div><hr>
                                    <div class="mb-3"><label for="name" class="form-label">Employee Name:</label><input type="text" name="name" id="name" class="form-control" value="<?= htmlspecialchars($search_name) ?>" placeholder="Search by name..."></div>
                                    <div class="mb-3"><label for="payroll" class="form-label">Payroll No:</label><input type="text" name="payroll" id="payroll" class="form-control" value="<?= htmlspecialchars($search_payroll) ?>" placeholder="Search by payroll..."></div>
                                    <div class="mb-3"><label for="department" class="form-label">Department:</label><select name="department" id="department" class="form-select"><option value="">All Departments</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['DepartmentCode']) ?>" <?= ($search_department == $dept['DepartmentCode'] ? 'selected' : '') ?>><?= htmlspecialchars($dept['DepartmentName']) ?></option><?php endforeach; ?></select></div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-2"></i>Apply Filters</button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="">
                            <input type="hidden" name="month" value="<?= $month ?>"><input type="hidden" name="year" value="<?= $year ?>">
                            <div class="card mb-4">
                                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-layer-group me-2"></i>Bulk Roster Actions</h5></div>
                                <div class="card-body">
                                    <div class="mb-3"><label for="bulk_department" class="form-label">Select Department:</label><select name="bulk_department" id="bulk_department" class="form-select" required><option value="">-- Choose Department --</option><?php foreach ($departments as $dept): ?><option value="<?= htmlspecialchars($dept['DepartmentCode']) ?>"><?= htmlspecialchars($dept['DepartmentName']) ?></option><?php endforeach; ?></select></div><hr>
                                    <div class="mb-3"><label for="bulk_shift_id" class="form-label">Apply this Shift:</label><select name="bulk_shift_id" id="bulk_shift_id" class="form-select"><option value="">-- No Shift Selected --</option><?php foreach ($shifts as $shift): if($shift['shift_code'] !== 'OFF'): ?><option value="<?= $shift['shift_id'] ?>"><?= htmlspecialchars($shift['shift_code']) ?> (<?= $shift['start_time_formatted'] ?> - <?= $shift['end_time_formatted'] ?>)</option><?php endif; endforeach; ?></select></div>
                                    <div class="mb-3"><label class="form-label">To these day types:</label>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="weekday" id="apply_weekday" name="apply_to_days[]" checked><label class="form-check-label" for="apply_weekday">Weekdays</label></div>
                                        <div class="form-check"><input class="form-check-input" type="checkbox" value="holiday" id="apply_holiday" name="apply_to_days[]" checked><label class="form-check-label" for="apply_holiday">Public Holidays</label></div>
                                    </div><hr>
                                    <div class="mb-3"><label for="bulk_weekend_action" class="form-label">For Weekends:</label><select name="bulk_weekend_action" id="bulk_weekend_action" class="form-select"><option value="set_off" selected>Set as 'OFF' Day</option><option value="ignore">Do Nothing (Keep Existing)</option></select></div>
                                    <button type="submit" name="action" value="bulk_apply" class="btn btn-warning w-100" id="bulk-apply-btn">Apply Bulk Changes</button>
                                </div>
                            </div>
                        </form>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                             <div class="card">
                                <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-exchange-alt me-2"></i>Import / Export</h5></div>
                                <div class="card-body">
                                    <button type="submit" name="action" value="download" class="btn btn-success w-100 mb-3"><i class="fas fa-download me-2"></i>Download Filtered Roster CSV</button>
                                    <div class="mb-3"><label for="roster_csv" class="form-label"><strong>Upload Roster CSV:</strong></label><input type="file" name="roster_csv" id="roster_csv" accept=".csv" class="form-control" required></div>
                                    <button type="submit" name="action" value="upload" class="btn btn-info w-100"><i class="fas fa-upload me-2"></i>Upload and Process File</button>
                                </div>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-9">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0">Shift Roster for <?= date('F Y', mktime(0,0,0,$month,1,$year)) ?></h5></div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped table-hover roster-table mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="employee-name">Employee</th>
                                                <?php for ($d = 1; $d <= $daysInMonth; $d++): $date=mktime(0,0,0,$month,$d,$year); $full_date_str=date('Y-m-d',$date); $dayOfWeek=date('w',$date); $isWeekend=($dayOfWeek==0||$dayOfWeek==6); $isHoliday=in_array($full_date_str,$holidays_this_month); $th_class=$isHoliday?'holiday':($isWeekend?'weekend':''); ?>
                                                    <th class="<?= $th_class ?>"><?= date('D', $date) ?><br><?= $d ?></th>
                                                <?php endfor; ?>
                                                <th class="total-hours">Total Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($employees)): ?>
                                                <tr><td colspan="<?= $daysInMonth + 2 ?>" class="text-center p-5"><h4>No employees match the selected criteria.</h4></td></tr>
                                            <?php else: foreach ($employees as $employee): ?>
                                                <tr>
                                                    <td class="employee-name"><strong><?= htmlspecialchars($employee['fullName']) ?></strong><br><small class="text-muted">ID: <?= htmlspecialchars($employee['PayrollNo']) ?></small></td>
                                                    <?php $totalSecondsInMonth=0; for ($d=1; $d<=$daysInMonth; $d++): $current_shift=$rosterData[$employee['empid']][$d]??null; if($current_shift){$shift_id=$current_shift['id'];if(isset($shifts[$shift_id])){$totalSecondsInMonth+=$shifts[$shift_id]['duration_seconds'];}} $shift_code=$current_shift['code']??null; $date=mktime(0,0,0,$month,$d,$year); $full_date_str=date('Y-m-d',$date); $dayOfWeek=date('w',$date); $isWeekend=($dayOfWeek==0||$dayOfWeek==6); $isHoliday=in_array($full_date_str,$holidays_this_month); $td_class=$isHoliday?'holiday':($isWeekend?'weekend':''); ?>
                                                        <td class="editable-cell <?= $td_class ?>" data-empid="<?= $employee['empid'] ?>" data-date="<?= $full_date_str ?>" data-current-shift-id="<?= $current_shift['id'] ?? '' ?>">
                                                            <?= $shift_code ? '<span class="badge bg-'.($shift_code=='OFF'?'secondary':'primary').'">' . htmlspecialchars($shift_code) . '</span>' : '-' ?>
                                                        </td>
                                                    <?php endfor; ?>
                                                    <?php $totalHours=floor($totalSecondsInMonth/3600); $totalMinutes=floor(($totalSecondsInMonth%3600)/60); $formattedTotal=sprintf('%02d:%02d',$totalHours,$totalMinutes); ?>
                                                    <td class="total-hours"><?= $formattedTotal ?></td>
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
    </div>
   
    
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
        });
    </script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const bulkApplyBtn = document.getElementById('bulk-apply-btn');
    if (bulkApplyBtn) {
        bulkApplyBtn.addEventListener('click', function(e) {
            const deptSelect = document.getElementById('bulk_department');
            if (!deptSelect.value) { alert('Please select a department.'); e.preventDefault(); return; }
            const confirmation = confirm(`Are you sure you want to apply these bulk changes to the selected department?\n\nThis will overwrite existing shifts based on your selections.`);
            if (!confirmation) { e.preventDefault(); }
        });
    }

    const shifts = <?= json_encode(array_values($shifts)) ?>;
    const rosterTable = document.querySelector('.roster-table tbody');
    if (rosterTable) {
        function createShiftDropdown(currentShiftId) {
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';
            select.innerHTML = '<option value="0">- Clear Shift -</option>';
            shifts.forEach(shift => {
                const option = document.createElement('option');
                option.value = shift.shift_id;
                option.textContent = `${shift.shift_code} (${shift.start_time_formatted} - ${shift.end_time_formatted})`;
                if (shift.shift_id == currentShiftId) { option.selected = true; }
                select.appendChild(option);
            });
            return select;
        }
        rosterTable.addEventListener('click', function(e) {
            const cell = e.target.closest('.editable-cell');
            if (!cell || cell.querySelector('select')) return;
            const originalContent = cell.innerHTML; const currentShiftId = cell.dataset.currentShiftId;
            const dropdown = createShiftDropdown(currentShiftId); cell.innerHTML = ''; cell.appendChild(dropdown); dropdown.focus();
            const saveChanges = async () => {
                const selectedShiftId = dropdown.value;
                if (selectedShiftId == (currentShiftId || '0')) { cell.innerHTML = originalContent; return; }
                cell.classList.add('saving');
                try {
                    const response = await fetch('update_shift.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ empid: cell.dataset.empid, roster_date: cell.dataset.date, shift_id: selectedShiftId }) });
                    if (!response.ok) { const errorResult = await response.json(); throw new Error(errorResult.message || 'Server error'); }
                    window.location.reload();
                } catch (error) { console.error('Update failed:', error); alert('Error: Could not update the shift. ' + error.message); cell.innerHTML = originalContent; cell.classList.remove('saving'); }
            };
            dropdown.addEventListener('change', saveChanges);
            dropdown.addEventListener('blur', () => { setTimeout(() => { if (cell.contains(dropdown)) cell.innerHTML = originalContent; }, 150); });
        });
    }
});
</script>
</body>
</html>