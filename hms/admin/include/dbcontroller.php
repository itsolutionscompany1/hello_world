<?php
$DB_host = "localhost";
$DB_user = "medicohm_hms";
$DB_pass = "aSTERISK@1234";
$DB_name = "medicohm_hms";
try
{
 $DB_con = new PDO("mysql:host={$DB_host};dbname={$DB_name}",$DB_user,$DB_pass);
 $DB_con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch(PDOException $e)
{
 $e->getMessage();
}
?>