<?php
/*
 * =================================================================
 * ZION HMS - DUAL DATABASE CONFIGURATION
 * =================================================================
 * This file connects to TWO separate databases as required by the system.
 */

// --- CONNECTION DETAILS (Same for both) ---
define('DB_SERVER', '127.0.0.1');
define('DB_USER', 'root');
define('DB_PASS', 'aSTERISK@12');
define('DB_PORT', 3307);

// --- 1. PatCom DATABASE CONNECTION (for Login & Users) ---
define('DB_NAME_PATCOM', 'PatCom');
$con_patcom = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME_PATCOM, DB_PORT);

if (mysqli_connect_errno()) {
    die("Fatal Error: Failed to connect to the PatCom database: " . mysqli_connect_error());
}

// --- 2. Payroll DATABASE CONNECTION (for HR & Leave Data) ---
define('DB_NAME_PAYROLL', 'payroll'); // Assuming the database is named 'payroll'
$con_payroll = mysqli_connect(DB_SERVER, DB_USER, DB_PASS, DB_NAME_PAYROLL, DB_PORT);

if (mysqli_connect_errno()) {
    die("Fatal Error: Failed to connect to the Payroll database: " . mysqli_connect_error());
}

?>