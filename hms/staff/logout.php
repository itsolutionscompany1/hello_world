<?php
session_start();
include("../include/config.php");

// Get the user ID from the session before we destroy it
$user_id = $_SESSION['id'] ?? 0;

if ($user_id > 0) {
    // The most important step: Delete the session from the database
    $sql = "DELETE FROM user_sessions WHERE user_id = ?";
    $stmt = mysqli_prepare($con_patcom, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
}

// Destroy the local PHP session
session_unset();
session_destroy();

// Redirect to the login page
header("Location: ../../index.php");
exit();
?>