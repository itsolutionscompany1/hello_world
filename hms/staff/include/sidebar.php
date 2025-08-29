

<?php
// =================================================================
// DYNAMIC SIDEBAR - PHP LOGIC (No changes needed here)
// =================================================================

$user_permissions = [];

// This logic now correctly uses BOTH database connections.
if (isset($_SESSION['id']) && isset($con_patcom) && isset($con_payroll)) {
    // Super Admin fallback
    if ($_SESSION['id'] == 1) {
        $user_permissions = [
            'dashboard.php', 'leave-request.php', 'attendance.php',
            'leave-management.php',
            'attendance-reports.php', 'shift_definitions.php', 'shift_roster.php', 'holidays.php', 'manage_attendance.php',
            'assign-roles.php', 'create-roles.php'
        ];
    } else {
        // Get role_id from PatCom DB
        $role_id = null;
        $stmt_role = mysqli_prepare($con_patcom, "SELECT role_id FROM umanusers WHERE UsrId = ?");
        if ($stmt_role) {
            mysqli_stmt_bind_param($stmt_role, "i", $_SESSION['id']);
            mysqli_stmt_execute($stmt_role);
            $result_role = mysqli_stmt_get_result($stmt_role);
            if ($user = mysqli_fetch_assoc($result_role)) { $role_id = $user['role_id']; }
            mysqli_stmt_close($stmt_role);
        } else { error_log("Sidebar Role Query Failed: " . mysqli_error($con_patcom)); }

        // Get permissions from Payroll DB
        if ($role_id) {
            $stmt_perms = mysqli_prepare($con_payroll, "SELECT permission_key FROM tblrole_permissions WHERE role_id = ?");
            if ($stmt_perms) {
                mysqli_stmt_bind_param($stmt_perms, "i", $role_id);
                mysqli_stmt_execute($stmt_perms);
                $result_perms = mysqli_stmt_get_result($stmt_perms);
                while ($row = mysqli_fetch_assoc($result_perms)) { $user_permissions[] = $row['permission_key']; }
                mysqli_stmt_close($stmt_perms);
            } else { error_log("Sidebar Permissions Query Failed: " . mysqli_error($con_payroll)); }
        }
    }
}

// Helper function to check if user has access to a group of pages
function user_can_access_group($pages, $permissions) {
    foreach ($pages as $page) {
        if (in_array($page, $permissions)) { return true; }
    }
    return false;
}
?>

