<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// 1. ========= SECURITY AND INPUT VALIDATION =========
if (strlen($_SESSION['id']) == 0) { header('location:logout.php'); exit(); }

$user_id_to_edit = isset($_GET['uid']) ? intval($_GET['uid']) : 0;
if ($user_id_to_edit <= 0) {
    $_SESSION['error'] = "Invalid user ID provided.";
    header('Location: manage-users.php');
    exit();
}

// 2. ========= HANDLE FORM SUBMISSION FOR UPDATE =========
if (isset($_POST['submit'])) {
    $role_id = intval($_POST['role']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $error = false;

    // Begin transaction for data integrity
    mysqli_begin_transaction($con);
    try {
        // Update main user details (name, email, role)
        $stmt = mysqli_prepare($con, "UPDATE doctors SET doctorName = ?, docEmail = ?, role_id = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssii", $fullname, $email, $role_id, $user_id_to_edit);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception("Could not update user details. The email may already be in use.");
        }

        // Update password ONLY if a new one is provided
        if (!empty($_POST['npass'])) {
            if ($_POST['npass'] !== $_POST['cfpass']) {
                throw new Exception("Passwords do not match.");
            }
            $password = md5($_POST['npass']);
            $stmt_pass = mysqli_prepare($con, "UPDATE doctors SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_pass, "si", $password, $user_id_to_edit);
            mysqli_stmt_execute($stmt_pass);
        }

        mysqli_commit($con);
        $_SESSION['success'] = "User details updated successfully.";

    } catch (Exception $e) {
        mysqli_rollback($con);
        $_SESSION['error'] = "Update failed: " . $e->getMessage();
        $error = true;
    }

    if ($error) {
        // If there was an error, redirect back to the same edit page to show the message
        header('Location: edit-user.php?uid=' . $user_id_to_edit);
    } else {
        // On success, redirect to the user list
        header('Location: manage-users.php');
    }
    exit();
}

// 3. ========= FETCH DATA FOR THE FORM =========
$stmt_user = mysqli_prepare($con, "SELECT id, doctorName, docEmail, role_id FROM doctors WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $user_id_to_edit);
mysqli_stmt_execute($stmt_user);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));

if (!$user_data) {
    $_SESSION['error'] = "User not found.";
    header('Location: manage-users.php');
    exit();
}

$roles = mysqli_fetch_all(mysqli_query($con, "SELECT id, role_name FROM tbluser_roles ORDER BY role_name"), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Edit User</title>
    <!-- Your standard CSS Includes -->
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php');?>
        <div class="app-content">
            <?php include('include/header.php');?>
            <div class="main-content">
                <div class="wrap-content container" id="container">
                    <section id="page-title"><div class="row"><div class="col-sm-8"><h1 class="mainTitle">Admin | Edit User</h1></div></section>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-8 col-md-offset-2">
                                <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
                                <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title">Edit: <?php echo htmlspecialchars($user_data['doctorName']); ?></h5></div>
                                    <div class="panel-body">
                                        <form role="form" name="edituser" method="post" onsubmit="return valid();">
                                            <div class="form-group"><label>Full Name *</label><input type="text" name="fullname" class="form-control" value="<?php echo htmlspecialchars($user_data['doctorName']); ?>" required></div>
                                            <div class="form-group"><label>Email *</label><input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($user_data['docEmail']); ?>" required></div>
                                            <div class="form-group">
                                                <label>Role *</label>
                                                <select name="role" class="form-control" required>
                                                    <option value="">-- Select a Role --</option>
                                                    <?php foreach($roles as $role): ?>
                                                        <option value="<?php echo $role['id']; ?>" <?php if($user_data['role_id'] == $role['id']) echo 'selected'; ?>>
                                                            <?php echo htmlspecialchars($role['role_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <hr><p class="text-muted">Only fill in password fields if you want to change the password.</p>
                                            <div class="form-group"><label>New Password</label><input type="password" name="npass" class="form-control" placeholder="Enter New Password"></div>
                                            <div class="form-group"><label>Confirm New Password</label><input type="password" name="cfpass" class="form-control" placeholder="Confirm New Password"></div>
                                            <a href="manage-users.php" class="btn btn-default">Cancel</a>
                                            <button type="submit" name="submit" class="btn btn-primary pull-right">Update User</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php include('include/footer.php');?>
    </div>
    <!-- JS Includes -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        function valid() {
            // This function is called on form submission
            const newPassword = document.edituser.npass.value;
            const confirmPassword = document.edituser.cfpass.value;

            // Only validate if a new password is being entered
            if (newPassword.length > 0 && newPassword !== confirmPassword) {
                alert("The 'New Password' and 'Confirm New Password' fields do not match!");
                document.edituser.cfpass.focus();
                return false; // Prevent form submission
            }
            return true; // Allow form submission
        }
        jQuery(document).ready(function() {
            Main.init();
        });
    </script>
</body>
</html>