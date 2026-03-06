<?php
session_start();
if (!isset($_SESSION['DEFAULT_ISLOGIN'])) {  
  //Header("location: login.php"); 
 // exit;
} 

$servername = "localhost";
$username = "root";
$password = "dsi^*000";
$dbname = "klikdsic_mmdsidb";
  

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
   // echo "Connected successfully"; 
    }
catch(PDOException $e)
    {
    echo "Connection failed: " . $e->getMessage();
    }
 

 
//webtitle 
$webtitle ="mmDSI"; 
?>
