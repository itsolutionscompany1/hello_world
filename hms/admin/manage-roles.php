<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// 1. ========= SETUP AND SECURITY CHECKS =========
if (strlen($_SESSION['id']) == 0) { header('location:logout.php'); exit(); }

// 2. ========= HANDLE FORM SUBMISSIONS (CREATE, UPDATE) =========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_role') {
        $role_name = trim($_POST['role_name']);
        $role_description = trim($_POST['role_description']);
        if (!empty($role_name)) {
            $stmt = mysqli_prepare($con, "INSERT INTO tbluser_roles (role_name, role_description) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $role_name, $role_description);
            mysqli_stmt_execute($stmt);
            $_SESSION['success'] = "Role '{$role_name}' created successfully.";
        } else {
            $_SESSION['error'] = "Role name cannot be empty.";
        }
    }
    
    if ($action === 'update_role') {
        $role_id = intval($_POST['role_id']);
        $role_name = trim($_POST['role_name']);
        $role_description = trim($_POST['role_description']);
        if ($role_id > 0 && !empty($role_name)) {
            mysqli_begin_transaction($con);
            try {
                $stmt_role = mysqli_prepare($con, "UPDATE tbluser_roles SET role_name = ?, role_description = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_role, "ssi", $role_name, $role_description, $role_id);
                mysqli_stmt_execute($stmt_role);

                $stmt_clear = mysqli_prepare($con, "DELETE FROM tblrole_permissions WHERE role_id = ?");
                mysqli_stmt_bind_param($stmt_clear, "i", $role_id);
                mysqli_stmt_execute($stmt_clear);

                if (!empty($_POST['permissions'])) {
                    $stmt_perms = mysqli_prepare($con, "INSERT INTO tblrole_permissions (role_id, permission_key) VALUES (?, ?)");
                    foreach ($_POST['permissions'] as $permission_key) {
                        mysqli_stmt_bind_param($stmt_perms, "is", $role_id, $permission_key);
                        mysqli_stmt_execute($stmt_perms);
                    }
                }
                mysqli_commit($con);
                $_SESSION['success'] = "Role '{$role_name}' updated successfully.";
            } catch (Exception $e) {
                mysqli_rollback($con);
                $_SESSION['error'] = "Error updating role: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Invalid data for role update.";
        }
    }
    
    header('Location: manage-roles.php');
    exit();
}

// --- HANDLE DELETE ACTION ---
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $role_id = intval($_GET['role_id']);
    if ($role_id > 1) { // Safety: Do not allow deleting the Admin role (ID 1)
        $stmt_check = mysqli_prepare($con, "SELECT COUNT(*) as user_count FROM doctors WHERE role_id = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $role_id);
        mysqli_stmt_execute($stmt_check);
        $user_count = mysqli_stmt_get_result($stmt_check)->fetch_assoc()['user_count'];
        if ($user_count > 0) {
            $_SESSION['error'] = "Cannot delete this role because {$user_count} user(s) are assigned to it.";
        } else {
            mysqli_query($con, "DELETE FROM tbluser_roles WHERE id = $role_id");
            mysqli_query($con, "DELETE FROM tblrole_permissions WHERE role_id = $role_id");
            $_SESSION['success'] = "Role deleted successfully.";
        }
    } else {
        $_SESSION['error'] = "The Administrator role cannot be deleted.";
    }
    header('Location: manage-roles.php');
    exit();
}

// 3. ========= FETCH DATA FOR THE PAGE =========
$roles = mysqli_fetch_all(mysqli_query($con, "SELECT * FROM tbluser_roles ORDER BY role_name"), MYSQLI_ASSOC);
$permissions_by_role = [];
$perm_res = mysqli_query($con, "SELECT * FROM tblrole_permissions");
while($row = mysqli_fetch_assoc($perm_res)) { $permissions_by_role[$row['role_id']][] = $row['permission_key']; }
$menu_permissions = [
    'Dashboard' => ['dashboard.php'],
    'Procurements' => ['prepare-requisition.php', 'manage-requisitions.php', 'lpo-analysis.php'],
    'Stock' => ['stock-report.php', 'stock-take.php', 'stock-take-history.php','stock-adjustment.php'],
    'Cashier' => ['manage-patient.php', 'add-patient.php', 'cashier-pos.php', 'refund-management.php', 'cashier-collection-report.php'],
    'Reports' => ['sales-report.php'],
    'System Management' => ['manage_hospital.php', 'manage-branches.php', 'manage-stores.php', 'add-products.php', 'manage-products.php', 'manage-ledger-accounts.php', 'chart-of-accounts.php', 'add-user.php', 'manage-roles.php']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Manage Roles & Permissions</title>
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
                        <div class="row"><div class="col-sm-8"><h1 class="mainTitle">Admin | Manage Roles & Permissions</h1></div></div>
                    </section>
                    <div class="container-fluid container-fullw bg-white">
                        <?php if(isset($_SESSION['error'])): ?><div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div><?php endif; ?>
                        <?php if(isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div><?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title" id="form-title">Add New Role</h5></div>
                                    <div class="panel-body">
                                        <form role="form" id="roleForm" method="post">
                                            <input type="hidden" name="action" id="form_action" value="create_role">
                                            <input type="hidden" name="role_id" id="form_role_id" value="0">
                                            <div class="row"><div class="col-md-6"><div class="form-group"><label>Role Name *</label><input type="text" name="role_name" id="form_role_name" class="form-control" required></div></div><div class="col-md-6"><div class="form-group"><label>Description</label><input type="text" name="role_description" id="form_role_description" class="form-control"></div></div></div>
                                            <hr>
                                            <h5 class="over-title">Set Permissions for this Role</h5>
                                            <div class="row">
                                                <?php foreach($menu_permissions as $group => $pages): ?>
                                                    <div class="col-md-4">
                                                        <fieldset><legend><?php echo $group; ?></legend>
                                                            <div class="checkbox clip-check check-primary"><input type="checkbox" class="check-all" id="check-all-<?php echo str_replace(' ', '', $group); ?>"><label for="check-all-<?php echo str_replace(' ', '', $group); ?>"><strong>Check All</strong></label></div>
                                                            <?php foreach($pages as $page): ?>
                                                                <div class="checkbox clip-check check-primary"><input type="checkbox" name="permissions[]" class="permission-check-<?php echo str_replace(' ', '', $group); ?>" id="<?php echo $page; ?>" value="<?php echo $page; ?>"><label for="<?php echo $page; ?>"><?php echo ucwords(str_replace(['.php', '-'], ['', ' '], $page)); ?></label></div>
                                                            <?php endforeach; ?>
                                                        </fieldset>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <hr>
                                            <button type="submit" id="form_submit_btn" class="btn btn-primary pull-right">Save Role</button>
                                            <button type="button" id="reset_form_btn" class="btn btn-default pull-right" style="margin-right: 10px; display: none;">Cancel Edit</button>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title">Existing Roles</h5></div>
                                    <div class="panel-body">
                                        <table class="table table-hover">
                                            <thead><tr><th>Role Name</th><th>Description</th><th class="text-center">Actions</th></tr></thead>
                                            <tbody>
                                                <?php foreach($roles as $role): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($role['role_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($role['role_description']); ?></td>
                                                    <td class="text-center">
                                                        <button class="btn btn-xs btn-primary edit-role-btn" data-role-id="<?php echo $role['id']; ?>" data-role-name="<?php echo htmlspecialchars($role['role_name']); ?>" data-role-description="<?php echo htmlspecialchars($role['role_description']); ?>"><i class="fa fa-pencil"></i> Edit</button>
                                                        <?php if ($role['id'] > 1): // Prevent deleting the Admin role ?>
                                                        <a href="manage-roles.php?action=delete&role_id=<?php echo $role['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure? This cannot be undone.');"><i class="fa fa-trash-o"></i> Delete</a>
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
            </div>
        </div>
        <?php include('include/footer.php');?>
    </div>
    <!-- JAVASCRIPT SECTION -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script> if (!$.cookie('skin_color')) { $.cookie('skin_color', 'theme-1', { path: '/' }); } </script>
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function($) {
            Main.init();
            const allPermissionsByRole = <?php echo json_encode($permissions_by_role); ?>;

            function resetForm() {
                $('#roleForm')[0].reset();
                $('#form-title').text('Add New Role');
                $('#form_submit_btn').text('Save Role');
                $('#form_action').val('create_role');
                $('#form_role_id').val('0');
                $('#reset_form_btn').hide();
                $('input[name="permissions[]"], .check-all').prop('checked', false);
            }

            $('.edit-role-btn').on('click', function(e) {
                e.preventDefault();
                var btn = $(this);
                $('#form_role_id').val(btn.data('role-id'));
                $('#form_role_name').val(btn.data('role-name'));
                $('#form_role_description').val(btn.data('role-description'));
                $('#form-title').text('Edit Role: ' + btn.data('role-name'));
                $('#form_submit_btn').text('Update Role');
                $('#form_action').val('update_role');
                $('#reset_form_btn').show();
                $('input[name="permissions[]"], .check-all').prop('checked', false);
                var roleId = btn.data('role-id').toString();
                if (allPermissionsByRole[roleId]) {
                    allPermissionsByRole[roleId].forEach(function(key) {
                        $('#' + key.replace(/\./g, '\\.')).prop('checked', true);
                    });
                }
                $('html, body').animate({ scrollTop: 0 }, 'fast');
            });

            $('#reset_form_btn').on('click', resetForm);
            
            // "Check All" functionality
            $('.check-all').on('change', function() {
                var groupClass = '.permission-check-' + $(this).attr('id').replace('check-all-', '');
                $(groupClass).prop('checked', $(this).prop('checked'));
            });
        });
    </script>
</body>
</html>