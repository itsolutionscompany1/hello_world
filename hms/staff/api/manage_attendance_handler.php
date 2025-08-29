<?php
/**
 * API for handling BULK staff attendance data for the HR/Admin management page.
 * VERSION 4: Implements "Missed Punch" logic and robust, case-insensitive status checks.
 */

session_start();
header('Content-Type: application/json');
include('../../include/config.php');

// --- Main Execution Block ---
try {
    // 1. Validate Connections & Permissions
    if (!isset($con_patcom) || !$con_patcom instanceof mysqli) { throw new Exception("Attendance DB not available.", 503); }
    if (!isset($con_payroll) || !$con_payroll instanceof mysqli) { throw new Exception("Payroll DB not available.", 503); }
    if (!isset($_SESSION['id'])) { throw new Exception("Authentication required.", 401); }
    
    // 2. Get and Validate Filters
    $month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['default' => date('n')]]);
    $year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['default' => date('Y')]]);
    $department = filter_input(INPUT_GET, 'department', FILTER_SANITIZE_STRING);
    $name_search = filter_input(INPUT_GET, 'name', FILTER_SANITIZE_STRING);

    // 3. Fetch filtered employees
    $sql_emp = "SELECT e.empid, e.PayrollNo, CONCAT(e.Surname, ' ', e.OtherNames) AS fullName, d.DepartmentName 
                FROM employees e 
                JOIN departments d ON e.DepartmentCode = d.DepartmentCode 
                WHERE e.Status = 'ACTIVE'";
    $params_emp = []; $types_emp = '';
    
    if (!empty($department)) {
        $sql_emp .= " AND e.DepartmentCode = ?";
        $types_emp .= 's';
        $params_emp[] = $department;
    }
    if (!empty($name_search)) {
        $sql_emp .= " AND CONCAT(e.Surname, ' ', e.OtherNames) LIKE ?";
        $types_emp .= 's';
        $params_emp[] = "%{$name_search}%";
    }
    
    $sql_emp .= " ORDER BY fullName";
    $stmt_emp = mysqli_prepare($con_payroll, $sql_emp);
    if (!empty($params_emp)) {
        mysqli_stmt_bind_param($stmt_emp, $types_emp, ...$params_emp);
    }
    mysqli_stmt_execute($stmt_emp);
    $employees = mysqli_fetch_all(mysqli_stmt_get_result($stmt_emp), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_emp);

    if (empty($employees)) {
        echo json_encode(['success' => true, 'data' => []]);
        exit();
    }
    
    // 4. Get and process all data in bulk
    $all_data = getBulkAttendanceData($con_patcom, $con_payroll, $employees, $month, $year);

    // Re-index array for JSON compatibility if needed
    echo json_encode(['success' => true, 'data' => array_values($all_data)]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


function getBulkAttendanceData($db_patcom, $db_payroll, $employees, $month, $year) {
    $employee_ids = array_column($employees, 'empid');
    $payroll_nos = array_column($employees, 'PayrollNo');
    $firstDay = "$year-$month-01";
    $lastDay = date("Y-m-t", strtotime($firstDay));
    $daysInMonth = date('t', strtotime($firstDay));

    // A. Bulk fetch Rosters
    $rosters = [];
    if (!empty($employee_ids)) {
        $id_placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
        $sql_roster = "SELECT user_id, DAY(roster_date) as day, s.shift_code, s.start_time, s.end_time FROM shift_roster sr JOIN shifts s ON sr.shift_id = s.shift_id WHERE sr.user_id IN ($id_placeholders) AND MONTH(roster_date) = ? AND YEAR(roster_date) = ?";
        $stmt_roster = mysqli_prepare($db_payroll, $sql_roster);
        $types_roster = str_repeat('i', count($employee_ids)) . 'ii';
        $params_roster = array_merge($employee_ids, [$month, $year]);
        mysqli_stmt_bind_param($stmt_roster, $types_roster, ...$params_roster);
        mysqli_stmt_execute($stmt_roster);
        $result_roster = mysqli_stmt_get_result($stmt_roster);
        while ($row = mysqli_fetch_assoc($result_roster)) { $rosters[$row['user_id']][$row['day']] = $row; }
        mysqli_stmt_close($stmt_roster);
    }

    // B. Bulk fetch Leaves
    $leaves = [];
    if (!empty($employee_ids)) {
        $sql_leave = "SELECT empid, start_date, end_date, lt.type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.empid IN ($id_placeholders) AND lr.status='approved' AND lr.start_date <= ? AND lr.end_date >= ?";
        $stmt_leave = mysqli_prepare($db_payroll, $sql_leave);
        $types_leave = str_repeat('i', count($employee_ids)) . 'ss';
        $params_leave = array_merge($employee_ids, [$lastDay, $firstDay]);
        mysqli_stmt_bind_param($stmt_leave, $types_leave, ...$params_leave);
        mysqli_stmt_execute($stmt_leave);
        $result_leave = mysqli_stmt_get_result($stmt_leave);
        while($row = mysqli_fetch_assoc($result_leave)){
            $current = new DateTime($row['start_date']); $end = new DateTime($row['end_date']);
            while($current <= $end){ if($current->format('n') == $month) $leaves[$row['empid']][$current->format('j')] = $row['type_name']; $current->modify('+1 day'); }
        }
        mysqli_stmt_close($stmt_leave);
    }
    
    // C. Bulk fetch Punches
    $punches = [];
    if (!empty($payroll_nos)) {
        $payroll_placeholders = implode(',', array_fill(0, count($payroll_nos), '?'));
        $sql_punches = "SELECT membernumber, timein, statues FROM attendance WHERE membernumber IN ($payroll_placeholders) AND DATE(timein) BETWEEN ? AND ?";
        $stmt_punches = mysqli_prepare($db_patcom, $sql_punches);
        $types_punches = str_repeat('s', count($payroll_nos)) . 'ss';
        $params_punches = array_merge($payroll_nos, [$firstDay, $lastDay]);
        mysqli_stmt_bind_param($stmt_punches, $types_punches, ...$params_punches);
        mysqli_stmt_execute($stmt_punches);
        $result_punches = mysqli_stmt_get_result($stmt_punches);
        while ($row = mysqli_fetch_assoc($result_punches)) { $date = date('Y-m-d', strtotime($row['timein'])); $punches[$row['membernumber']][$date][] = $row; }
        mysqli_stmt_close($stmt_punches);
    }
    
    // D. Process data for each employee
    $processed_data = [];
    $late_grace_period_minutes = 5;
    $early_out_grace_period_minutes = 5;
    foreach ($employees as $employee) {
        $empid = $employee['empid']; $payrollNo = $employee['PayrollNo'];
        // **MODIFIED:** Added 'missed_punches' and renamed 'off_days'
        $summary = ['present_days'=>0, 'absent_days'=>0, 'leave_days'=>0, 'total_hours'=>'0h 0m', 'late_arrivals'=>0, 'early_outs'=>0, 'scheduled_off'=>0, 'missed_punches' => 0];
        $details = []; $totalMinutes = 0;

        for ($d = 1; $d <= $daysInMonth; $d++) {
            $currentDateStr = "$year-$month-$d";
            $dayOfWeek = date('w', strtotime($currentDateStr));
            $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
            $record = ['date' => date('d-m-Y (D)', strtotime($currentDateStr)), 'status' => '', 'check_in' => null, 'check_out' => null, 'working_hours' => null, 'notes' => ''];
            
            $isRosteredOn = isset($rosters[$empid][$d]) && $rosters[$empid][$d]['shift_code'] !== 'OFF';
            $isOnLeave = isset($leaves[$empid][$d]);
            $hasPunches = isset($punches[$payrollNo][$currentDateStr]);

            if ($isOnLeave) {
                $record['status'] = 'On Leave';
                $summary['leave_days']++;
                $record['notes'] = $leaves[$empid][$d];
            } elseif ($isRosteredOn) {
                $shift = $rosters[$empid][$d];
                $record['notes'] = "Shift: " . $shift['shift_code'];
                
                if ($hasPunches) {
                    $checkIn = null; $checkOut = null;
                    foreach ($punches[$payrollNo][$currentDateStr] as $p) {
                        $ts = strtotime($p['timein']);
                        // **MODIFIED:** Robust, case-insensitive check
                        $status = trim(strtolower($p['statues']));
                        if ($status === 'check in' && (!$checkIn || $ts < $checkIn)) $checkIn = $ts;
                        if ($status === 'check out' && (!$checkOut || $ts > $checkOut)) $checkOut = $ts;
                    }
                    
                    // ========================================================================================
                    // **REWRITTEN LOGIC BLOCK** for Present, Missed Punch, and Absent statuses.
                    // ========================================================================================
                    if ($checkIn) {
                        $summary['present_days']++; $record['status'] = 'Present'; $record['check_in'] = date('H:i', $checkIn);
                        $shiftStart = strtotime("$currentDateStr " . $shift['start_time']) + ($late_grace_period_minutes * 60);
                        if ($checkIn > $shiftStart) { $record['status'] = 'Late'; $summary['late_arrivals']++; }
                        
                        if ($checkOut) { 
                            $record['check_out'] = date('H:i', $checkOut); $diff = $checkOut - $checkIn; $h=floor($diff/3600); $m=floor(($diff%3600)/60); 
                            $totalMinutes+=($h*60)+$m; $record['working_hours'] = "{$h}h {$m}m"; 
                            
                            $shiftEndDateTimeStr = $currentDateStr . ' ' . $shift['end_time'];
                            $shiftEndTimestamp = strtotime($shiftEndDateTimeStr) - ($early_out_grace_period_minutes * 60);
                            if (strtotime($shift['end_time']) < strtotime($shift['start_time'])) { $shiftEndTimestamp = strtotime($shiftEndDateTimeStr . ' +1 day') - ($early_out_grace_period_minutes * 60); }
                            if ($checkOut < $shiftEndTimestamp) {
                                $record['status'] = ($record['status'] === 'Late') ? 'Late & Early Out' : 'Early Out';
                                if ($record['status'] !== 'Late & Early Out') $summary['early_outs']++;
                            }
                        }
                    } elseif ($checkOut) {
                        $record['status'] = 'Missed Punch';
                        $summary['missed_punches']++;
                        $record['check_out'] = date('H:i', $checkOut);
                        $record['notes'] = "No Check-In found";
                    } else { 
                        $record['status'] = 'Absent'; $summary['absent_days']++; 
                    }
                } else { 
                    $record['status'] = 'Absent'; $summary['absent_days']++; 
                }
            } else {
                $record['status'] = $isWeekend ? 'Weekend' : 'Day Off';
                $summary['scheduled_off']++;
                 if (isset($rosters[$empid][$d]) && $rosters[$empid][$d]['shift_code'] === 'OFF'){
                    $record['notes'] = "Scheduled Day Off";
                }
            }
            
            $details[] = $record;
        }
        
        $summary['total_hours'] = floor($totalMinutes / 60) . 'h ' . ($totalMinutes % 60) . 'm';
        
        $processed_data[$empid] = [
            'fullName' => $employee['fullName'],
            'payrollNo' => $employee['PayrollNo'],
            'department' => $employee['DepartmentName'],
            'summary' => $summary,
            'details' => $details
        ];
    }
    return $processed_data;
}
?>