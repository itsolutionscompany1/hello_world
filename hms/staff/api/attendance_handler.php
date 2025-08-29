<?php
/**
 * API for handling staff attendance data, intelligently integrated with shift rosters and leave requests.
 * VERSION 9: Implements "Missed Punch" logic and robust, case-insensitive status checks.
 */

session_start();
header('Content-Type: application/json');
include('../../include/config.php');

// --- Main Execution Block ---
try {
    if (!isset($con_patcom) || !$con_patcom instanceof mysqli) { throw new Exception("Attendance database connection is not available.", 503); }
    if (!isset($con_payroll) || !$con_payroll instanceof mysqli) { throw new Exception("Payroll database connection is not available.", 503); }
    if (!isset($_SESSION['id'])) { throw new Exception("Authentication required. Please log in.", 401); }
    
    $sessionUserId = $_SESSION['id'];
    $debugLog = []; // Initialize the log array here
    
    $employeeId = getEmpIdFromUserId($con_patcom, $con_payroll, $sessionUserId, $debugLog);
    if (!$employeeId) {
        $reason = end($debugLog);
        throw new Exception("Could not link user to employee record. Reason: " . $reason, 404);
    }
    
    $stmt_user = mysqli_prepare($con_patcom, "SELECT MemberNo FROM umanusers WHERE UsrId = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_user, "i", $sessionUserId);
    mysqli_stmt_execute($stmt_user);
    $user_patcom = mysqli_stmt_get_result($stmt_user)->fetch_assoc();
    mysqli_stmt_close($stmt_user);
    $memberNo = $user_patcom['MemberNo'];

    $action = $_GET['action'] ?? '';
    if ($action === 'getAttendance') {
        $response = getAttendanceData($con_patcom, $con_payroll, $memberNo, $employeeId, $debugLog);
        $response['debug'] = $debugLog;
    } else {
        throw new Exception("Invalid action requested.", 400);
    }

    echo json_encode($response);

} catch (Exception $e) {
    $statusCode = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
    http_response_code($statusCode);
    $errorResponse = ['success' => false, 'message' => $e->getMessage()];
    if (!empty($debugLog)) { $errorResponse['debug'] = $debugLog; }
    echo json_encode($errorResponse);
}


function getEmpIdFromUserId($db_conn_patcom, $db_conn_payroll, $user_id, &$debugLog) {
    $sql_get_memberno = "SELECT MemberNo FROM umanusers WHERE UsrId = ? LIMIT 1";
    $stmt_get_memberno = mysqli_prepare($db_conn_patcom, $sql_get_memberno);
    if (!$stmt_get_memberno) throw new Exception('SQL Prepare Error (getMemberNo)');
    mysqli_stmt_bind_param($stmt_get_memberno, "i", $user_id);
    mysqli_stmt_execute($stmt_get_memberno);
    $user = mysqli_stmt_get_result($stmt_get_memberno)->fetch_assoc();
    mysqli_stmt_close($stmt_get_memberno);
    if (!$user || empty($user['MemberNo'])) { $debugLog[] = "Failure: Could not find MemberNo for UsrId " . $user_id; return null; }
    $memberNo = $user['MemberNo'];
    $debugLog[] = "Success: Found MemberNo: '" . $memberNo . "'";
    $sql_get_empid = "SELECT empid FROM employees WHERE PayrollNo = ? LIMIT 1";
    $stmt_get_empid = mysqli_prepare($db_conn_payroll, $sql_get_empid);
    if (!$stmt_get_empid) throw new Exception('SQL Prepare Error (getEmpId)');
    mysqli_stmt_bind_param($stmt_get_empid, "s", $memberNo);
    mysqli_stmt_execute($stmt_get_empid);
    $employee = mysqli_stmt_get_result($stmt_get_empid)->fetch_assoc();
    mysqli_stmt_close($stmt_get_empid);
    if($employee && !empty($employee['empid'])) { $debugLog[] = "Success: Found empid: '" . $employee['empid'] . "'"; return $employee['empid']; }
    $debugLog[] = "Failure: Could not find employee with matching PayrollNo for '" . $memberNo . "'";
    return null;
}

/**
 * Main function to fetch and process attendance data with detailed logging.
 */
function getAttendanceData($db_patcom, $db_payroll, $memberNo, $employeeId, &$debugLog) {
    $month = intval($_GET['month'] ?? date('n'));
    $year = intval($_GET['year'] ?? date('Y'));
    $firstDay = "$year-$month-01";
    $lastDay = date("Y-m-t", strtotime($firstDay));

    $debugLog[] = "\n--- Starting Attendance Calculation ---";
    $debugLog[] = "Inputs: employeeId='{$employeeId}', memberNo='{$memberNo}', month='{$month}', year='{$year}'";
    $debugLog[] = "Date Range: {$firstDay} to {$lastDay}";

    $late_grace_period_minutes = 5;
    $early_out_grace_period_minutes = 5;

    // 1. Fetch Shift Roster
    $roster = [];
    $sql_roster = "SELECT DAY(sr.roster_date) as day, s.shift_code, s.start_time, s.end_time FROM shift_roster sr JOIN shifts s ON sr.shift_id = s.shift_id WHERE sr.user_id = ? AND MONTH(sr.roster_date) = ? AND YEAR(sr.roster_date) = ?";
    $stmt_roster = mysqli_prepare($db_payroll, $sql_roster);
    mysqli_stmt_bind_param($stmt_roster, 'iii', $employeeId, $month, $year);
    mysqli_stmt_execute($stmt_roster);
    $result_roster = mysqli_stmt_get_result($stmt_roster);
    while ($row = mysqli_fetch_assoc($result_roster)) { $roster[$row['day']] = $row; }
    mysqli_stmt_close($stmt_roster);
    
    $debugLog[] = "\n--- Step 1: Shift Roster Data ---";
    $debugLog[] = "Result: Found " . count($roster) . " roster entries.";

    // 2. Fetch Approved Leave Days
    $leaveDays = getLeaveDays($db_payroll, $employeeId, $firstDay, $lastDay);
    
    $debugLog[] = "\n--- Step 2: Leave Data ---";
    $debugLog[] = "Result: Found " . count($leaveDays) . " leave days.";

    // 3. Get attendance punches from PATCOM DB
    $sql_punches = "SELECT timein, statues FROM attendance WHERE membernumber = ? AND DATE(timein) BETWEEN ? AND ?";
    $stmt_punches = mysqli_prepare($db_patcom, $sql_punches);
    mysqli_stmt_bind_param($stmt_punches, "sss", $memberNo, $firstDay, $lastDay);
    mysqli_stmt_execute($stmt_punches);
    $result_punches = mysqli_stmt_get_result($stmt_punches);
    $dailyPunches = [];
    while ($row = mysqli_fetch_assoc($result_punches)) { $date = date('Y-m-d', strtotime($row['timein'])); $dailyPunches[$date][] = $row; }
    mysqli_stmt_close($stmt_punches);

    $debugLog[] = "\n--- Step 3: Raw Attendance Punch Data ---";
    if (empty($dailyPunches)) {
        $debugLog[] = "Result: No clock-in/out punches found for this member number and period.";
    } else {
        $debugLog[] = "Result: Found punches on " . count($dailyPunches) . " days. Data:\n" . json_encode($dailyPunches, JSON_PRETTY_PRINT);
    }
    $debugLog[] = "\n--- Processing Daily Records... ---";

    // 4. Initialize counters and process every day
    $records = [];
    // **MODIFIED:** Added 'missed_punches' counter
    $summary = ['present_days' => 0, 'absent_days' => 0, 'leave_days' => 0, 'total_hours' => '0h 0m', 'late_arrivals' => 0, 'early_outs' => 0, 'working_days' => 0, 'missed_punches' => 0];
    $totalMinutes = 0;
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $currentDateStr = sprintf('%d-%02d-%02d', $year, $month, $day);
        $record = ['date' => date('d-m-Y (D)', strtotime($currentDateStr)), 'check_in' => null, 'check_out' => null, 'status' => '', 'working_hours' => null, 'notes' => ''];
        $punchedIn = isset($dailyPunches[$currentDateStr]);

        if (isset($leaveDays[$currentDateStr])) {
            $record['status'] = 'On Leave'; 
            $summary['leave_days']++; 
            $record['notes'] = $leaveDays[$currentDateStr];
            
            if ($punchedIn) {
                $checkInTimestamp = null; $checkOutTimestamp = null;
                foreach ($dailyPunches[$currentDateStr] as $punch) {
                    $punchTimestamp = strtotime($punch['timein']);
                    if (trim(strtolower($punch['statues'])) === 'check in' && (!$checkInTimestamp || $punchTimestamp < $checkInTimestamp)) $checkInTimestamp = $punchTimestamp;
                    if (trim(strtolower($punch['statues'])) === 'check out' && (!$checkOutTimestamp || $punchTimestamp > $checkOutTimestamp)) $checkOutTimestamp = $punchTimestamp;
                }
                if ($checkInTimestamp) { $record['check_in'] = date('h:i:s A', $checkInTimestamp); }
                if ($checkOutTimestamp) { $record['check_out'] = date('h:i:s A', $checkOutTimestamp); }
                $record['notes'] .= " (Clocked In)";
            }
        } elseif (isset($roster[$day]) && $roster[$day]['shift_code'] !== 'OFF') {
            $summary['working_days']++; 
            $shift = $roster[$day];
            $record['notes'] = "Shift: " . htmlspecialchars($shift['shift_code']) . " (" . date('H:i', strtotime($shift['start_time'])) . " - " . date('H:i', strtotime($shift['end_time'])) . ")";
            
            if ($punchedIn) {
                $checkInTimestamp = null; $checkOutTimestamp = null;
                foreach ($dailyPunches[$currentDateStr] as $punch) {
                    $punchTimestamp = strtotime($punch['timein']);
                    // **MODIFIED:** Robust, case-insensitive check
                    $status = trim(strtolower($punch['statues']));
                    if ($status === 'check in' && (!$checkInTimestamp || $punchTimestamp < $checkInTimestamp)) $checkInTimestamp = $punchTimestamp;
                    if ($status === 'check out' && (!$checkOutTimestamp || $punchTimestamp > $checkOutTimestamp)) $checkOutTimestamp = $punchTimestamp;
                }
                
                // ========================================================================================
                // **REWRITTEN LOGIC BLOCK** to handle Present, Missed Punch, and Absent statuses correctly.
                // ========================================================================================
                if ($checkInTimestamp) {
                    // CASE 1: Correctly checked in.
                    $summary['present_days']++; $record['status'] = 'Present'; $record['check_in'] = date('h:i:s A', $checkInTimestamp);
                    
                    $shiftStartDateTimeStr = $currentDateStr . ' ' . $shift['start_time'];
                    $shiftStartTimestamp = strtotime($shiftStartDateTimeStr) + ($late_grace_period_minutes * 60);
                    if ($checkInTimestamp > $shiftStartTimestamp) { $record['status'] = 'Late'; $summary['late_arrivals']++; }

                    if ($checkOutTimestamp) {
                        $record['check_out'] = date('h:i:s A', $checkOutTimestamp);
                        $diff = $checkOutTimestamp - $checkInTimestamp; $hours = floor($diff/3600); $mins = floor(($diff%3600)/60);
                        $totalMinutes += ($hours * 60) + $mins; $record['working_hours'] = "{$hours}h {$mins}m";
                        
                        $shiftEndDateTimeStr = $currentDateStr . ' ' . $shift['end_time'];
                        $shiftEndTimestamp = strtotime($shiftEndDateTimeStr) - ($early_out_grace_period_minutes * 60);
                        if (strtotime($shift['end_time']) < strtotime($shift['start_time'])) { $shiftEndTimestamp = strtotime($shiftEndDateTimeStr . ' +1 day') - ($early_out_grace_period_minutes * 60); }
                        if ($checkOutTimestamp < $shiftEndTimestamp) {
                            $record['status'] = ($record['status'] === 'Late') ? 'Late & Early Out' : 'Early Out';
                            $summary['early_outs']++;
                        }
                    } else { $record['notes'] .= " (No Check-Out)"; }
                } elseif ($checkOutTimestamp) {
                    // CASE 2: NEW! Only a checkout was found.
                    $record['status'] = 'Missed Punch';
                    $record['check_out'] = date('h:i:s A', $checkOutTimestamp);
                    $record['notes'] .= " (No Check-In record found)";
                    $summary['missed_punches']++;
                } else {
                    // CASE 3: Scheduled, but no valid punches found (e.g., status is neither 'Check In' nor 'Check Out').
                    $record['status'] = 'Absent'; $summary['absent_days']++; 
                }
            } else {
                // Not punched in at all on a scheduled day.
                $record['status'] = 'Absent'; $summary['absent_days']++;
            }
        } else {
            $dayOfWeek = date('w', strtotime($currentDateStr));
            $record['status'] = 'Day Off';
            if ($dayOfWeek == 0 || $dayOfWeek == 6) { $record['status'] = 'Weekend'; }
            if (isset($roster[$day]) && $roster[$day]['shift_code'] === 'OFF') { $record['notes'] = "Scheduled Day Off"; }
        }
        $records[] = $record;
    }

    if($totalMinutes > 0){ $summary['total_hours'] = floor($totalMinutes / 60) . 'h ' . ($totalMinutes % 60) . 'm'; }
    return ['success' => true, 'data' => ['records' => $records, 'summary' => $summary]];
}

function getLeaveDays($db_payroll, $employeeId, $startDate, $endDate) {
    if ($db_payroll === null) { return []; }
    $leaveDays = [];
    $query = "SELECT lr.start_date, lr.end_date, lt.type_name FROM leave_requests lr JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE lr.empid = ? AND TRIM(LOWER(lr.status)) = 'approved' AND lr.start_date <= ? AND lr.end_date >= ?";
    $stmt = mysqli_prepare($db_payroll, $query);
    if (!$stmt) { error_log("CRITICAL: Failed to prepare leave query: " . mysqli_error($db_payroll)); return []; }
    mysqli_stmt_bind_param($stmt, "iss", $employeeId, $endDate, $startDate);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $current = new DateTime($row['start_date']); $end = new DateTime($row['end_date']);
        while ($current <= $end) { $leaveDays[$current->format('Y-m-d')] = $row['type_name']; $current->modify('+1 day'); }
    }
    mysqli_stmt_close($stmt);
    return $leaveDays;
}
?>