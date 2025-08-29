<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

if (strlen($_SESSION['id']) == 0) { header('location:logout.php'); exit(); }

// Handle Delete Action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['uid'])) {
    $user_id_to_delete = intval($_GET['uid']);
    // Safety checks: Cannot delete self or the super admin (ID 1)
    if ($user_id_to_delete != $_SESSION['id'] && $user_id_to_delete > 1) {
        mysqli_query($con, "DELETE FROM doctors WHERE id = $user_id_to_delete");
        $_SESSION['success'] = "User deleted successfully.";
    } else {
        $_SESSION['error'] = "Action not allowed. You cannot delete this user account.";
    }
    header('Location: manage-users.php');
    exit();
}

// Fetch all users with their role names
$sql = "
    SELECT u.id, u.doctorName, u.docEmail, r.role_name 
    FROM doctors u 
    LEFT JOIN tbluser_roles r ON u.role_id = r.id 
    ORDER BY u.doctorName
";
$users = mysqli_fetch_all(mysqli_query($con, $sql), MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Manage Users</title>
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
            <div class="main-content" >
                <div class="wrap-content container" id="container">
                    <section id="page-title">
                        <div class="row">
                            <div class="col-sm-8"><h1 class="mainTitle">Admin | Manage Users</h1></div>
                            <div class="col-sm-4">
                                <a href="add-user.php" class="btn btn-primary pull-right" style="margin-top: 20px;"><i class="fa fa-plus"></i> Add New User</a>
                            </div>
                        </div>
                    </section>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
                                <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
                                <table class="table table-hover">
                                    <thead><tr><th>Full Name</th><th>Email</th><th>Role</th><th class="text-center">Actions</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['doctorName']); ?></td>
                                            <td><?php echo htmlspecialchars($user['docEmail']); ?></td>
                                            <td><span class="label label-info"><?php echo htmlspecialchars($user['role_name'] ?? 'N/A'); ?></span></td>
                                            <td class="text-center">
                                                <a href="edit-user.php?uid=<?php echo $user['id']; ?>" class="btn btn-xs btn-primary"><i class="fa fa-pencil"></i> Edit</a>
                                                <?php if ($user['id'] > 1 && $user['id'] != $_SESSION['id']): ?>
                                                <a href="manage-users.php?action=delete&uid=<?php echo $user['id']; ?>" 
                                                   class="btn btn-xs btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this user?');">
                                                   <i class="fa fa-trash-o"></i> Delete
                                                </a>
                                                <?php endif; ?>
                                            </td>
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
        <?php include('include/footer.php');?>
    </div>
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script> jQuery(document).ready(function() { Main.init(); }); </script>
</body>
</html>