<?php
session_start();
error_reporting(E_ALL);

include('../include/config.php');

// ========================================================================
// SECURITY CHECK & SETUP
// ========================================================================

// 1. Check if the user is logged in
if (strlen($_SESSION['id'] ?? '') == 0) {
    header('location:../logout.php');
    exit(); // Always exit after a header redirect
}

// 2. Verify that the mysqli connection object exists
if (!isset($con_payroll) || !$con_payroll instanceof mysqli) {
    die("FATAL ERROR: The database connection for PAYROLL (\$con_payroll) is not a valid mysqli object in config.php.");
}

// 3. Auto-create the 'shifts' table if it doesn't exist
$createTableQuery = "
CREATE TABLE IF NOT EXISTS `shifts` (
  `shift_id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_code` varchar(20) NOT NULL,
  `shift_name` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`shift_id`),
  UNIQUE KEY `shift_code` (`shift_code`)
)";
// Error checking for the create table query
if (!mysqli_query($con_payroll, $createTableQuery)) {
    die("Database setup error: Could not create the 'shifts' table: " . mysqli_error($con_payroll));
}

// ========================================================================
// INITIALIZE VARIABLES
// ========================================================================
$message = '';
$edit_mode = false;
$shift_to_edit = null;

// ========================================================================
// HANDLE FORM SUBMISSIONS (POST REQUESTS)
// ========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- ADD SHIFT ---
    if (isset($_POST['add_shift'])) {
        $shift_code = strtoupper(trim($_POST['shift_code']));
        $shift_name = trim($_POST['shift_name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        $stmt = mysqli_prepare($con_payroll, "INSERT INTO shifts (shift_code, shift_name, start_time, end_time) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $shift_code, $shift_name, $start_time, $end_time);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">Shift added successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error: Could not add shift. The Shift Code may already exist.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
        mysqli_stmt_close($stmt);
    }
    
    // --- UPDATE SHIFT ---
    if (isset($_POST['update_shift'])) {
        $shift_id = intval($_POST['shift_id']);
        $shift_code = strtoupper(trim($_POST['shift_code']));
        $shift_name = trim($_POST['shift_name']);
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        $stmt = mysqli_prepare($con_payroll, "UPDATE shifts SET shift_code = ?, shift_name = ?, start_time = ?, end_time = ? WHERE shift_id = ?");
        mysqli_stmt_bind_param($stmt, "ssssi", $shift_code, $shift_name, $start_time, $end_time, $shift_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-success alert-dismissible fade show" role="alert">Shift updated successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error: Could not update shift.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
        mysqli_stmt_close($stmt);
    }

    // --- DELETE SHIFT ---
    if (isset($_POST['delete_shift'])) {
        $shift_id = intval($_POST['shift_id']);
        $stmt = mysqli_prepare($con_payroll, "DELETE FROM shifts WHERE shift_id = ?");
        mysqli_stmt_bind_param($stmt, "i", $shift_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $message = '<div class="alert alert-info alert-dismissible fade show" role="alert">Shift deleted successfully.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">Error: Could not delete shift.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
        mysqli_stmt_close($stmt);
    }
}

// ========================================================================
// HANDLE PAGE LOAD DATA (GET REQUESTS)
// ========================================================================

// --- Check for Edit Request ---
if (isset($_GET['edit_id'])) {
    $edit_mode = true;
    $edit_id = intval($_GET['edit_id']);
    $stmt = mysqli_prepare($con_payroll, "SELECT * FROM shifts WHERE shift_id = ?");
    mysqli_stmt_bind_param($stmt, "i", $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $shift_to_edit = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Management | HMS</title>

    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <!-- Using a CDN for Font Awesome for reliability -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
</head>
<body>
    <div id="app">
        <?php include('include/sidebar.php'); ?>
        <div class="app-content">
            <?php include('include/header.php'); ?>
            <div class="main-content">
                <div class="container-fluid">
                    <!-- Page Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="mainTitle">Shift Management</h1>
                            <span class="mainDescription">Add, edit, and manage work shifts</span>
                        </div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#helpModal">
                            <i class="fas fa-question-circle me-1"></i> Help
                        </button>
                    </div>

                    <!-- Display Dynamic Messages -->
                    <?= $message ?>

                    <div class="row">
                        <!-- Left Column: Existing Shifts Table -->
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="card-title mb-0"><i class="far fa-clock me-2"></i>Defined Shifts</h5>
                                    <?php 
                                        $count_result = mysqli_query($con_payroll, "SELECT COUNT(*) as total FROM shifts");
                                        $shift_count = mysqli_fetch_assoc($count_result)['total'];
                                    ?>
                                    <span class="badge bg-primary rounded-pill"><?= $shift_count ?> Total Shifts</span>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>Code</th>
                                                    <th>Name</th>
                                                    <th>Start Time</th>
                                                    <th>End Time</th>
                                                    <th>Duration</th>
                                                    <th class="text-end">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $result = mysqli_query($con_payroll, "SELECT * FROM shifts ORDER BY start_time, shift_code");
                                                if (mysqli_num_rows($result) > 0) {
                                                    while ($shift = mysqli_fetch_assoc($result)) {
                                                        $start = new DateTime($shift['start_time']);
                                                        $end = new DateTime($shift['end_time']);
                                                        if ($end < $start) { // Handles overnight shifts
                                                            $end->modify('+1 day');
                                                        }
                                                        $interval = $start->diff($end);
                                                        $duration_hours = $interval->h + ($interval->i / 60);
                                                ?>
                                                    <tr>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($shift['shift_code']) ?></span></td>
                                                        <td><?= htmlspecialchars($shift['shift_name']) ?></td>
                                                        <td><?= $start->format('g:i A') ?></td>
                                                        <td><?= (new DateTime($shift['end_time']))->format('g:i A') ?></td>
                                                        <td><span class="badge bg-light text-dark"><?= number_format($duration_hours, 1) ?>h</span></td>
                                                        <td class="text-end">
                                                            <a href="?edit_id=<?= $shift['shift_id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-pencil-alt"></i></a>
                                                            <form method="POST" action="shift_definitions.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this shift?');">
                                                                <input type="hidden" name="shift_id" value="<?= $shift['shift_id'] ?>">
                                                                <button type="submit" name="delete_shift" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash-alt"></i></button>
                                                            </form>
                                                        </td>
                                                    </tr>
                                                <?php
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" class="text-center py-4 text-muted">No shifts defined yet. Use the form to add one.</td></tr>';
                                                }
                                                ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Add/Edit Form -->
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">
                                        <i class="fas <?= $edit_mode ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i>
                                        <?= $edit_mode ? 'Edit Shift' : 'Add New Shift' ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="shift_definitions.php">
                                        <?php if ($edit_mode): ?>
                                            <input type="hidden" name="shift_id" value="<?= htmlspecialchars($shift_to_edit['shift_id'] ?? '') ?>">
                                        <?php endif; ?>

                                        <div class="mb-3">
                                            <label for="shift_code" class="form-label">Shift Code <span class="text-danger">*</span></label>
                                            <input type="text" id="shift_code" name="shift_code" class="form-control" value="<?= htmlspecialchars($shift_to_edit['shift_code'] ?? '') ?>" placeholder="e.g., MOR, EVE, NGT" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="shift_name" class="form-label">Shift Name <span class="text-danger">*</span></label>
                                            <input type="text" id="shift_name" name="shift_name" class="form-control" value="<?= htmlspecialchars($shift_to_edit['shift_name'] ?? '') ?>" placeholder="e.g., Morning Shift" required>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="start_time" class="form-label">Start Time <span class="text-danger">*</span></label>
                                                <input type="time" id="start_time" name="start_time" class="form-control" value="<?= htmlspecialchars($shift_to_edit['start_time'] ?? '') ?>" required>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="end_time" class="form-label">End Time <span class="text-danger">*</span></label>
                                                <input type="time" id="end_time" name="end_time" class="form-control" value="<?= htmlspecialchars($shift_to_edit['end_time'] ?? '') ?>" required>
                                            </div>
                                        </div>
                                        <div class="d-grid gap-2">
                                            <?php if ($edit_mode): ?>
                                                <button type="submit" name="update_shift" class="btn btn-primary"><i class="fas fa-save me-2"></i>Update Shift</button>
                                                <a href="shift_definitions.php" class="btn btn-secondary"><i class="fas fa-times me-2"></i>Cancel Edit</a>
                                            <?php else: ?>
                                                <button type="submit" name="add_shift" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Add Shift</button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal (Identical to previous version) -->
    <div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
        <!-- ... modal content ... -->
    </div>
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
        });
    </script>
    <script>
    // Self-dismissing alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', () => {
        const alert = document.querySelector('.alert-dismissible');
        if (alert) {
            setTimeout(() => {
                new bootstrap.Alert(alert).close();
            }, 5000);
        }
    });
    </script>
</body>
</html>