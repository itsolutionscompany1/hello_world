<?php
session_start();
include('include/config.php');

if(isset($_POST['submit']))
{
	$fname = $_POST['full_name'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $gender = $_POST['gender'];
    $email = $_POST['email']; // Changed from username back to email to match table
    $password = md5($_POST['password']);
    
    // Using correct column names that match your table
    $query = mysqli_query($con, "INSERT INTO users(fullName, address, city, gender, email, password) 
                                VALUES('$fname', '$address', '$city', '$gender', '$email', '$password')");
    if($query)
    {
        echo "<script>alert('Successfully Registered');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <title>User Registration</title>
        
        <link href="http://fonts.googleapis.com/css?family=Lato:300,400,400italic,600,700|Raleway:300,400,500,600,700|Crete+Round:400italic" rel="stylesheet" type="text/css" />
        <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
        <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
        <link rel="stylesheet" href="vendor/themify-icons/themify-icons.min.css">
        <link href="vendor/animate.css/animate.min.css" rel="stylesheet" media="screen">
        <link href="vendor/perfect-scrollbar/perfect-scrollbar.min.css" rel="stylesheet" media="screen">
        <link href="vendor/switchery/switchery.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-touchspin/jquery.bootstrap-touchspin.min.css" rel="stylesheet" media="screen">
        <link href="vendor/select2/select2.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-datepicker/bootstrap-datepicker3.standalone.min.css" rel="stylesheet" media="screen">
        <link href="vendor/bootstrap-timepicker/bootstrap-timepicker.min.css" rel="stylesheet" media="screen">
        <link rel="stylesheet" href="assets/css/styles.css">
        <link rel="stylesheet" href="assets/css/plugins.css">
        <link rel="stylesheet" href="assets/css/themes/theme-1.css" id="skin_color" />
    </head>
    
    <body>
        <div id="app">        
            <?php include('include/sidebar.php');?>
            <div class="app-content">
                <?php include('include/header.php');?>
                
                <div class="main-content">
                    <div class="wrap-content container" id="container">
                        <section id="page-title">
                            <div class="row">
                                <div class="col-sm-8">
                                    <h1 class="mainTitle">User Registration</h1>
                                </div>
                                <ol class="breadcrumb">
                                    <li>
                                        <span>Admin</span>
                                    </li>
                                    <li class="active">
                                        <span>User Registration</span>
                                    </li>
                                </ol>
                            </div>
                        </section>
                        
                        <div class="container-fluid container-fullw bg-white">
                            <div class="row">
                                <div class="col-md-12">
                                    <div class="row">
                                        <div class="col-md-6 col-md-offset-3">
                                            <div class="box-register">
                                                <form name="registration" id="registration" method="post">
                                                    <fieldset>
                                                        <legend>Sign Up</legend>
                                                        <p>Enter your personal details below:</p>
                                                        
                                                        <div class="form-group">
                                                            <input type="text" class="form-control" name="full_name" placeholder="Full Name" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="text" class="form-control" name="address" placeholder="Address" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <input type="text" class="form-control" name="city" placeholder="City" required>
                                                        </div>
                                                        <div class="form-group">
                                                            <label class="block">Gender</label>
                                                            <div class="clip-radio radio-primary">
                                                                <input type="radio" id="rg-female" name="gender" value="female">
                                                                <label for="rg-female">Female</label>
                                                                <input type="radio" id="rg-male" name="gender" value="male">
                                                                <label for="rg-male">Male</label>
                                                            </div>
                                                        </div>
                                                        
                                                        <p>Enter your account details below:</p>
                                                        
                                                        <div class="form-group">
                                                            <span class="input-icon">
                                                                <input type="text" class="form-control" name="email" placeholder="email" required>
                                                                <i class="fa fa-user"></i> 
                                                            </span>
                                                        </div>
                                                        <div class="form-group">
                                                            <span class="input-icon">
                                                                <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
                                                                <i class="fa fa-lock"></i> 
                                                            </span>
                                                        </div>
                                                        <div class="form-group">
                                                            <span class="input-icon">
                                                                <input type="password" class="form-control" name="password_again" placeholder="Password Again" required>
                                                                <i class="fa fa-lock"></i> 
                                                            </span>
                                                        </div>
                                                        <div class="form-group">
                                                            <div class="checkbox clip-check check-primary">
                                                                <input type="checkbox" id="agree" value="agree" required>
                                                                <label for="agree">I agree to the terms and conditions</label>
                                                            </div>
                                                        </div>
                                                        <div class="form-actions">
                                                            <button type="submit" class="btn btn-primary pull-right" id="submit" name="submit">
                                                                Submit <i class="fa fa-arrow-circle-right"></i>
                                                            </button>
                                                        </div>
                                                    </fieldset>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php include('include/footer.php');?>
            <?php include('include/setting.php');?>
        </div>
        
        <script src="vendor/jquery/jquery.min.js"></script>
        <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
        <script src="vendor/modernizr/modernizr.js"></script>
        <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
        <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="vendor/switchery/switchery.min.js"></script>
        <script src="vendor/jquery-validation/jquery.validate.min.js"></script>
        <script src="assets/js/main.js"></script>
        
        <script>
            jQuery(document).ready(function() {
                Main.init();
            });
        </script>
    </body>
</html>