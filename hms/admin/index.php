<?php
session_start();
include("include/config.php");
// I've kept your error reporting setting, but for development, it's better to show all errors.
// error_reporting(E_ALL); 
// ini_set('display_errors', 1);
error_reporting(0);

if(isset($_POST['submit'])) {
    $uname = $_POST['username'];
    $upassword = $_POST['password']; // In a real system, passwords should be hashed.

    // --- SECURITY UPGRADE: Using Prepared Statements to prevent SQL Injection ---
    $stmt = mysqli_prepare($con, "SELECT id, username FROM admin WHERE username=? AND password=?");
    mysqli_stmt_bind_param($stmt, "ss", $uname, $upassword);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $num = mysqli_fetch_array($result);

    if($num) {
        $_SESSION['login'] = $num['username'];
        $_SESSION['id'] = $num['id'];
        header("location:dashboard.php");
        exit(); // Always exit after a header redirect
    } else {
        // Set the error message to be displayed in the form
        $_SESSION['errmsg'] = "Invalid username or password";
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login | Zion Medical Center</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Google Fonts & Font Awesome -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary-blue: #2c5282; 
            --accent-green: #38a169; 
            --accent-green-dark: #2f855a; 
            --light-bg: #f7fafc; 
            --white: #ffffff; 
            --dark-text: #2d3748; 
            --medium-text: #4a5568; 
            --light-text: #718096; 
            --border-color: #e2e8f0; 
            --error-red-bg: #f8d7da; 
            --error-red-text: #721c24; 
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Roboto', sans-serif;
            background-color: var(--light-bg);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .login-container {
            width: 100%;
            max-width: 450px;
        }
        .login-box {
            background: var(--white);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            text-align: center;
        }
        .logo {
            margin-bottom: 25px;
        }
        .logo i {
            font-size: 48px;
            color: var(--accent-green);
        }
        .logo h2 {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            color: var(--dark-text);
            margin-top: 10px;
        }
        .logo p {
            color: var(--light-text);
            margin-top: 5px;
        }
        .error-message {
            background-color: var(--error-red-bg);
            color: var(--error-red-text);
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 0.95rem;
        }
        .input-group {
            position: relative;
            margin-bottom: 25px;
            text-align: left;
        }
        .input-field {
            width: 100%;
            height: 50px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding-left: 45px;
            padding-right: 15px;
            font-size: 16px;
            font-family: 'Roboto', sans-serif;
            color: var(--dark-text);
            transition: all 0.3s;
        }
        .input-group .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--light-text);
            transition: color 0.3s;
        }
        .input-field:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(44, 82, 130, 0.1);
        }
        .input-field:focus ~ .icon {
            color: var(--primary-blue);
        }
        .btn-submit {
            width: 100%;
            height: 50px;
            background: var(--accent-green);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: 'Montserrat', sans-serif;
        }
        .btn-submit:hover {
            background-color: var(--accent-green-dark);
        }
        .footer-links {
            margin-top: 25px;
        }
        .footer-links a {
            color: var(--primary-blue);
            text-decoration: none;
            font-size: 0.9rem;
        }
        .footer-links a:hover {
            text-decoration: underline;
        }
        .copyright {
            margin-top: 30px;
            font-size: 14px;
            color: var(--light-text);
        }
    </style>
</head>
<body class="login">
    <div class="login-container">
        <div class="login-box">
            <div class="logo">
                <i class="fas fa-user-shield"></i>
                <h2>Zion Medical Center</h2>
                <p>Admin Portal Login</p>
            </div>

            <form class="form-login" method="post">
                <fieldset>
                    <!-- PHP logic to display the error message -->
                    <?php if (isset($_SESSION['errmsg']) && !empty($_SESSION['errmsg'])): ?>
                        <div class="error-message">
                            <?php 
                                echo htmlentities($_SESSION['errmsg']);
                                $_SESSION['errmsg'] = ""; // Clear the message after displaying it
                            ?>
                        </div>
                    <?php endif; ?>

                    <div class="input-group">
                        <input type="text" class="input-field" name="username" placeholder="Username" required>
                        <i class="fas fa-user icon"></i>
                    </div>

                    <div class="input-group">
                        <input type="password" class="input-field" name="password" placeholder="Password" required>
                        <i class="fas fa-lock icon"></i>
                    </div>
                    
                    <button type="submit" class="btn-submit" name="submit">
                        Login <i class="fa fa-arrow-circle-right" style="margin-left: 8px;"></i>
                    </button>
                </fieldset>
            </form>

            <div class="footer-links">
                <a href="../index.php">
                    <i class="fas fa-home"></i> Back to Main Portal
                </a>
            </div>
        </div>

        <div class="copyright">
            &copy; <?php echo date("Y"); ?> Zion Medical Center | All Rights Reserved
        </div>
    </div>
</body>
</html>