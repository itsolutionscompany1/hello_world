<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
include('include/config.php');

// 1. ========= SECURITY CHECKS =========
if (strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
    exit();
}

// 2. ========= DATA FETCHING FOR DASHBOARD CARDS (BUSINESS-WIDE) =========

// --- Total Revenue, Profit, and Stock Value ---
$sql_totals = "
    SELECT 
        (SELECT SUM(TotalAmount) FROM tblsales WHERE Status = 'Paid') as total_revenue,
        (SELECT SUM(si.Quantity * p.purchaseprice) FROM tblsaleitems si JOIN tblsales s ON si.SaleID = s.ID JOIN tblproducts p ON si.ProductID = p.ID WHERE s.Status = 'Paid') as total_cost,
        (SELECT SUM(cs.quantity * p.purchaseprice) FROM tblcurrentstock cs JOIN tblproducts p ON cs.product_id = p.ID) as total_stock_value,
        (SELECT COUNT(ID) FROM tblpatient) as total_customers
";
$totals_data = mysqli_fetch_assoc(mysqli_query($con, $sql_totals));
$total_revenue = $totals_data['total_revenue'] ?? 0;
$total_profit = ($totals_data['total_revenue'] ?? 0) - ($totals_data['total_cost'] ?? 0);
$total_stock_value = $totals_data['total_stock_value'] ?? 0;
$total_customers = $totals_data['total_customers'] ?? 0;


// 3. ========= DATA FETCHING FOR LISTS AND CHART (BUSINESS-WIDE) =========

// --- Top 5 Low Stock Items (Across All Stores) ---
$sql_low_list = "
    SELECT p.ProductName, cs.quantity, s.store_name
    FROM tblcurrentstock cs
    JOIN tblproducts p ON cs.product_id = p.ID
    JOIN tblstores s ON cs.store_id = s.id
    WHERE p.reorder_level > 0 AND cs.quantity <= p.reorder_level
    ORDER BY (cs.quantity - p.reorder_level) ASC
    LIMIT 5
";
$low_stock_list = mysqli_fetch_all(mysqli_query($con, $sql_low_list), MYSQLI_ASSOC);

// --- 5 Most Recent Pending Purchase Orders ---
$sql_lpo_list = "
    SELECT lpo_number, lpo_date, status 
    FROM tbllpo 
    WHERE status IN ('Issued', 'Partially Received') 
    ORDER BY lpo_date DESC 
    LIMIT 5
";
$lpo_list = mysqli_fetch_all(mysqli_query($con, $sql_lpo_list), MYSQLI_ASSOC);

// --- Sales Data by Branch (for Pie Chart) ---
$sql_branch_sales = "
    SELECT b.branch_name, SUM(s.TotalAmount) as total_sales
    FROM tblsales s
    JOIN tblstores st ON s.store_id = st.id
    JOIN tblbranches b ON st.branch_id = b.id
    WHERE s.Status = 'Paid'
    GROUP BY b.branch_name
    ORDER BY total_sales DESC
