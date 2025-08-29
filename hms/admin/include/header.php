<header class="navbar navbar-default navbar-static-top">
    <!-- start: NAVBAR HEADER -->
    <div class="navbar-header">
        <a href="#" class="sidebar-mobile-toggler pull-left hidden-md hidden-lg" class="btn btn-navbar sidebar-toggle" data-toggle-class="app-slide-off" data-toggle-target="#app" data-toggle-click-outside="#sidebar">
            <i class="ti-align-justify"></i>
        </a>
        <a class="navbar-brand" href="dashboard.php" style="padding-top: 15px;">
            <i class="fa fa-cogs"></i> 
            <span style="font-weight: bold;">SEAMRISE</span>
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
        <ul class="nav navbar-right">
            <!-- Central Title -->
            <li style="padding-top: 18px;">
                <h4 style="color: #5a5a5a;">Seamrise Company | Admin Panel</h4>
            </li>
        
            <!-- Location Display Block -->
            <?php if (isset($_SESSION['store_name']) && isset($_SESSION['branch_name'])): ?>
            <li class="hidden-xs" style="padding-top: 15px; margin-left: 20px; color: #717171;">
                <div class="current-location">
                    <i class="fa fa-map-marker" style="color: #007bff;"></i> 
                    <strong>Active Location:</strong> 
                    <span><?php echo htmlspecialchars($_SESSION['branch_name']); ?> / <?php echo htmlspecialchars($_SESSION['store_name']); ?></span>
                </div>
            </li>
            <?php endif; ?>

            <li class="dropdown current-user">
                <a href class="dropdown-toggle" data-toggle="dropdown">
                    <img src="assets/images/images.jpg" alt="Admin Avatar"> 
                    <span class="username">
                        <?php 
                            // This safely gets the logged-in admin's name from the session
                            echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); 
                        ?> 
                        <i class="ti-angle-down"></i>
                    </span>
                </a>
                <ul class="dropdown-menu dropdown-dark">
                    <li>
                        <a href="change-password.php">
                            Change Password
                        </a>
                    </li>
                    <li>
                        <a href="logout.php">
                            Log Out
                        </a>
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
</header>```

