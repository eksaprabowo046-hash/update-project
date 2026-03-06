<?php 
session_start();
 
   
//Destroy session
unset($_SESSION['DEFAULT_IDUSER']); 
unset($_SESSION['DEFAULT_USERNAME']);
unset($_SESSION['DEFAULT_TIMESTAMP']); 
unset($_SESSION['DEFAULT_ISLOGIN']);
unset($_SESSION['DEFAULT_STSUSER']); 			
session_destroy(); 


 Header("location: index.php");
 	 
 
?>
 