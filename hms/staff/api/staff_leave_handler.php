<?php
// hms/staff/api/staff_leave_handler.php

/**
 * Main API for handling staff leave-related actions.
 * VERSION 5: Restored the original getEmpIdFromUserId function to fix the user linking error.
 *            Corrected getLeaveTypes to send policy data for frontend calculations.
 */

// --- CONFIGURATION ---
const DEBUG_MODE = false;

// --- INITIALIZATION ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
header('Content-Type: ' . (DEBUG_MODE ? 'text/plain' : 'application/json'));
include('../../include/config.php');

// --- Security & Authentication Check ---
if (!isset($_SESSION['id'])) {
    $errorMessage = 'Authentication failed: No user ID found in session.';
    if (DEBUG_MODE) { die("FATAL ERROR: " . $errorMessage); } 
    else { http_response_code(401); echo json_encode(['success' => false, 'message' => $errorMessage]); exit(); }
}
$userId = $_SESSION['id'];
$debugLog = [];

// ========================================================================
// --- SCRIPT EXECUTION LOGIC ---
// ========================================================================
if (DEBUG_MODE) {
    echo "--- STARTING DEBUG for getEmpIdFromUserId ---\n\n";
    try {
        $empid = getEmpIdFromUserId($con_patcom, $con_payroll, $userId, $debugLog);
        echo implode("\n", $debugLog);
        echo "\n\n--- FINAL RESULT ---\n";
        if ($empid) { echo "SUCCESS: The final empid found is: " . $empid . "\n"; } 
        else { echo "FAILURE: The function did not return an empid.\n"; }
    } catch (Exception $e) {
        echo "\n--- FATAL ERROR CAUGHT ---\n";
        echo "An exception occurred: " . $e->getMessage() . "\n";
    }
    echo "\n--- DEBUG END ---";
    exit();
} else {
    $action = $_REQUEST['action'] ?? '';
    $response = ['success' => false, 'message' => 'Invalid action'];
    try {
        // This function call is now using the full, correct implementation below
        $empid = getEmpIdFromUserId($con_patcom, $con_payroll, $userId, $debugLog);
        if (!$empid) { 
            throw new Exception("Your user account is not linked to an employee record. Please contact HR."); 
        }
        
        switch ($action) {
            case 'getLeaveTypes': $response = getLeaveTypes($con_payroll, $empid); break;
            case 'getLeaveBalances': $response = getLeaveBalances($con_payroll, $empid); break;
            case 'getMyRequests': $response = getMyRequests($con_payroll, $empid); break;
            case 'submitLeaveRequest': $response = submitLeaveRequest($con_payroll, $empid); break;
            default: throw new Exception("Action not recognized.");
        }
    } catch (Exception $e) {
        http_response_code(500);
        $response = ['success' => false, 'message' => 'API Error: ' . $e->getMessage()];
    }
    echo json_encode($response);
    exit();
}

// ========================================================================
// --- CORE & API Functions ---
// ========================================================================

/**
 * **RESTORED:** This is the original, correct function to link a user to an employee.
 */
function getEmpIdFromUserId($db_conn_patcom, $db_conn_payroll, $user_id, &$debugLog) {
    $debugLog[] = "Received User ID from session: " . $user_id . "\n";
    $debugLog[] = "STEP 1: Looking for MemberNo in 'PatCom' database...";
    $sql_get_memberno = "SELECT MemberNo FROM umanusers WHERE UsrId = ? LIMIT 1";
    $stmt_get_memberno = mysqli_prepare($db_conn_patcom, $sql_get_memberno);
    if (!$stmt_get_memberno) throw new Exception('SQL Prepare Error (getMemberNo): ' . mysqli_error($db_conn_patcom));
    mysqli_stmt_bind_param($stmt_get_memberno, "i", $user_id);
    mysqli_stmt_execute($stmt_get_memberno);
    $user = mysqli_stmt_get_result($stmt_get_memberno)->fetch_assoc();
    mysqli_stmt_close($stmt_get_memberno);
    if (!$user || empty($user['MemberNo'])) { $debugLog[] = "  - FAILURE: Could not find MemberNo for UsrId " . $user_id . "."; return null; }
    $memberNo = $user['MemberNo'];
    $debugLog[] = "  - SUCCESS: Found MemberNo: '" . $memberNo . "'\n";
    $debugLog[] = "STEP 2: Using MemberNo to find matching PayrollNo in 'payroll' database...";
    $sql_get_empid = "SELECT empid FROM employees WHERE PayrollNo = ? LIMIT 1";
    $stmt_get_empid = mysqli_prepare($db_conn_payroll, $sql_get_empid);
    if (!$stmt_get_empid) throw new Exception('SQL Prepare Error (getEmpId): ' . mysqli_error($db_conn_payroll));
    mysqli_stmt_bind_param($stmt_get_empid, "s", $memberNo);
    mysqli_stmt_execute($stmt_get_empid);
    $employee = mysqli_stmt_get_result($stmt_get_empid)->fetch_assoc();
    mysqli_stmt_close($stmt_get_empid);
    if($employee && !empty($employee['empid'])) { $empid = $employee['empid']; $debugLog[] = "  - SUCCESS: Found empid: '" . $empid . "'"; return $empid; }
    $debugLog[] = "  - FAILURE: No employee found with matching PayrollNo for '" . $memberNo . "'.";
    return null;
}

