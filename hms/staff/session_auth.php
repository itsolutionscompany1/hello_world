<?php
// This file should be included at the VERY TOP of every protected staff page.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the database connection
include(__DIR__ . "/../include/config.php");

// Check if user is logged in via standard session variables
if (!isset($_SESSION['dlogin']) || !isset($_SESSION['session_token'])) {
    header("location: ../../index.php"); // Redirect to the main login page
    exit();
}

// Now, validate the token against the database
$session_token = $_SESSION['session_token'];
$user_id = $_SESSION['id'];

$sql = "SELECT id FROM user_sessions WHERE user_id = ? AND session_token = ?";
$stmt = mysqli_prepare($con_patcom, $sql);
mysqli_stmt_bind_param($stmt, "is", $user_id, $session_token);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$session_valid = mysqli_fetch_assoc($result);

if (!$session_valid) {
    // If the token is NOT in the database, the session is invalid.
    // This happens when they've logged out from another device.
    
    // Destroy the local session
    session_unset();
    session_destroy();

    // Redirect to login with a message
    header("location: ../../index.php?error=session_expired");
    exit();
}

// Optional: Update the last_seen timestamp to keep the session "alive"
$update_sql = "UPDATE user_sessions SET last_seen = CURRENT_TIMESTAMP WHERE session_token = ?";
$update_stmt = mysqli_prepare($con_patcom, $update_sql);
mysqli_stmt_bind_param($update_stmt, "s", $session_token);
mysqli_stmt_execute($update_stmt);

?>