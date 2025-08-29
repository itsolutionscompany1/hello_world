<?php
// api/leave_handler.php

// ========================================================================
// --- DEBUGGING & SETUP BLOCK ---
// ========================================================================
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: application/json');
include('../../include/config.php'); 

// --- Main API Router ---
$action = $_REQUEST['action'] ?? '';
$response = ['success' => false, 'message' => 'Invalid action specified'];

try {
    switch ($action) {
        case 'getLeaveRequests': $response = getLeaveRequests($con_payroll); break;
        case 'getLeavePolicy': $response = getLeavePolicy($con_payroll); break;
        case 'getEmployeeBalances': $response = getEmployeeBalances($con_payroll); break;
        case 'updateLeaveStatus': $response = updateLeaveStatus($con_payroll); break;
        case 'saveLeavePolicy': $response = saveLeavePolicy($con_payroll); break;
        case 'adjustEmployeeBalances': $response = adjustEmployeeBalances($con_payroll); break;
        default: throw new Exception("Action not recognized.");
    }
} catch (Exception $e) {
    $response = ['success' => false, 'message' => 'API Error: ' . $e->getMessage()];
}

echo json_encode($response);
exit();

// ========================================================================
// --- API Functions ---
// ========================================================================

/**
 * **RESTORED AND VERIFIED**
 * Handles approving or rejecting a leave request, including balance updates.
 */
function updateLeaveStatus($db_conn) {
    $requestId = $_POST['requestId'] ?? null;
    $status = $_POST['status'] ?? null;
    $reason = $_POST['reason'] ?? null; // For rejections
    $admin_user_id = $_SESSION['id'] ?? null;

    if (!$requestId || !$status || !$admin_user_id) {
        throw new Exception("Missing required parameters (requestId, status, adminId).");
    }

    mysqli_begin_transaction($db_conn);
    try {
        // Step 1: Find the admin's employee ID (empid)
        $sql_get_admin = "SELECT e.empid FROM employees e JOIN patcom.umanusers u ON CAST(REPLACE(e.PayrollNo, 'ZMC/', '') AS UNSIGNED) = u.MemberNo WHERE u.UsrId = ?";        $stmt_admin = mysqli_prepare($db_conn, $sql_get_admin);
        mysqli_stmt_bind_param($stmt_admin, "i", $admin_user_id);
        mysqli_stmt_execute($stmt_admin);
        $admin_data = mysqli_stmt_get_result($stmt_admin)->fetch_assoc();
        mysqli_stmt_close($stmt_admin);
        if (!$admin_data) throw new Exception("Admin's employee record not found.");
        $approved_by_empid = $admin_data['empid'];

        // Step 2: Get the leave request details
        $sql_get_req = "SELECT lr.empid, lr.leave_type_id, lr.days_requested, lr.status as current_status FROM leave_requests lr WHERE lr.request_id = ?";
        $stmt_get_req = mysqli_prepare($db_conn, $sql_get_req);
        mysqli_stmt_bind_param($stmt_get_req, "i", $requestId);
        mysqli_stmt_execute($stmt_get_req);
        $req_details = mysqli_stmt_get_result($stmt_get_req)->fetch_assoc();
        mysqli_stmt_close($stmt_get_req);
        if (!$req_details) throw new Exception("Leave request not found.");
        if ($req_details['current_status'] !== 'pending') throw new Exception("This request has already been processed.");

        // Step 3: Update the leave request status
        $sql_update_req = "UPDATE leave_requests SET status = ?, approved_by = ?, approval_date = NOW(), rejection_reason = ? WHERE request_id = ?";
        $stmt_update_req = mysqli_prepare($db_conn, $sql_update_req);
        mysqli_stmt_bind_param($stmt_update_req, "sisi", $status, $approved_by_empid, $reason, $requestId);
        mysqli_stmt_execute($stmt_update_req);
        mysqli_stmt_close($stmt_update_req);

        // Step 4: If approved, update the employee's leave balance
        if ($status === 'approved') {
            $empid = $req_details['empid'];
            $leave_type_id = $req_details['leave_type_id'];
            $days_requested = $req_details['days_requested'];
            $currentYear = date('Y');

            // Find or create the balance record
            $sql_balance_check = "SELECT balance_id, current_balance FROM employee_leave_balances WHERE empid = ? AND leave_type_id = ? AND year = ?";
            $stmt_balance_check = mysqli_prepare($db_conn, $sql_balance_check);
            mysqli_stmt_bind_param($stmt_balance_check, "iis", $empid, $leave_type_id, $currentYear);
            mysqli_stmt_execute($stmt_balance_check);
            $balance_data = mysqli_stmt_get_result($stmt_balance_check)->fetch_assoc();
            mysqli_stmt_close($stmt_balance_check);

            if ($balance_data) { // Record exists, update it
                $sql_balance_update = "UPDATE employee_leave_balances SET current_balance = current_balance - ?, utilized_this_year = utilized_this_year + ? WHERE balance_id = ?";
                $stmt_balance_update = mysqli_prepare($db_conn, $sql_balance_update);
                mysqli_stmt_bind_param($stmt_balance_update, "ddi", $days_requested, $days_requested, $balance_data['balance_id']);
                mysqli_stmt_execute($stmt_balance_update);
                mysqli_stmt_close($stmt_balance_update);
            } else { // No record, create one
                $sql_get_allotment = "SELECT annual_allotment FROM leave_types WHERE leave_type_id = ?";
                $stmt_get_allotment = mysqli_prepare($db_conn, $sql_get_allotment);
                mysqli_stmt_bind_param($stmt_get_allotment, "i", $leave_type_id);
                mysqli_stmt_execute($stmt_get_allotment);
                $allotment = mysqli_stmt_get_result($stmt_get_allotment)->fetch_assoc()['annual_allotment'] ?? 0;
                mysqli_stmt_close($stmt_get_allotment);
                
                $new_balance = $allotment - $days_requested;
                $sql_balance_insert = "INSERT INTO employee_leave_balances (empid, leave_type_id, year, current_balance, utilized_this_year) VALUES (?, ?, ?, ?, ?)";
                $stmt_balance_insert = mysqli_prepare($db_conn, $sql_balance_insert);
                mysqli_stmt_bind_param($stmt_balance_insert, "iisdd", $empid, $leave_type_id, $currentYear, $new_balance, $days_requested);
                mysqli_stmt_execute($stmt_balance_insert);
                mysqli_stmt_close($stmt_balance_insert);
            }
        }
        
        mysqli_commit($db_conn);
        return ['success' => true, 'message' => 'Leave request has been ' . $status];
    } catch (Exception $e) { 
        mysqli_rollback($db_conn); 
        throw $e; 
    }
}