<!-- start: SIDEBAR -->
<div class="sidebar app-aside" id="sidebar">
    <div class="sidebar-container perfect-scrollbar">
        <nav>
            <div class="navbar-title"><span>Main Navigation</span></div>
            <ul class="main-navigation-menu">

                <!-- ===================================================================== -->
                <!-- SECTION 1: PERSONAL STAFF TOOLS                                       -->
                <!-- Common tasks for every user. No dropdowns for quick access.         -->
                <!-- ===================================================================== -->
                <?php if (in_array('dashboard.php', $user_permissions)): ?>
                <li class="active"><a href="dashboard.php"><div class="item-content"><div class="item-media"><i class="ti-home"></i></div><div class="item-inner"><span class="title"> Dashboard </span></div></div></a></li>
                <?php endif; ?>

                <?php if (in_array('leave-request.php', $user_permissions)): ?>
                <li><a href="leave-request.php"><div class="item-content"><div class="item-media"><i class="ti-write"></i></div><div class="item-inner"><span class="title"> My Leave </span></div></div></a></li>
                <?php endif; ?>

                <?php if (in_array('attendance.php', $user_permissions)): ?>
                <li><a href="attendance.php"><div class="item-content"><div class="item-media"><i class="ti-check-box"></i></div><div class="item-inner"><span class="title"> My Attendance </span></div></div></a></li>
                <?php endif; ?>


                <?php
                // --- Define permission groups ---
                $leave_mgmt_pages = ['leave-management.php'];
                $attendance_mgmt_pages = ['attendance-reports.php', 'shift_definitions.php', 'shift_roster.php', 'holidays.php', 'manage_attendance.php'];
                $user_mgmt_pages = ['assign-roles.php', 'create-roles.php'];

                // Check if the user has access to ANY management tools to show the header
                if (user_can_access_group($leave_mgmt_pages, $user_permissions) || user_can_access_group($attendance_mgmt_pages, $user_permissions)):
                ?>
                <!-- ===================================================================== -->
                <!-- SECTION 2: MANAGEMENT TOOLS                                           -->
                <!-- For managers and HR. Grouped under a clear section header.          -->
                <!-- ===================================================================== -->
                <!-- <li class="menu-section">
                    <span>Management</span>
                </li> -->

                <?php // --- Leave Management Dropdown --- ?>
                <?php if (user_can_access_group($leave_mgmt_pages, $user_permissions)): ?>
                <li>
                    <a href="javascript:void(0)"><div class="item-content"><div class="item-media"><i class="ti-email"></i></div><div class="item-inner"><span class="title"> Leave Administration </span><i class="icon-arrow"></i></div></div></a>
                    <ul class="sub-menu">
                        <?php if (in_array('leave-management.php', $user_permissions)): ?><li><a href="leave-management.php"><span>Manage Leave Requests</span></a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>

                <?php // --- Attendance & Rostering Dropdown --- ?>
                <?php if (user_can_access_group($attendance_mgmt_pages, $user_permissions)): ?>
                <li>
                    <a href="javascript:void(0)"><div class="item-content"><div class="item-media"><i class="ti-calendar"></i></div><div class="item-inner"><span class="title"> Attendance & Rostering </span><i class="icon-arrow"></i></div></div></a>
                    <ul class="sub-menu">
                        <?php if (in_array('manage_attendance.php', $user_permissions)): ?><li><a href="manage_attendance.php"><span>Adjust Attendance</span></a></li><?php endif; ?>
                        <?php if (in_array('shift_roster.php', $user_permissions)): ?><li><a href="shift_roster.php"><span>Assign Staff Roster</span></a></li><?php endif; ?>
                        <?php if (in_array('shift_definitions.php', $user_permissions)): ?><li><a href="shift_definitions.php"><span>Manage Shift Types</span></a></li><?php endif; ?>
                        <?php if (in_array('holidays.php', $user_permissions)): ?><li><a href="holidays.php"><span>Manage Holidays</span></a></li><?php endif; ?>
                        <?php if (in_array('attendance-reports.php', $user_permissions)): ?><li><a href="attendance-reports.php"><span>View Reports</span></a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; ?>
                <?php endif; // End of Management section check ?>


                <?php // Check if the user has access to ANY admin tools to show the header ?>
                <?php if (user_can_access_group($user_mgmt_pages, $user_permissions)): ?>
                <!-- ===================================================================== -->
                <!-- SECTION 3: SYSTEM ADMINISTRATION                                      -->
                <!-- For high-level admins. The final, most privileged section.          -->
                <!-- ===================================================================== -->
                <!-- <li class="menu-section">
                    <span>Administration</span>
                </li> -->

                <?php // --- User & Role Management Dropdown --- ?>
                <li>
                    <a href="javascript:void(0)"><div class="item-content"><div class="item-media"><i class="ti-user"></i></div><div class="item-inner"><span class="title"> User Management </span><i class="icon-arrow"></i></div></div></a>
                    <ul class="sub-menu">
                        <?php if (in_array('create-roles.php', $user_permissions)): ?><li><a href="create-roles.php"><span>Manage Roles & Permissions</span></a></li><?php endif; ?>
                        <?php if (in_array('assign-roles.php', $user_permissions)): ?><li><a href="assign-roles.php"><span>Assign User Roles</span></a></li><?php endif; ?>
                    </ul>
                </li>
                <?php endif; // End of Administration section check ?>

            </ul>
        </nav>
    </div>
</div>
<!-- end: SIDEBAR -->