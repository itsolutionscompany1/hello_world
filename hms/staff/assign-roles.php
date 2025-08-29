<?php
// Establish the database connections first.
require_once('session_auth.php'); 

// Start the session and set error reporting.
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =================================================================
// 1. ========= DATABASE CONNECTION AND SECURITY CHECKS =========
// =================================================================

// This page requires BOTH database connections to function.
if (!isset($con_patcom) || !$con_patcom instanceof mysqli) {
    die("FATAL ERROR: The PatCom database connection is not available. Check `include/config.php`.");
}
if (!isset($con_payroll) || !$con_payroll instanceof mysqli) {
    die("FATAL ERROR: The Payroll database connection is not available. Check `include/config.php`.");
}

// Security Check: Ensure a user is logged in (you can add role checks here too).
if (strlen($_SESSION['id'] ?? '') == 0) { 
    header('location: ../../logout.php'); 
    exit(); 
}

// =================================================================
// 2. ========= HANDLE FORM SUBMISSION (ASSIGN ROLE) ==============
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_role'])) {
    $userId = intval($_POST['user_id']);
    $roleId = intval($_POST['role_id']); // 0 can be used to "unassign"

    if ($userId > 0) {
        // The UPDATE query runs on the PatCom database to update the user's record.
        $stmt = mysqli_prepare($con_patcom, "UPDATE umanusers SET role_id = ? WHERE UsrId = ?");
        // A role_id of 0 will be stored as NULL in the database.
        $roleIdToSave = ($roleId == 0) ? NULL : $roleId;
        mysqli_stmt_bind_param($stmt, "ii", $roleIdToSave, $userId);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['msg'] = "User role has been updated successfully.";
        } else {
            $_SESSION['errmsg'] = "Error updating role: " . mysqli_error($con_patcom);
        }
    } else {
        $_SESSION['errmsg'] = "Invalid user ID provided.";
    }
    
    // Redirect to the same page to prevent form resubmission on refresh.
    header('Location: assign-roles.php');
    exit();
}

// =================================================================
// 3. ========= FETCH DATA FOR PAGE DISPLAY ========================
// =================================================================

// --- Fetch all available roles from the Payroll database ---
$roles_query_result = mysqli_query($con_payroll, "SELECT id, role_name FROM tbluser_roles ORDER BY role_name");
if (!$roles_query_result) {
    die("FATAL ERROR: Could not fetch roles from Payroll DB. " . mysqli_error($con_payroll));
}
// Create two arrays: one for the dropdown, one for easy name lookup later.
$roles_for_dropdown = mysqli_fetch_all($roles_query_result, MYSQLI_ASSOC);
$role_names_by_id = array_column($roles_for_dropdown, 'role_name', 'id');

// --- Fetch all users from the PatCom database ---
$users_query_result = mysqli_query($con_patcom, "SELECT UsrId, UserFullNames, role_id FROM umanusers ORDER BY UserFullNames");
if (!$users_query_result) {
    die("FATAL ERROR: Could not fetch users from PatCom DB. " . mysqli_error($con_patcom));
}
$users = mysqli_fetch_all($users_query_result, MYSQLI_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Assign User Roles</title>
    <!-- Standard CSS Includes -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="../vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="../vendor/themify-icons/themify-icons.min.css">
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="stylesheet" href="../assets/css/plugins.css">
    <link rel="stylesheet" href="../assets/css/themes/theme-1.css" id="skin_color" />
</head>
<body>
    <div id="app">		
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <!-- PAGE TITLE -->
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8"><h1 class="mainTitle">Admin | Assign User Roles</h1></div>
                        </div>
                    </section>
                    
                    <div class="container-fluid container-fullw bg-white">
                        <!-- MESSAGES -->
                        <?php if(isset($_SESSION['errmsg'])): ?><div class="alert alert-danger"><?php echo $_SESSION['errmsg']; unset($_SESSION['errmsg']); ?></div><?php endif; ?>
                        <?php if(isset($_SESSION['msg'])): ?><div class="alert alert-success"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div><?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title">Manage User Role Assignments</h5></div>
                                    <div class="panel-body">
                                        <div class="table-responsive">
                                            <table class="table table-hover table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Full Name</th>
                                                        <th>Current Role</th>
                                                        <th style="width: 300px;">Assign New Role</th>
                                                        <th style="width: 100px;">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach($users as $user): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($user['UserFullNames']); ?></td>
                                                        <td>
                                                            <?php 
                                                                // Use the lookup array to display the current role name
                                                                if (!empty($user['role_id']) && isset($role_names_by_id[$user['role_id']])) {
                                                                    echo '<span class="label label-info">' . htmlspecialchars($role_names_by_id[$user['role_id']]) . '</span>';
                                                                } else {
                                                                    echo '<em>Not Assigned</em>';
                                                                }
                                                            ?>
                                                        </td>
                                                        <!-- A separate form for each user makes submission simple -->
                                                        <form method="post" action="assign-roles.php">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['UsrId']; ?>">
                                                            <td>
                                                                <select name="role_id" class="form-control">
                                                                    <option value="0">-- Unassign Role --</option>
                                                                    <?php foreach($roles_for_dropdown as $role): ?>
                                                                        <option value="<?php echo $role['id']; ?>" <?php if ($user['role_id'] == $role['id']) echo 'selected'; ?>>
                                                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                            </td>
                                                            <td>
                                                                <button type="submit" name="assign_role" class="btn btn-primary btn-sm">Save</button>
                                                            </td>
                                                        </form>
                                                    </tr>
                                                    <?php endforeach; ?>
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
        <?php include('include/footer.php');?>
        <?php include('include/setting.php');?>
    </div>
    <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="vendor/select2/select2.min.js"></script>
        <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
        });
    </script>
</body>
</html>