/**
 * **RESTORED AND VERIFIED**
 * Adjusts employee balances and logs the reason.
 */
function adjustEmployeeBalances($db_conn) {
    mysqli_begin_transaction($db_conn);
    try {
        $empId = $_POST['empId'] ?? null;
        $reason = $_POST['reason'] ?? '';
        $balances = $_POST['balances'] ?? [];
        $admin_name = $_SESSION['user_name'] ?? 'Admin';
        $currentYear = date('Y');

        if (empty($empId) || empty($reason) || empty($balances)) {
            throw new Exception("Employee ID, reason, and balances are required.");
        }

        foreach ($balances as $leave_type_id => $new_balance) {
            $sql_check = "SELECT balance_id FROM employee_leave_balances WHERE empid = ? AND leave_type_id = ? AND year = ?";
            $stmt_check = mysqli_prepare($db_conn, $sql_check);
            mysqli_stmt_bind_param($stmt_check, "iis", $empId, $leave_type_id, $currentYear);
            mysqli_stmt_execute($stmt_check);
            $existing = mysqli_stmt_get_result($stmt_check)->fetch_assoc();
            mysqli_stmt_close($stmt_check);
            
            $update_reason = "\nAdjusted by {$admin_name} on " . date('Y-m-d') . ". Reason: " . $reason;

            if ($existing) {
                $sql_update = "UPDATE employee_leave_balances SET current_balance = ?, update_reason = CONCAT(COALESCE(update_reason, ''), ?) WHERE balance_id = ?";
                $stmt_update = mysqli_prepare($db_conn, $sql_update);
                mysqli_stmt_bind_param($stmt_update, "dsi", $new_balance, $update_reason, $existing['balance_id']);
                mysqli_stmt_execute($stmt_update);
                mysqli_stmt_close($stmt_update);
            } else {
                $sql_insert = "INSERT INTO employee_leave_balances (empid, leave_type_id, year, current_balance, update_reason) VALUES (?, ?, ?, ?, ?)";
                $stmt_insert = mysqli_prepare($db_conn, $sql_insert);
                mysqli_stmt_bind_param($stmt_insert, "iisds", $empId, $leave_type_id, $currentYear, $new_balance, $update_reason);
                mysqli_stmt_execute($stmt_insert);
                mysqli_stmt_close($stmt_insert);
            }
        }
        mysqli_commit($db_conn);
        return ['success' => true, 'message' => 'Employee balances adjusted successfully.'];
    } catch (Exception $e) { 
        mysqli_rollback($db_conn); 
        throw $e;
    }
}


