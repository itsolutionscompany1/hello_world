<?php
// Establish the database connections first.
require_once('session_auth.php'); 

error_reporting(E_ALL);
ini_set('display_errors', 1);

// =================================================================
// 1. ========= DATABASE CONNECTION AND SECURITY CHECKS =========
// =================================================================

// This page needs the Payroll DB for roles and the PatCom DB for checking user assignments.
if (!isset($con_payroll) || !$con_payroll instanceof mysqli) {
    die("FATAL ERROR: The Payroll database connection (`$con_payroll`) is not available. Check `include/config.php`.");
}
if (!isset($con_patcom) || !$con_patcom instanceof mysqli) {
    die("FATAL ERROR: The PatCom database connection (`$con_patcom`) is not available. Check `include/config.php`.");
}

// Security Check: Ensure a user is logged in.
if (strlen($_SESSION['id'] ?? '') == 0) { 
    header('location: ../../logout.php'); 
    exit(); 
}

// Set the default database for this page to the Payroll DB where roles are stored.
$db = $con_payroll;

// =================================================================
// 2. ========= HANDLE FORM SUBMISSIONS (CREATE, UPDATE) =========
// =================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_role') {
        // (Logic for creating a role - unchanged)
        $role_name = trim($_POST['role_name']);
        $role_description = trim($_POST['role_description']);
        if (!empty($role_name)) {
            $stmt = mysqli_prepare($db, "INSERT INTO tbluser_roles (role_name, role_description) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, "ss", $role_name, $role_description);
            mysqli_stmt_execute($stmt);
            $_SESSION['msg'] = "Role '{$role_name}' created successfully.";
        } else {
            $_SESSION['errmsg'] = "Role name cannot be empty.";
        }
    }
    
    if ($action === 'update_role') {
        // (Logic for updating a role - unchanged)
        $role_id = intval($_POST['role_id']);
        $role_name = trim($_POST['role_name']);
        $role_description = trim($_POST['role_description']);
        if ($role_id > 0 && !empty($role_name)) {
            mysqli_begin_transaction($db);
            try {
                $stmt_role = mysqli_prepare($db, "UPDATE tbluser_roles SET role_name = ?, role_description = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_role, "ssi", $role_name, $role_description, $role_id);
                mysqli_stmt_execute($stmt_role);

                $stmt_clear = mysqli_prepare($db, "DELETE FROM tblrole_permissions WHERE role_id = ?");
                mysqli_stmt_bind_param($stmt_clear, "i", $role_id);
                mysqli_stmt_execute($stmt_clear);

                if (!empty($_POST['permissions'])) {
                    $stmt_perms = mysqli_prepare($db, "INSERT INTO tblrole_permissions (role_id, permission_key) VALUES (?, ?)");
                    foreach ($_POST['permissions'] as $permission_key) {
                        mysqli_stmt_bind_param($stmt_perms, "is", $role_id, $permission_key);
                        mysqli_stmt_execute($stmt_perms);
                    }
                }
                mysqli_commit($db);
                $_SESSION['msg'] = "Role '{$role_name}' updated successfully.";
            } catch (Exception $e) {
                mysqli_rollback($db);
                $_SESSION['errmsg'] = "Error updating role: " . $e->getMessage();
            }
        } else {
            $_SESSION['errmsg'] = "Invalid data for role update.";
        }
    }
    
    header('Location: create-roles.php');
    exit();
}

// =================================================================
// 3. ========= HANDLE DELETE ACTION ===============================
// =================================================================
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $role_id = intval($_GET['id']);
    if ($role_id > 1) { 
        // IMPROVED: Check for assigned users in the `umanusers` table (PatCom DB)
        $stmt_check = mysqli_prepare($con_patcom, "SELECT COUNT(*) as user_count FROM umanusers WHERE role_id = ?");
        mysqli_stmt_bind_param($stmt_check, "i", $role_id);
        mysqli_stmt_execute($stmt_check);
        $user_count = mysqli_stmt_get_result($stmt_check)->fetch_assoc()['user_count'];
        
        if ($user_count > 0) {
            $_SESSION['errmsg'] = "Cannot delete role: {$user_count} user(s) are assigned to it.";
        } else {
            // Safe to delete from the Payroll DB
            mysqli_query($db, "DELETE FROM tbluser_roles WHERE id = $role_id");
            mysqli_query($db, "DELETE FROM tblrole_permissions WHERE role_id = $role_id");
            $_SESSION['msg'] = "Role deleted successfully.";
        }
    } else {
        $_SESSION['errmsg'] = "The Administrator role cannot be deleted.";
    }
    header('Location: create-roles.php');
    exit();
}

// =================================================================
// 4. ========= FETCH DATA FOR THE PAGE DISPLAY ====================
// =================================================================
$roles_query_result = mysqli_query($db, "SELECT * FROM tbluser_roles ORDER BY role_name");
if ($roles_query_result === false) {
    die("FATAL ERROR: Failed to fetch roles from the 'payroll' database. Details: " . mysqli_error($db));
}
$roles = mysqli_fetch_all($roles_query_result, MYSQLI_ASSOC);

