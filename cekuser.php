<?php
    include "dbase.php"; 
	session_start();
	
    $ciduser   = ($_POST['ciduser']);
	$cpassword = ($_POST['cpassword']);
	
 
	try {  
			$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			// jalankan query
			$sql = $conn->prepare("select iduser,  nama, passwd, kodjab, stsaktif  from ruser where iduser= '$ciduser' and passwd='$cpassword'");
			$sql->execute();  
            if($sql->rowCount() > 0){ 
			    $rs = $sql->fetch();

				// Cek Status Aktif
				if (isset($rs['stsaktif']) && $rs['stsaktif'] != 1) {
					$_SESSION['DEFAULT_IDUSER'] 	= "";
					$_SESSION['DEFAULT_USERNAME']	= ""; 
					$_SESSION['DEFAULT_TIMESTAMP']	= time(); 
					$_SESSION['DEFAULT_ISLOGIN']	= "";
					$_SESSION['DEFAULT_STSUSER']	= "";
					session_write_close();
					Header("location: index.php?err=active");
					exit;
				}


				//Session in jika user dan hak akses benar
				$_SESSION['DEFAULT_IDUSER'] 	= strtoupper($ciduser);
				$_SESSION['DEFAULT_USERNAME']	= $rs['nama'];
				$_SESSION['DEFAULT_TIMESTAMP']	= time(); 
				$_SESSION['DEFAULT_ISLOGIN']	= "mK&%~%h#867H4z";
				if ($rs['kodjab']==0) {
			   		$_SESSION['DEFAULT_STSUSER']	= "A"; 
				}else {
			   		$_SESSION['DEFAULT_STSUSER']	= "V"; 
				}  
				$_SESSION['DEFAULT_KODJAB']	= $rs['kodjab'];  
				Header("location: index.php");
			    
			}else {
			    $_SESSION['DEFAULT_IDUSER'] 	= "";
				$_SESSION['DEFAULT_USERNAME']	= ""; 
				$_SESSION['DEFAULT_TIMESTAMP']	= time(); 
				$_SESSION['DEFAULT_ISLOGIN']	= "";
				$_SESSION['DEFAULT_STSUSER']	= "";
				$_SESSION['DEFAULT_MESSAGE']    = "Id User dan Password tidak valid !";	
				$_SESSION['DEFAULT_KODJAB']	= ""; 					
				Header("location: index.php?par=x1b"); 
			}
			
			 
	   }//try
	   catch (PDOException $e)	{
		  echo " error !";
	 
	  }//catch
	
	//matikan koneksi  
	$conn=null;    
?>