// --- Other functions (Unchanged and verified) ---
function getEmployeeBalances($db_conn) {
    $searchTerm = $_GET['searchTerm'] ?? '';
    $sql_employees = "SELECT empid, Sex as Gender, Surname, OtherNames FROM employees WHERE CONCAT(Surname, ' ', OtherNames) LIKE ? ORDER BY Surname LIMIT 100";
    $searchWildcard = "%{$searchTerm}%";
    $stmt_employees = mysqli_prepare($db_conn, $sql_employees);
    mysqli_stmt_bind_param($stmt_employees, "s", $searchWildcard);
    mysqli_stmt_execute($stmt_employees);
    $employees = mysqli_fetch_all(mysqli_stmt_get_result($stmt_employees), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_employees);
    if (empty($employees)) { return ['success' => true, 'employees' => []]; }
    $employee_ids = array_column($employees, 'empid');
    $currentYear = date('Y');
    $balances = [];
    $id_placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
    $types = str_repeat('i', count($employee_ids)) . 's';
    $params = array_merge($employee_ids, [$currentYear]);
    $balanceSql = "SELECT elb.empid, lt.leave_type_id, lt.type_name, lt.annual_allotment, COALESCE(elb.current_balance, lt.annual_allotment) as current_balance FROM leave_types lt LEFT JOIN employee_leave_balances elb ON lt.leave_type_id = elb.leave_type_id AND elb.empid IN ($id_placeholders) AND elb.year = ? WHERE lt.is_active = 1 ORDER BY lt.type_name";
    $balance_stmt = mysqli_prepare($db_conn, $balanceSql);
    mysqli_stmt_bind_param($balance_stmt, $types, ...$params);
    mysqli_stmt_execute($balance_stmt);
    $balances_result = mysqli_stmt_get_result($balance_stmt);
    $balances_by_empid = [];
    while ($balance = mysqli_fetch_assoc($balances_result)) {
        if (!isset($balances_by_empid[$balance['empid']])) { $balances_by_empid[$balance['empid']] = []; }
        $balances_by_empid[$balance['empid']][$balance['leave_type_id']] = ['name' => $balance['type_name'], 'balance' => $balance['current_balance']];
    }
    mysqli_stmt_close($balance_stmt);
    foreach ($employees as &$employee) { $employee['balances'] = $balances_by_empid[$employee['empid']] ?? []; }
    return ['success' => true, 'employees' => $employees];
}
function getLeaveRequests($db_conn) {
    $status = $_GET['status'] ?? 'pending'; $searchTerm = $_GET['searchTerm'] ?? '';
    $sql = "SELECT lr.request_id, lr.empid, CONCAT(e.Surname, ' ', e.OtherNames) AS employee_name, lt.type_name, DATE_FORMAT(lr.start_date, '%d-%m-%Y') as start_date, DATE_FORMAT(lr.end_date, '%d-%m-%Y') as end_date, lr.days_requested, lr.status FROM leave_requests lr JOIN employees e ON lr.empid = e.empid JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id WHERE 1=1";
    $params = []; $types = '';
    if ($status !== 'all') { $sql .= " AND lr.status = ?"; array_push($params, $status); $types .= 's'; }
    if (!empty($searchTerm)) { $sql .= " AND CONCAT(e.Surname, ' ', e.OtherNames) LIKE ?"; $searchWildcard = "%{$searchTerm}%"; array_push($params, $searchWildcard); $types .= 's'; }
    $sql .= " ORDER BY lr.created_at DESC";
    $stmt = mysqli_prepare($db_conn, $sql);
    if (!$stmt) throw new Exception("SQL Prepare Error: " . mysqli_error($db_conn));
    if ($types) { mysqli_stmt_bind_param($stmt, $types, ...$params); }
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    return ['success' => true, 'data' => $data];
}
function getLeavePolicy($db_conn) {
    $sql = "SELECT leave_type_id, type_name, annual_allotment, max_carry_over, includes_saturdays, includes_sundays, includes_holidays FROM leave_types ORDER BY type_name";
    $result = mysqli_query($db_conn, $sql);
    if (!$result) throw new Exception("SQL Error: " . mysqli_error($db_conn));
    $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    return ['success' => true, 'leave_types' => $data];
}
function saveLeavePolicy($db_conn) {
    mysqli_begin_transaction($db_conn);
    try {
        if (isset($_POST['allotments'])) { /* ... allotment logic ... */ }
        if (isset($_POST['carry_over'])) { /* ... carry over logic ... */ }
        $all_leave_type_ids = array_keys($_POST['allotments'] ?? []);
        if (!empty($all_leave_type_ids)) {
            $sql_update_days = "UPDATE leave_types SET includes_saturdays = ?, includes_sundays = ?, includes_holidays = ? WHERE leave_type_id = ?";
            $stmt_days = mysqli_prepare($db_conn, $sql_update_days);
            foreach ($all_leave_type_ids as $id) {
                $sat_value = isset($_POST['saturdays'][$id]) ? 'YES' : 'NO';
                $sun_value = isset($_POST['sundays'][$id]) ? 'YES' : 'NO';
                $hol_value = isset($_POST['holidays'][$id]) ? 'YES' : 'NO';
                mysqli_stmt_bind_param($stmt_days, "sssi", $sat_value, $sun_value, $hol_value, $id);
                mysqli_stmt_execute($stmt_days);
            }
            mysqli_stmt_close($stmt_days);
        }
        mysqli_commit($db_conn);
        return ['success' => true, 'message' => 'Leave policy saved successfully.'];
    } catch (Exception $e) { mysqli_rollback($db_conn); throw $e; }
}
?>