";
$branch_sales_result = mysqli_query($con, $sql_branch_sales);
$branch_chart_labels = [];
$branch_chart_data = [];
while($row = mysqli_fetch_assoc($branch_sales_result)) {
    $branch_chart_labels[] = $row['branch_name'];
    $branch_chart_data[] = (float)$row['total_sales'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin | Business Dashboard</title>
    <!-- Your standard CSS Includes -->
    <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="stylesheet" href="assets/css/plugins.css">
    <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    <style>
        .dashboard-stat { background-color: #fff; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); display: flex; align-items: center; }
        .dashboard-stat-icon { font-size: 2.5em; margin-right: 20px; width: 60px; text-align: center; }
        .dashboard-stat-content h4 { margin: 0 0 5px 0; font-size: 1.5em; font-weight: 700; }
        .dashboard-stat-content span { font-size: 0.9em; color: #888; }
        .panel-scroll { max-height: 250px; overflow-y: auto; }
        .chart-container { height: 300px; position: relative; }
    </style>
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
                            <div class="col-sm-8"><h1 class="mainTitle">Admin | Business Overview</h1></div>
                            <ol class="breadcrumb"><li><span>Admin</span></li><li class="active"><span>Dashboard</span></li></ol>
                        </div>
                    </section>
                    
                    <div class="container-fluid container-fullw bg-white">
                        <!-- KPI Cards Row -->
                        <div class="row">
                            <div class="col-sm-6 col-md-3">
                                <div class="dashboard-stat">
                                    <div class="dashboard-stat-icon text-success"><i class="fa fa-money"></i></div>
                                    <div class="dashboard-stat-content">
                                        <h4>KSh <?php echo number_format($total_revenue, 2); ?></h4>
                                        <span>Total Revenue (All Time)</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="dashboard-stat">
                                    <div class="dashboard-stat-icon text-primary"><i class="fa fa-line-chart"></i></div>
                                    <div class="dashboard-stat-content">
                                        <h4>KSh <?php echo number_format($total_profit, 2); ?></h4>
                                        <span>Estimated Gross Profit</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="dashboard-stat">
                                    <div class="dashboard-stat-icon text-warning"><i class="fa fa-archive"></i></div>
                                    <div class="dashboard-stat-content">
                                        <h4>KSh <?php echo number_format($total_stock_value, 2); ?></h4>
                                        <span>Total Stock Value</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-sm-6 col-md-3">
                                <div class="dashboard-stat">
                                    <div class="dashboard-stat-icon text-info"><i class="fa fa-users"></i></div>
                                    <div class="dashboard-stat-content">
                                        <h4><?php echo number_format($total_customers); ?></h4>
                                        <span>Total Customers</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chart and Lists Row -->
                        <div class="row">
                            <div class="col-md-7">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title">Sales by Branch</h5></div>
                                    <div class="panel-body">
                                        <div class="chart-container">
                                            <canvas id="branchSalesChart"></canvas>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title text-danger"><i class="fa fa-warning"></i> Urgent: Low Stock Items</h5></div>
                                    <div class="panel-body panel-scroll">
                                        <ul class="list-group">
                                            <?php if(empty($low_stock_list)): ?>
                                                <li class="list-group-item">No items are below reorder level.</li>
                                            <?php else: foreach($low_stock_list as $item): ?>
                                                <li class="list-group-item">
                                                    <?php echo htmlspecialchars($item['ProductName']); ?>
                                                    <span class="badge badge-danger pull-right"><?php echo htmlspecialchars($item['store_name']); ?></span>
                                                </li>
                                            <?php endforeach; endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                <div class="panel panel-white">
                                    <div class="panel-heading"><h5 class="panel-title text-info"><i class="fa fa-truck"></i> Recent Pending Orders</h5></div>
                                    <div class="panel-body panel-scroll">
                                        <ul class="list-group">
                                            <?php if(empty($lpo_list)): ?>
                                                <li class="list-group-item">No pending purchase orders.</li>
                                            <?php else: foreach($lpo_list as $lpo): ?>
                                                <li class="list-group-item">
                                                    <?php echo htmlspecialchars($lpo['lpo_number']); ?>
                                                    <span class="badge pull-right"><?php echo htmlspecialchars($lpo['status']); ?></span>
                                                </li>
                                            <?php endforeach; endif; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php include('include/footer.php');?>
        </div>
    </div>
    <!-- JS Includes -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script> if (!$.cookie('skin_color')) { $.cookie('skin_color', 'theme-1', { path: '/' }); } </script>
    <script src="assets/js/main.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
            
            // --- Branch Sales Pie Chart ---
            const ctx = document.getElementById('branchSalesChart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($branch_chart_labels); ?>,
                    datasets: [{
                        label: 'Sales',
                        data: <?php echo json_encode($branch_chart_data); ?>,
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.7)',
                            'rgba(54, 162, 235, 0.7)',
                            'rgba(255, 206, 86, 0.7)',
                            'rgba(75, 192, 192, 0.7)',
                            'rgba(153, 102, 255, 0.7)',
                            'rgba(255, 159, 64, 0.7)'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'right' }
                    }
                }
            });
        });
    </script>
</body>
</html>