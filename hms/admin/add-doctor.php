<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// 1. ========= SETUP AND SECURITY CHECKS =========
if (strlen($_SESSION['id']) == 0) { header('location:logout.php'); exit(); }

// 2. ========= ONE-TIME DATABASE MIGRATIONS =========
// These run safely and only once to prepare your database for the new features.
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `tbluser_roles` (`id` INT AUTO_INCREMENT PRIMARY KEY, `role_name` VARCHAR(100) NOT NULL UNIQUE, `role_description` TEXT NULL)");
mysqli_query($con, "CREATE TABLE IF NOT EXISTS `tblrole_permissions` (`role_id` INT, `permission_key` VARCHAR(100), PRIMARY KEY (`role_id`, `permission_key`))");
// Adds the role_id column to your existing doctors table
mysqli_query($con, "ALTER TABLE `doctors` ADD COLUMN IF NOT EXISTS `role_id` INT NULL AFTER `id`");

// 3. ========= HANDLE FORM SUBMISSION =========
if (isset($_POST['submit'])) {
    $role_id = intval($_POST['role']);
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    
    // Check if passwords match
    if ($_POST['npass'] !== $_POST['cfpass']) {
        $_SESSION['error'] = "Password and Confirm Password do not match.";
    } else {
        $password = md5($_POST['npass']);

        mysqli_begin_transaction($con);
        try {
            $new_user_id = null;

            // Handle new role creation first
            if ($role_id == 0 && !empty($_POST['new_role_name'])) {
                $new_role_name = trim($_POST['new_role_name']);
                $stmt_role = mysqli_prepare($con, "INSERT INTO tbluser_roles (role_name) VALUES (?)");
                mysqli_stmt_bind_param($stmt_role, "s", $new_role_name);
                mysqli_stmt_execute($stmt_role);
                $role_id = mysqli_insert_id($con); // Get the ID of the new role
            }

            if ($role_id <= 0) {
                throw new Exception("A role must be selected or created.");
            }

            // Insert the new user into the 'doctors' table
            $stmt_user = mysqli_prepare($con, "INSERT INTO doctors (role_id, doctorName, docEmail, password) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_user, "isss", $role_id, $fullname, $email, $password);
            if (!mysqli_stmt_execute($stmt_user)) {
                // Check for duplicate email error
                if (mysqli_errno($con) == 1062) {
                    throw new Exception("Could not create user. The email address '{$email}' already exists.");
                }
                throw new Exception("Database error: Could not create user.");
            }
            
            // Clear old permissions for this role before setting new ones
            $stmt_clear_perms = mysqli_prepare($con, "DELETE FROM tblrole_permissions WHERE role_id = ?");
            mysqli_stmt_bind_param($stmt_clear_perms, "i", $role_id);
            mysqli_stmt_execute($stmt_clear_perms);

            // Insert new permissions if any are selected
            if (!empty($_POST['permissions'])) {
                $stmt_perms = mysqli_prepare($con, "INSERT INTO tblrole_permissions (role_id, permission_key) VALUES (?, ?)");
                foreach ($_POST['permissions'] as $permission_key) {
                    mysqli_stmt_bind_param($stmt_perms, "is", $role_id, $permission_key);
                    mysqli_stmt_execute($stmt_perms);
                }
            }

            mysqli_commit($con);
            $_SESSION['success'] = "User and permissions saved successfully.";
            header('Location: manage-users.php'); // It's good practice to create a manage-users.php page
            exit();

        } catch (Exception $e) {
            mysqli_rollback($con);
            $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        }
    }
}

// 4. ========= FETCH DATA FOR THE FORM =========
$roles = mysqli_fetch_all(mysqli_query($con, "SELECT * FROM tbluser_roles ORDER BY role_name"), MYSQLI_ASSOC);
$permissions_by_role = [];
$perm_res = mysqli_query($con, "SELECT * FROM tblrole_permissions");
while($row = mysqli_fetch_assoc($perm_res)) {
    $permissions_by_role[$row['role_id']][] = $row['permission_key'];
}

// Define your application's menu structure as permissions
$menu_permissions = [
    'Dashboard' => ['dashboard.php'],
    'Procurements' => ['prepare-requisition.php', 'manage-requisitions.php', 'lpo-analysis.php'],
    'Stock' => ['stock-report.php', 'stock-take.php', 'stock-take-history.php'],
    'System Management' => ['manage_hospital.php', 'manage-branches.php', 'manage-stores.php', 'add-products.php', 'manage-products.php', 'manage-ledger-accounts.php', 'chart-of-accounts.php', 'add-user.php'],
    'Cashier' => ['manage-patient.php', 'add-patient.php', 'cashier-pos.php', 'refund-management.php', 'cashier-collection-report.php'],
    'Reports' => ['sales-report.php']
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Add User & Roles</title>
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
                        <div class="row"><div class="col-sm-8"><h1 class="mainTitle">Admin | User & Role Management</h1></div></div>
                    </section>
                    <div class="container-fluid container-fullw bg-white">
                        <div class="row">
                            <div class="col-md-12">
                                <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
                                <form role="form" name="adduser" method="post" onsubmit="return valid();">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="panel panel-white">
                                                <div class="panel-heading"><h5 class="panel-title">User Details</h5></div>
                                                <div class="panel-body">
                                                    <div class="form-group"><label>Full Name *</label><input type="text" name="fullname" class="form-control" placeholder="Enter Full Name" required></div>
                                                    <div class="form-group"><label>User Email *</label><input type="email" name="email" class="form-control" placeholder="Enter User Email" required></div>
                                                    <div class="form-group"><label>Password *</label><input type="password" name="npass" class="form-control" placeholder="New Password" required></div>
                                                    <div class="form-group"><label>Confirm Password *</label><input type="password" name="cfpass" class="form-control" placeholder="Confirm Password" required></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="panel panel-white">
                                                <div class="panel-heading"><h5 class="panel-title">Assign Role</h5></div>
                                                <div class="panel-body">
                                                    <div class="form-group">
                                                        <label>Select Role</label>
                                                        <select name="role" id="role_select" class="form-control" required>
                                                            <option value="">-- Select an Existing Role --</option>
                                                            <?php foreach($roles as $role): ?>
                                                                <option value="<?php echo $role['id']; ?>" data-permissions='<?php echo json_encode($permissions_by_role[$role['id']] ?? []); ?>'>
                                                                    <?php echo htmlspecialchars($role['role_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                            <option value="0">-- Create a New Role --</option>
                                                        </select>
                                                    </div>
                                                    <div id="new_role_container" class="form-group" style="display:none;">
                                                        <label>New Role Name *</label>
                                                        <input type="text" name="new_role_name" class="form-control" placeholder="e.g., Cashier, Stock Manager">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <hr>
                                    <div class="panel panel-white">
                                        <div class="panel-heading"><h5 class="panel-title">Set Role Permissions</h5></div>
                                        <div class="panel-body">
                                            <p class="text-muted">Select the pages and features this role can access. Changes will affect all users in this role.</p>
                                            <div class="row">
                                                <?php foreach($menu_permissions as $group => $pages): ?>
                                                    <div class="col-md-4">
                                                        <fieldset>
                                                            <legend><?php echo $group; ?></legend>
                                                            <?php foreach($pages as $page): ?>
                                                                <div class="checkbox clip-check check-primary">
                                                                    <input type="checkbox" name="permissions[]" id="<?php echo $page; ?>" value="<?php echo $page; ?>">
                                                                    <label for="<?php echo $page; ?>"><?php echo ucwords(str_replace(['.php', '-'], ['', ' '], $page)); ?></label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </fieldset>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" name="submit" class="btn btn-primary pull-right">Save User & Permissions</button>
                                </form>
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
    <script>
        function valid() {
            if (document.adduser.npass.value != document.adduser.cfpass.value) {
                alert("Password and Confirm Password Field do not match!");
                document.adduser.cfpass.focus();
                return false;
            }
            return true;
        }

        jQuery(document).ready(function() {
            Main.init();

            $('#role_select').on('change', function() {
                var selectedValue = $(this).val();
                var newRoleInput = $('input[name="new_role_name"]');
                
                $('#new_role_container').toggle(selectedValue === '0');
                newRoleInput.prop('required', selectedValue === '0'); // Make required only if creating new
                
                $('input[name="permissions[]"]').prop('checked', false);

                if (selectedValue !== '0' && selectedValue !== '') {
                    var permissions = $(this).find('option:selected').data('permissions');
                    if (permissions && Array.isArray(permissions)) {
                        permissions.forEach(function(permissionKey) {
                            // Safely select checkbox, escaping . in the id
                            $('#' + permissionKey.replace(/\./g, '\\.')).prop('checked', true);
                        });
                    }
                }
            }).trigger('change');
        });
    </script>
</body>
</html>


