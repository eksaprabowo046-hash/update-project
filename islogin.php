<?php
#CEK LOKAL APAKAH KONDISI LOGIN 
if (isset($_SESSION['DEFAULT_ISLOGIN'] )){
	if ($_SESSION['DEFAULT_ISLOGIN'] != "mK&%~%h#867H4z") {
	  Header("location: index.php"); 
	  exit; 
	}
} else {
      Header("location: index.php"); 
	  exit; 
}
  
?>
