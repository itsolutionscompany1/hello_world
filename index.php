
<?php
ob_start();
session_start();

// This file now includes both $con_patcom and $con_payroll
include("hms/include/config.php"); 

// --- 1. SETUP MESSAGE HANDLING ---
$page_message = "";
$message_type = ""; // This will be 'error' or 'info' to style the message box

// Check for messages from redirects (like automatic logout)
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'inactive') {
        $page_message = "You have been automatically logged out due to inactivity.";
        $message_type = 'info';
    }
    if ($_GET['error'] === 'session_expired') {
        $page_message = "Your session has expired or was terminated. Please log in again.";
        $message_type = 'info';
    }
}


// --- 2. REDIRECT LOGIC ---
// If user is already logged in, redirect them away from the login page
if (isset($_SESSION['dlogin']) && isset($_SESSION['session_token'])) {
    header("location: hms/staff/dashboard.php");
    exit();
}


// --- 3. FORM SUBMISSION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- HANDLE STAFF LOGIN (Uses PatCom) ---
    if (isset($_POST['form_type']) && $_POST['form_type'] === 'staff') {
        $username = $_POST['username'];
        $hashed_password = md5($_POST['password']); 

        // Querying umanusers in the PatCom database
        $sql = "SELECT UsrId, UserName, UserFullNames FROM umanusers WHERE UserName = ? AND BINARY UserPassWord = ?";
        $stmt = mysqli_prepare($con_patcom, $sql);
        if ($stmt === false) { die('SQL Error (Staff Login): ' . mysqli_error($con_patcom)); }

        mysqli_stmt_bind_param($stmt, "ss", $username, $hashed_password);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user_data = mysqli_fetch_assoc($result);

        if ($user_data) {
            // --- TOKEN & SESSION LOGIC ---
            $user_id = $user_data['UsrId'];
            
            // 1. Generate a secure token
            $token = bin2hex(random_bytes(32));

            // 2. Delete any old sessions for this user (logs them out everywhere else)
            $delete_sql = "DELETE FROM user_sessions WHERE user_id = ?";
            $delete_stmt = mysqli_prepare($con_patcom, $delete_sql);
            mysqli_stmt_bind_param($delete_stmt, "i", $user_id);
            mysqli_stmt_execute($delete_stmt);

            // 3. Store the new session token in the database
            $ip_address = $_SERVER['REMOTE_ADDR'];
            $user_agent = $_SERVER['HTTP_USER_AGENT'];
            $insert_sql = "INSERT INTO user_sessions (user_id, session_token, ip_address, user_agent) VALUES (?, ?, ?, ?)";
            $insert_stmt = mysqli_prepare($con_patcom, $insert_sql);
            mysqli_stmt_bind_param($insert_stmt, "isss", $user_id, $token, $ip_address, $user_agent);
            
            if (mysqli_stmt_execute($insert_stmt)) {
                // 4. Store essential info in the PHP session
                $_SESSION['dlogin'] = $user_data['UserName'];
                $_SESSION['id'] = $user_data['UsrId'];
                $_SESSION['user_name'] = $user_data['UserFullNames'];
                $_SESSION['session_token'] = $token;
                
                // --- NEW: START THE INACTIVITY TIMER ---
                $_SESSION['last_activity'] = time(); // Record the current timestamp
                
                // 5. Redirect to the dashboard
                header("location: hms/staff/dashboard.php");
                exit();
            } else {
                $page_message = "Could not create a secure session. Please try again.";
                $message_type = 'error';
            }
        } else {
            $page_message = "Invalid Staff username or password.";
            $message_type = 'error';
        }
    }
    // --- (Your Admin login logic can be updated similarly if needed) ---
}
?>
<!-- Your HTML code for the login page remains exactly the same -->

