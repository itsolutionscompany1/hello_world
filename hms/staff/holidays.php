<?php
require_once('session_auth.php'); 

error_reporting(E_ALL);
require_once('session_auth.php'); 


// Security Check
if (strlen($_SESSION['id'] ?? '') == 0) {
    header('location:../logout.php');
    exit();
}

$message = '';
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle POST actions (Add or Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // Add a new holiday
    if ($_POST['action'] == 'add') {
        $holiday_date = $_POST['holiday_date'];
        $holiday_name = trim($_POST['holiday_name']);

        if (empty($holiday_date) || empty($holiday_name)) {
            $_SESSION['message'] = '<div class="alert alert-danger">Date and Name are required.</div>';
        } else {
            $sql = "INSERT INTO public_holidays (holiday_date, holiday_name) VALUES (?, ?)";
            $stmt = mysqli_prepare($con_payroll, $sql);
            mysqli_stmt_bind_param($stmt, 'ss', $holiday_date, $holiday_name);
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['message'] = '<div class="alert alert-success">Holiday added successfully.</div>';
            } else {
                $_SESSION['message'] = '<div class="alert alert-danger">Error: This date may already exist.</div>';
            }
            mysqli_stmt_close($stmt);
        }
    }

    // Delete a holiday
    if ($_POST['action'] == 'delete') {
        $holiday_id = intval($_POST['holiday_id']);
        $sql = "DELETE FROM public_holidays WHERE id = ?";
        $stmt = mysqli_prepare($con_payroll, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $holiday_id);
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['message'] = '<div class="alert alert-info">Holiday removed.</div>';
        } else {
            $_SESSION['message'] = '<div class="alert alert-danger">Error deleting holiday.</div>';
        }
        mysqli_stmt_close($stmt);
    }
    // Redirect to prevent form resubmission
    header("Location: holidays.php?year=$year");
    exit();
}

// Display session message after redirect
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}


// Fetch all holidays for the selected year
$holidays = [];
$sql_fetch = "SELECT id, holiday_date, holiday_name FROM public_holidays WHERE YEAR(holiday_date) = ? ORDER BY holiday_date ASC";
$stmt_fetch = mysqli_prepare($con_payroll, $sql_fetch);
mysqli_stmt_bind_param($stmt_fetch, 'i', $year);
mysqli_stmt_execute($stmt_fetch);
$result = mysqli_stmt_get_result($stmt_fetch);
while ($row = mysqli_fetch_assoc($result)) {
    $holidays[] = $row;
}
mysqli_stmt_close($stmt_fetch);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
            <div class="container-fluid">
                <h1 class="mainTitle">Manage Public Holidays</h1>
                <?= $message ?>
                <div class="row">
                    <!-- Left Column: Add Holiday Form -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0"><i class="fas fa-plus-circle me-2"></i>Add New Holiday</h5></div>
                            <div class="card-body">
                                <form method="POST" action="holidays.php?year=<?= $year ?>">
                                    <input type="hidden" name="action" value="add">
                                    <div class="mb-3"><label for="holiday_date" class="form-label">Date:</label><input type="date" name="holiday_date" id="holiday_date" class="form-control" required></div>
                                    <div class="mb-3"><label for="holiday_name" class="form-label">Holiday Name:</label><input type="text" name="holiday_name" id="holiday_name" class="form-control" placeholder="e.g., New Year's Day" required></div>
                                    <button type="submit" class="btn btn-primary w-100">Add Holiday</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: List of Defined Holidays -->
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header"><h5 class="card-title mb-0 d-inline-block"><i class="fas fa-calendar-alt me-2"></i>Defined Holidays for</h5>
                                <form method="GET" class="d-inline-block ms-2"><select name="year" class="form-select-sm" onchange="this.form.submit()"><?php for ($y = date('Y') - 2; $y <= date('Y') + 3; $y++) { echo '<option value="'.$y.'" '.($y == $year ? 'selected' : '').'>'.$y.'</option>'; } ?></select></form>
                            </div>
                            <div class="card-body">
                                <?php if (empty($holidays)): ?>
                                    <p class="text-center text-muted">No holidays defined for <?= $year ?>.</p>
                                <?php else: ?>
                                    <ul class="list-group">
                                        <?php foreach ($holidays as $holiday): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div><strong><?= date("F j, Y", strtotime($holiday['holiday_date'])) ?></strong> (<?= date("l", strtotime($holiday['holiday_date'])) ?>)<br><small class="text-muted"><?= htmlspecialchars($holiday['holiday_name']) ?></small></div>
                                                <form method="POST" action="holidays.php?year=<?= $year ?>" onsubmit="return confirm('Are you sure you want to delete this holiday?');">
                                                    <input type="hidden" name="action" value="delete"><input type="hidden" name="holiday_id" value="<?= $holiday['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
   <!-- JS Includes -->
   <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
    $(document).ready(function() {
        Main.init(); // Initialize the template theme
    });
    </script>
</body>
</html>