/**
 * **CORRECTED:** Fetches leave types, now including the day-counting policy rules.
 */
function getLeaveTypes($db_conn, $empid) {
    $sql_gender = "SELECT Sex FROM employees WHERE empid = ? LIMIT 1";
    $stmt_gender = mysqli_prepare($db_conn, $sql_gender);
    mysqli_stmt_bind_param($stmt_gender, "i", $empid);
    mysqli_stmt_execute($stmt_gender);
    $gender = mysqli_stmt_get_result($stmt_gender)->fetch_assoc()['Sex'] ?? 'MALE';
    mysqli_stmt_close($stmt_gender);
    
    // This query now includes the policy columns needed by the frontend
    $sql = "SELECT 
                leave_type_id, type_name, annual_allotment, max_carry_over, description,
                includes_saturdays, includes_sundays, includes_holidays
            FROM leave_types 
            WHERE is_active = 1 
            ORDER BY type_name";
            
    $result = mysqli_query($db_conn, $sql);
    $all_leave_types = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
    $filtered_types = [];
    foreach($all_leave_types as $type) {
        if (stripos($type['type_name'], 'Maternity') !== false && strtoupper($gender) !== 'FEMALE') continue;
        if (stripos($type['type_name'], 'Paternity') !== false && strtoupper($gender) !== 'MALE') continue;
        $filtered_types[] = $type;
    }
    return ['success' => true, 'leaveTypes' => $filtered_types];
}

/**
 * Fetches the current leave balances for the employee.
 */
function getLeaveBalances($db_conn, $empid) {
    $currentYear = date('Y');
    $sql = "SELECT lt.type_name, lt.annual_allotment, COALESCE(elb.current_balance, lt.annual_allotment) as current_balance, COALESCE(elb.utilized_this_year, 0) as utilized_this_year FROM leave_types lt LEFT JOIN employee_leave_balances elb ON lt.leave_type_id = elb.leave_type_id AND elb.empid = ? AND elb.year = ? WHERE lt.is_active = 1 ORDER BY lt.type_name";
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $empid, $currentYear);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return ['success' => true, 'balances' => $data];
}

/**
 * Fetches recent leave requests for the employee.
 */
function getMyRequests($db_conn, $empid) {
    $sql = "SELECT 
                lr.request_id AS leave_request_id, lt.type_name, 
                DATE_FORMAT(lr.start_date, '%d-%m-%Y') as start_date, 
                DATE_FORMAT(lr.end_date, '%d-%m-%Y') as end_date, 
                lr.days_requested, lr.status,
                DATE_FORMAT(lr.created_at, '%d-%m-%Y %H:%i') as created_at,
                DATE_FORMAT(lr.updated_at, '%d-%m-%Y %H:%i') as updated_at
            FROM leave_requests lr 
            JOIN leave_types lt ON lr.leave_type_id = lt.leave_type_id 
            WHERE lr.empid = ? 
            ORDER BY lr.created_at DESC 
            LIMIT 10";
    $stmt = mysqli_prepare($db_conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empid);
    mysqli_stmt_execute($stmt);
    $data = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return ['success' => true, 'data' => $data];
}

/**
 * Submits a new leave request.
 */
function submitLeaveRequest($db_conn, $empid) {
    mysqli_begin_transaction($db_conn);
    try {
        $leaveTypeId = $_POST['leaveTypeId'] ?? null;
        $startDate = $_POST['startDate'] ?? null;
        $endDate = $_POST['endDate'] ?? null;
        $daysRequested = isset($_POST['daysRequested']) ? floatval($_POST['daysRequested']) : 0;
        $reason = $_POST['reason'] ?? null;
        if (empty($leaveTypeId) || empty($startDate) || empty($endDate) || $daysRequested <= 0 || empty($reason)) { throw new Exception("All fields are required and must be valid."); }
        $balances = getLeaveBalances($db_conn, $empid)['balances'];
        $currentBalance = 0; $leaveTypeName = '';
        $policy_sql = "SELECT type_name FROM leave_types WHERE leave_type_id = ?";
        $stmt_policy = mysqli_prepare($db_conn, $policy_sql);
        mysqli_stmt_bind_param($stmt_policy, "i", $leaveTypeId);
        mysqli_stmt_execute($stmt_policy);
        $policy = mysqli_stmt_get_result($stmt_policy)->fetch_assoc();
        mysqli_stmt_close($stmt_policy);
        if ($policy) { $leaveTypeName = $policy['type_name']; } else { throw new Exception("Invalid leave type selected."); }
        foreach($balances as $b) { if($b['type_name'] == $leaveTypeName) { $currentBalance = floatval($b['current_balance']); break; } }
        if ($daysRequested > $currentBalance) { throw new Exception("Insufficient leave balance. You requested {$daysRequested} days but only have {$currentBalance} days available for {$leaveTypeName}."); }
        $sql = "INSERT INTO leave_requests (empid, leave_type_id, start_date, end_date, days_requested, reason, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
        $stmt = mysqli_prepare($db_conn, $sql);
        mysqli_stmt_bind_param($stmt, "iissds", $empid, $leaveTypeId, $startDate, $endDate, $daysRequested, $reason);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        mysqli_commit($db_conn);
        return ['success' => true, 'message' => 'Leave request submitted successfully.'];
    } catch (Exception $e) {
        mysqli_rollback($db_conn);
        throw $e; 
    }
}
?>