<!-- ... Paste the rest of your HTML/CSS/JS code here ... -->
<!-- Your HTML code for the login page remains exactly the same -->
<!doctype html>
<!-- The entire HTML and CSS section from the previous step is correct and does not need to be changed. -->
<!-- Paste the full HTML/CSS/JS code from the previous working version here. -->
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Zion Medical Center | Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-dark: #1a202c; --primary-blue: #2c5282; --accent-green: #38a169; --accent-green-dark: #2f855a; --light-bg: #f7fafc; --white: #ffffff; --dark-text: #2d3748; --medium-text: #4a5568; --light-text: #718096; --border-color: #e2e8f0; --error-red-bg: #f8d7da; --error-red-text: #721c24; }
        body { font-family: 'Roboto', sans-serif; margin: 0; background-color: var(--light-bg); display: flex; min-height: 100vh; }
        .info-panel { width: 50%; padding: 60px; background: linear-gradient(rgba(44, 82, 130, 0.85), rgba(26, 32, 44, 0.95)), url('https://images.unsplash.com/photo-1576091160550-2173dba999ab?q=80&w=2070&auto=format=fit=crop') center/cover; color: white; display: flex; flex-direction: column; justify-content: center; }
        .logo-container { margin-bottom: 40px; font-family: 'Montserrat', sans-serif; font-size: 2rem; font-weight: 800; color: var(--white); }
        .logo-container i { margin-right: 15px; color: var(--accent-green); }
        .info-content h1 { font-family: 'Montserrat', sans-serif; font-size: 2.8rem; margin-bottom: 20px; font-weight: 700; }
        .info-content p { font-size: 1.1rem; line-height: 1.7; margin-bottom: 30px; opacity: 0.9; }
        .features-list { margin-top: 40px; }
        .feature-item { display: flex; align-items: flex-start; margin-bottom: 25px; }
        .feature-icon { background-color: rgba(56, 161, 105, 0.2); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20px; flex-shrink: 0; color: var(--accent-green); }
        .feature-text h3 { margin: 0 0 5px 0; font-weight: 600; font-size: 1.1rem; }
        .feature-text p { margin: 0; font-size: 0.95rem; opacity: 0.8; }
        .action-panel { width: 50%; padding: 60px; display: flex; flex-direction: column; justify-content: center; }
        .action-tabs { display: flex; margin-bottom: 30px; border-bottom: 1px solid var(--border-color); }
        .tab-btn { padding: 12px 20px; background: none; border: none; font-family: 'Montserrat', sans-serif; font-weight: 600; color: var(--light-text); cursor: pointer; position: relative; margin-right: 5px; font-size: 1rem; }
        .tab-btn.active { color: var(--primary-blue); }
        .tab-btn.active::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 100%; height: 3px; background-color: var(--accent-green); }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.5s ease; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .form-header h2 { font-family: 'Montserrat', sans-serif; font-size: 1.8rem; color: var(--dark-text); margin-bottom: 10px; }
        .form-header p { color: var(--light-text); margin: 0; }
        .form-group { margin-bottom: 20px; position: relative; }
        .form-control { width: 100%; padding: 12px 15px; box-sizing: border-box; border: 1px solid var(--border-color); border-radius: 6px; font-size: 1rem; }
        .btn-block { display: block; width: 100%; padding: 12px 24px; background-color: var(--accent-green); color: white; border: none; border-radius: 6px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; font-family: 'Montserrat', sans-serif; }
        .btn-block:hover { background-color: var(--accent-green-dark); }
        .error-message { padding: 15px; margin-bottom: 20px; border-radius: 8px; background-color: var(--error-red-bg); color: var(--error-red-text); text-align: center; }
        @media (max-width: 1024px) { body { flex-direction: column; } .info-panel, .action-panel { width: 100%; box-sizing: border-box; } }
    </style>
</head>
<body>
    <div class="info-panel">
        <div class="logo-container"><i class="fas fa-hospital"></i> ZION MEDICAL CENTER</div>
        <div class="info-content">
            <h1>The Heart of World-Class Healthcare</h1>
            <p>Welcome to the central portal for our dedicated staff. Manage schedules, track attendance, and access essential resources to help us continue providing compassionate, patient-centered care.</p>
            <div class="features-list">
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-calendar-check"></i></div><div class="feature-text"><h3>Attendance Management</h3><p>Easily clock in and out, and view your work history at a glance.</p></div></div>
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-calendar-alt"></i></div><div class="feature-text"><h3>Leave &amp; Time Off</h3><p>Submit and track leave requests through a streamlined, digital process.</p></div></div>
                <div class="feature-item"><div class="feature-icon"><i class="fas fa-bullhorn"></i></div><div class="feature-text"><h3>Stay Informed</h3><p>Access important announcements, schedules, and hospital updates.</p></div></div>
            </div>
        </div>
    </div>
    <div class="action-panel">
        <div class="action-tabs">
            <button class="tab-btn active" data-tab="staff">Staff Login</button>
            <button class="tab-btn" data-tab="admin">Admin Login</button>
        </div>
        <?php if (!empty($login_error)): ?>
            <div class="error-message"><?php echo htmlspecialchars($login_error); ?></div>
        <?php endif; ?>
        <div class="tab-content active" id="staff-tab">
            <div class="form-header"><h2>Staff Portal Access</h2><p>Please enter your credentials to continue.</p></div>
            <form action="" method="POST">
                <input type="hidden" name="form_type" value="staff">
                <!-- IMPORTANT: The placeholder and label now say "Username" to match the database -->
                <div class="form-group"><label for="staff-id">Staff Username</label><input type="text" id="staff-id" name="username" class="form-control" placeholder="e.g., john.doe" required></div>
                <div class="form-group"><label for="staff-password">Password</label><input type="password" id="staff-password" name="password" class="form-control" placeholder="Enter your password" required></div>
                <button type="submit" class="btn-block">Login</button>
            </form>
        </div>
        <div class="tab-content" id="admin-tab">
            <div class="form-header"><h2>Administrator Access</h2><p>For authorized administrative personnel only.</p></div>
             <form action="" method="POST">
                <input type="hidden" name="form_type" value="admin">
                <div class="form-group"><label for="admin-id">Admin Username</label><input type="text" id="admin-id" name="username" class="form-control" placeholder="Enter your admin username" required></div>
                <div class="form-group"><label for="admin-password">Password</label><input type="password" id="admin-password" name="password" class="form-control" placeholder="Enter your password" required></div>
                <button type="submit" class="btn-block">Login as Admin</button>
            </form>
        </div>
    </div>
    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                btn.classList.add('active');
                const tabId = btn.getAttribute('data-tab') + '-tab';
                document.getElementById(tabId).classList.add('active');
            });
        });
    </script>
</body>
</html>
<?php
ob_end_flush();
?>