<!-- start: HEADER -->
<style>
    /* 
     * Custom Header Styles
     * This moves inline styles to a dedicated block for cleaner code.
     */
    .navbar-default {
        background-color: #ffffff;
        border-bottom: 1px solid #e0e0e0;
        /* Adds a subtle shadow for a modern, lifted look */
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    .navbar-brand {
        font-weight: 600; /* Slightly bolder for better visibility */
        font-size: 18px;
        height: 60px; /* Increased height for better spacing */
        line-height: 30px;
    }
    .navbar-brand .fa-hospital-alt {
        color: #007bff; /* A modern blue color */
    }
    .main-title-container {
        /* Vertically centers the main title */
        display: flex;
        align-items: center;
        height: 60px;
        padding: 0 15px;
    }
    .main-title-container .main-title-text {
        color: #5a5a5a;
        font-weight: 300; /* A lighter font weight for the subtitle */
        font-size: 1.2em;
    }
    .department-display, .current-user > a {
        /* Ensures all right-side items are vertically centered */
        display: flex;
        align-items: center;
        height: 60px;
        padding: 0 15px;
    }
    .department-display {
        color: #717171;
        border-left: 1px solid #eee;
    }
    .department-display .fa-building {
        color: #28a745; /* A vibrant green */
        margin-right: 8px;
    }
    .current-user .username-icon {
        margin-right: 8px;
        opacity: 0.7;
    }
</style>

<header class="navbar navbar-default navbar-static-top">
    <!-- start: NAVBAR HEADER -->
    <div class="navbar-header">
        <a href="#" class="sidebar-mobile-toggler pull-left hidden-md hidden-lg btn btn-navbar sidebar-toggle" data-toggle-class="app-slide-off" data-toggle-target="#app" data-toggle-click-outside="#sidebar">
            <i class="ti-align-justify"></i>
        </a>
        <a class="navbar-brand" href="dashboard.php">
            <i class="fa fa-hospital-alt"></i> 
            <span>Zion Staff Portal</span>
        </a>
        <a href="#" class="sidebar-toggler pull-right visible-md visible-lg" data-toggle-class="app-sidebar-closed" data-toggle-target="#app">
            <i class="ti-align-justify"></i>
        </a>
        <a class="pull-right menu-toggler visible-xs-block" id="menu-toggler" data-toggle="collapse" href=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <i class="ti-view-grid"></i>
        </a>
    </div>
    <!-- end: NAVBAR HEADER -->

    <!-- start: NAVBAR COLLAPSE -->
    <div class="navbar-collapse collapse">
        <ul class="nav navbar-nav navbar-right">
            <!-- Central Title -->
            <li class="hidden-xs">
                <div class="main-title-container">
                    <h4 class="main-title-text">Medico Staff Portal</h4>
                </div>
            </li>
        
            <!-- Department Display Block -->
            <?php if (isset($_SESSION['department_name']) && !empty($_SESSION['department_name'])): ?>
            <li class="hidden-xs">
                <div class="department-display">
                    <i class="fa fa-building"></i> 
                    <div>
                        <strong>Department:</strong> 
                        <span><?php echo htmlspecialchars($_SESSION['department_name']); ?></span>
                    </div>
                </div>
            </li>
            <?php endif; ?>

            <!-- start: USER OPTIONS DROPDOWN -->
            <li class="dropdown current-user">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                    <i class="ti-user username-icon"></i>
                    <span class="username">
                        <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Staff Member'); ?> 
                        <i class="ti-angle-down"></i>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-dark">
                    <li>
                        <a href="my-profile.php"><i class="ti-user"></i> My Profile</a>
                    </li>
                    <li>
                        <a href="change-password.php"><i class="ti-lock"></i> Change Password</a>
                    </li>
                    <li class="divider"></li>
                    <li>
                        <!-- Ensure this path is correct for your project structure -->
                        <a href="../../logout.php"><i class="ti-shift-left"></i> Log Out</a>
                    </li>
                </ul>
            </li>
            <!-- end: USER OPTIONS DROPDOWN -->
        </ul>

        <!-- start: MENU TOGGLER FOR MOBILE DEVICES -->
        <div class="close-handle visible-xs-block menu-toggler" data-toggle="collapse" href=".navbar-collapse">
            <div class="arrow-left"></div>
            <div class="arrow-right"></div>
        </div>
        <!-- end: MENU TOGGLER FOR MOBILE DEVICES -->
    </div>
    <!-- end: NAVBAR COLLAPSE -->
</header>
<!-- end: HEADER -->