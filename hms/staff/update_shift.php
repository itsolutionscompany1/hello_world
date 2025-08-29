<?php
session_start();
include('../include/config.php');

// Security: Ensure the user is logged in.
if (strlen($_SESSION['id'] ?? '') == 0) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}

// Security: Only allow POST requests.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

// Get the JSON data sent from the JavaScript.
$json_data = file_get_contents('php://input');
$data = json_decode($json_data, true);

// Validate the incoming data.
$empid = $data['empid'] ?? null;
$shift_id = $data['shift_id'] ?? null;
$roster_date = $data['roster_date'] ?? null;

if (empty($empid) || empty($roster_date)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['status' => 'error', 'message' => 'Employee ID and Roster Date are required.']);
    exit();
}

header('Content-Type: application/json');

// If the shift_id is empty or '0', it signifies that the shift should be removed for that day.
if (empty($shift_id)) {
    $sql = "DELETE FROM shift_roster WHERE user_id = ? AND roster_date = ?";
    $stmt = mysqli_prepare($con_payroll, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $empid, $roster_date);
    
    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Shift removed successfully.']);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'Failed to remove shift.']);
    }
    mysqli_stmt_close($stmt);

} else {
    // If a shift_id is provided, insert a new record or update the existing one.
    // The UNIQUE KEY on `user_id` and `roster_date` handles the "update" part of this query.
    $sql = "INSERT INTO shift_roster (user_id, shift_id, roster_date) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE shift_id = VALUES(shift_id)";
            
    $stmt = mysqli_prepare($con_payroll, $sql);
    mysqli_stmt_bind_param($stmt, 'iis', $empid, $shift_id, $roster_date);

    if (mysqli_stmt_execute($stmt)) {
        echo json_encode(['status' => 'success', 'message' => 'Shift updated successfully.']);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['status' => 'error', 'message' => 'Failed to update shift.']);
    }
    mysqli_stmt_close($stmt);
}

mysqli_close($con_payroll);
?>