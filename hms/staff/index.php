<?php
session_start();
include("include/config.php");
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect logic to ensure correct user flow
if (isset($_SESSION['id']) && !isset($_SESSION['store_id'])) {
    header('location: select-location.php');
    exit();
}
if (isset($_SESSION['id']) && isset($_SESSION['store_id'])) {
    header('location: dashboard.php');
    exit();
}

// --- SERVER-SIDE AUTHENTICATION LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => 'An unknown error occurred.'];

    if (!$con) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection error.']);
        exit();
    }

    $data = json_decode(file_get_contents("php://input"));
    $uname = $data->username ?? '';
    $dpassword_md5 = md5($data->password ?? '');

    // =============================================================
    // =================== THE PERMANENT FIX BLOCK ===================
    // =============================================================
    // ** Using YOUR original, working query that targets the `doctors` table **
    $stmt = mysqli_prepare($con, "SELECT id, doctorName, docEmail FROM doctors WHERE docEmail=? AND password=?");
    mysqli_stmt_bind_param($stmt, "ss", $uname, $dpassword_md5);
    // =============================================================
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $num = mysqli_fetch_array($result);

    if ($num) {
        // ** Using YOUR original, working session variables **
        $_SESSION['dlogin'] = $num['docEmail'];
        $_SESSION['id'] = $num['id'];
        $_SESSION['user_name'] = $num['doctorName'];
        
        $uip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($con, "INSERT INTO doctorslog(uid, username, userip, status) VALUES('{$num['id']}', '$uname', '$uip', 1)");
        
        $response = ['success' => true, 'message' => "Success! Redirecting...", 'redirect' => 'select-location.php'];
    } else {
        $uip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($con, "INSERT INTO doctorslog(username, userip, status) VALUES('$uname', '$uip', 0)");
        $response['message'] = "Invalid email or password.";
    }
    
    echo json_encode($response);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Staff Login | Seamrise Company</title>
    <!-- Your CSS from the redesigned page -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary-dark: #1a202c; --primary-blue: #2c5282; --accent-yellow: #f59e0b; --accent-yellow-dark: #d97706; --light-bg: #f7fafc; --white: #ffffff; --dark-text: #2d3748; --medium-text: #4a5568; --light-text: #718096; --border-color: #e2e8f0; --success-green-bg: #d4edda; --success-green-text: #155724; --error-red-bg: #f8d7da; --error-red-text: #721c24; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Roboto', sans-serif; background-color: var(--light-bg); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .login-wrapper { display: flex; width: 100%; max-width: 1000px; background: var(--white); border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1); overflow: hidden; }
        .login-banner { flex-basis: 50%; background: linear-gradient(rgba(26, 32, 44, 0.85), rgba(26, 32, 44, 0.95)), url('https://images.unsplash.com/photo-1624455240156-14644a3e47a9?q=80&w=1974&auto=format&fit=crop') center/cover; color: white; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 60px; text-align: center; }
        .login-banner .icon { font-size: 60px; margin-bottom: 20px; color: var(--accent-yellow); }
        .login-banner h1 { font-family: 'Montserrat', sans-serif; font-size: 2.5rem; font-weight: 700; }
        .login-banner p { font-size: 1.1rem; opacity: 0.9; margin-top: 15px; }
        .login-form { flex-basis: 50%; display: flex; flex-direction: column; justify-content: center; padding: 60px; }
        .login-form h2 { font-family: 'Montserrat', sans-serif; font-size: 2rem; font-weight: 700; color: var(--dark-text); }
        .login-form .subtitle { font-size: 1rem; color: var(--light-text); margin-bottom: 30px; }
        .input-group { position: relative; margin-bottom: 25px; }
        .input-field { width: 100%; height: 50px; border: 1px solid var(--border-color); border-radius: 8px; padding-left: 45px; padding-right: 15px; font-size: 16px; font-family: 'Roboto', sans-serif; color: var(--dark-text); transition: all 0.3s; }
        .input-group .icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--light-text); transition: color 0.3s; }
        .input-field:focus { outline: none; border-color: var(--primary-blue); box-shadow: 0 0 0 3px rgba(44, 82, 130, 0.1); }
        .input-field:focus ~ .icon { color: var(--primary-blue); }
        .btn-submit { width: 100%; height: 50px; background: var(--accent-yellow); color: var(--white); border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; justify-content: center; align-items: center; font-family: 'Montserrat', sans-serif; }
        .btn-submit:hover:not(:disabled) { background-color: var(--accent-yellow-dark); }
        .btn-submit:disabled { background-color: var(--light-text); cursor: not-allowed; }
        .btn-submit .spinner { border: 2px solid rgba(255,255,255,0.5); border-left-color: var(--white); border-radius: 50%; width: 18px; height: 18px; animation: spin 1s linear infinite; display: none; }
        .btn-submit.loading .btn-text { display: none; } .btn-submit.loading .spinner { display: block; } @keyframes spin { to { transform: rotate(360deg); } }
        #message { margin-top: 20px; padding: 12px; border-radius: 8px; text-align: center; font-size: 0.95rem; display: none; }
        #message.success { background-color: var(--success-green-bg); color: var(--success-green-text); }
        #message.error { background-color: var(--error-red-bg); color: var(--error-red-text); }
        .footer { text-align: center; margin-top: 30px; font-size: 14px; color: var(--light-text); }
        @media (max-width: 768px) { .login-wrapper { flex-direction: column; } .login-banner { display: none; } .login-form { width: 100%; } }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-banner">
            <i class="fas fa-cogs icon"></i>
            <h1>Seamrise Company</h1>
            <p>Staff & B2B Partner Portal</p>
        </div>
        <div class="login-form">
            <h2>Staff Login</h2>
            <p class="subtitle">Please enter your credentials to continue.</p>
            <form id="loginForm" method="post" novalidate>
                <div class="input-group">
                    <input type="email" class="input-field" id="username" name="username" placeholder="Email Address" required>
                    <i class="fas fa-envelope icon"></i>
                </div>
                <div class="input-group">
                    <input type="password" class="input-field" id="password" name="password" placeholder="Password" required>
                    <i class="fas fa-lock icon"></i>
                </div>
                <button type="submit" class="btn-submit" id="loginButton">
                    <span class="btn-text">Sign In</span>
                    <div class="spinner"></div>
                </button>
            </form>
            <div id="message"></div>
            <div class="footer">
                <p>&copy; <?php echo date("Y"); ?>  Medico Company Ltd.</p>
            </div>
        </div>
    </div>
    <script>
        // The JavaScript does not need to be changed. It is correct.
        document.addEventListener('DOMContentLoaded', () => {
            const loginForm = document.getElementById('loginForm');
            const messageDiv = document.getElementById('message');
            const loginButton = document.getElementById('loginButton');
            loginForm.addEventListener('submit', async (event) => {
                event.preventDefault();
                loginButton.classList.add('loading');
                loginButton.disabled = true;
                messageDiv.style.display = 'none';
                const data = { username: loginForm.username.value, password: loginForm.password.value };
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'Accept': 'application/json'},
                        body: JSON.stringify(data)
                    });
                    const result = await response.json();
                    messageDiv.textContent = result.message;
                    messageDiv.style.display = 'block';
                    if (result.success) {
                        messageDiv.className = 'success';
                        setTimeout(() => { window.location.href = result.redirect; }, 1000);
                    } else {
                        messageDiv.className = 'error';
                        loginButton.classList.remove('loading');
                        loginButton.disabled = false;
                    }
                } catch (error) {
                    messageDiv.textContent = 'A network or server error occurred.';
                    messageDiv.className = 'error';
                    messageDiv.style.display = 'block';
                    loginButton.classList.remove('loading');
                    loginButton.disabled = false;
                }
            });
        });
    </script>
</body>
</html>