$permissions_query_result = mysqli_query($db, "SELECT * FROM tblrole_permissions");
if ($permissions_query_result === false) {
    die("FATAL ERROR: Failed to fetch permissions from the 'payroll' database. Details: " . mysqli_error($db));
}
$permissions_by_role = [];
while($row = mysqli_fetch_assoc($permissions_query_result)) {
    $permissions_by_role[$row['role_id']][] = $row['permission_key']; 
}

// ** THE FIX IS HERE **
// This array now perfectly matches the modules in your sidebar.
$menu_permissions = [
    'Staff Tools' => [
        'leave-request.php', // Corresponds to "My Leave Request"
        'attendance.php'     // Corresponds to "My Attendance"
    ],
    'Leave Management' => [
        'leave-management.php' // Corresponds to "Leave Dashboard"
    ],
    'Attendance' => [
        'attendance-reports.php', // Corresponds to "Attendance Reports"
        'shift_definitions.php',  // Corresponds to "Roster Definitions"
        'shift_roster.php',       // Corresponds to "Roster Mapping"
        'holidays.php',           // Corresponds to "Holiday Mapping"
        'manage_attendance.php'   // Corresponds to "Manage Attendance"
    ],
    'User Roles' => [
        'assign-roles.php', // Corresponds to "Assign Roles"
        'create-roles.php'  // Corresponds to "Create Roles"
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Manage Roles & Permissions</title>
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
            <div class="main-content" >
                <div class="wrap-content container" id="container">
                    <!-- PAGE TITLE -->
                    <section id="page-title">
                        <div class="row"><div class="col-sm-8"><h1 class="mainTitle">Admin | Manage Roles & Permissions</h1></div></div>
                    </section>
                    
                    <div class="container-fluid container-fullw bg-white">
                        <!-- MESSAGES -->
                        <?php if(isset($_SESSION['errmsg'])): ?><div class="alert alert-danger"><?php echo $_SESSION['errmsg']; unset($_SESSION['errmsg']); ?></div><?php endif; ?>
                        <?php if(isset($_SESSION['msg'])): ?><div class="alert alert-success"><?php echo $_SESSION['msg']; unset($_SESSION['msg']); ?></div><?php endif; ?>
                        
                        <div class="row">
                            <!-- ROLE CREATION / EDIT FORM -->
                            <div class="col-md-12">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title" id="form-title">Add New Role</h5></div>
                                    <div class="panel-body">
                                        <form role="form" id="roleForm" method="post" action="create-roles.php">
                                            <input type="hidden" name="action" id="form_action" value="create_role">
                                            <input type="hidden" name="role_id" id="form_role_id" value="0">
                                            <div class="row">
                                                <div class="col-md-6"><div class="form-group"><label>Role Name *</label><input type="text" name="role_name" id="form_role_name" class="form-control" required placeholder="e.g., HR Manager"></div></div>
                                                <div class="col-md-6"><div class="form-group"><label>Description</label><input type="text" name="role_description" id="form_role_description" class="form-control" placeholder="Brief role description"></div></div>
                                            </div>
                                            <hr>
                                            <h5 class="over-title">Set Permissions for this Role</h5>
                                            <div class="row">
                                                <?php foreach($menu_permissions as $group => $pages): ?>
                                                    <div class="col-md-4">
                                                        <fieldset class="well"><legend class="text-primary"><?php echo $group; ?></legend>
                                                            <div class="checkbox clip-check check-primary"><input type="checkbox" class="check-all" id="check-all-<?php echo str_replace(' ', '', $group); ?>"><label for="check-all-<?php echo str_replace(' ', '', $group); ?>"><strong>Select All</strong></label></div>
                                                            <?php foreach($pages as $page): $page_display_name = ucwords(str_replace(['.php', '-', '_'], ['', ' ', ' '], $page)); ?>
                                                                <div class="checkbox clip-check check-primary"><input type="checkbox" name="permissions[]" class="permission-check-<?php echo str_replace(' ', '', $group); ?>" id="<?php echo $page; ?>" value="<?php echo $page; ?>"><label for="<?php echo $page; ?>"><?php echo $page_display_name; ?></label></div>
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
                            <!-- EXISTING ROLES TABLE (Unchanged) -->
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
                                                        <?php if ($role['id'] > 1): ?>
                                                        <a href="create-roles.php?action=delete&id=<?php echo $role['id']; ?>" class="btn btn-xs btn-danger" onclick="return confirm('Are you sure? This cannot be undone.');"><i class="fa fa-trash-o"></i> Delete</a>
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
    <!-- JS Includes and script (Unchanged) -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
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
                $('input[type="checkbox"]').prop('checked', false);
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
                $('input[type="checkbox"]').prop('checked', false);
                
                var roleId = btn.data('role-id').toString();
                if (allPermissionsByRole[roleId]) {
                    allPermissionsByRole[roleId].forEach(function(key) {
                        $('#' + key.replace(/\./g, '\\.')).prop('checked', true);
                    });
                }
                $('html, body').animate({ scrollTop: 0 }, 'fast');
            });

            $('#reset_form_btn').on('click', resetForm);

            $('.check-all').on('change', function() {
                var groupClass = '.permission-check-' + $(this).attr('id').replace('check-all-', '');
                $(groupClass).prop('checked', $(this).is(':checked'));
            });
        });
    </script>
</body>
</html>