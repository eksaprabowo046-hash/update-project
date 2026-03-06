<?php
session_start();

include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER']; 


if ($kodjab!=1) {
	Header("location: index.php");
}	

if (isset($_POST['submit'])) {
	$currentPassword  = $_POST['currentPassword'];
	$newPassword      = $_POST['newPassword'];
	$confirmPassword  = $_POST['confirmPassword'];

	//Cek current password  
	$password = '';
	$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	// jalankan query
	$sql = $conn->prepare("select passwd from ruser where iduser='$iduser'");
	$sql->execute();  
	if($sql->rowCount() > 0){ 
		$rs = $sql->fetch(); 
		$password = $rs['passwd'];		
	}
	if ($password==$currentPassword){ 
	    $sqlcek = "update ruser set passwd='$newPassword'  where iduser='$iduser'"; 
		try { 
		   $qcek = $conn->prepare($sqlcek);
		   $qcek->execute();   
		   $pesan =  "<font color=blue>Ubah password sukses.</font><br>"; 
		}//try
		   catch (PDOException $e)	{
			  $pesan =  "<font color=red>Ubah password gagal.</font><br>"; 
		}//catch  
		  		
	}else {
		$pesan =  "<font color=red>Current Password salah !</font><br>";
	}

} //if isset(submit)
?>
<script>
function validatePassword() {
var currentPassword,newPassword,confirmPassword,output = true;

currentPassword = document.frmChange.currentPassword;
newPassword = document.frmChange.newPassword;
confirmPassword = document.frmChange.confirmPassword;

if(!currentPassword.value) {
currentPassword.focus();
document.getElementById("currentPassword").innerHTML = "required";
output = false;
}
else if(!newPassword.value) {
newPassword.focus();
document.getElementById("newPassword").innerHTML = "required";
output = false;
}
else if(!confirmPassword.value) {
confirmPassword.focus();
document.getElementById("confirmPassword").innerHTML = "required";
output = false;
}
if(newPassword.value != confirmPassword.value) {
newPassword.value="";
confirmPassword.value="";
newPassword.focus();
document.getElementById("confirmPassword").innerHTML = "No Match";
output = false;
}     
return output;
}
</script>

<html>
<head>
<title>Change Password</title>
<link rel="stylesheet" type="text/css" href="styles.css" />
</head>
<body>
<div class="wrapper">
<form name="index.php?par00a" method="post" action="" onSubmit="return validatePassword()">
<div style="width:500px;">
<div class="message"><?php if(isset($message)) { echo $message; } ?></div>
<table border="0" cellpadding="10" cellspacing="0" width="500" align="center" class="tblSaveForm">
<tr class="tableheader">
<td colspan="2"><h2>Ubah Password</h2> 
<h4>
		<?php  
		    $pesan = $pesan;
			echo $pesan;
			 		
		?>
		</h4>
</td>
</tr>
 
<tr>
<td width="40%"><label>Current Password</label></td>
<td width="60%"><input maxlength="15" type="password" name="currentPassword" class="txtField"/><span id="currentPassword"  class="required"></span></td>
</tr>
<tr>
<td><label>New Password</label></td>
<td><input maxlength="15" type="password" name="newPassword" class="txtField"/><span id="newPassword" class="required"></span></td>
</tr>
<td><label>Confirm Password</label></td>
<td><input maxlength="15" type="password" name="confirmPassword" class="txtField"/><span id="confirmPassword" class="required"></span></td>
</tr>
<tr>
<td></td>
<td>
<button type="submit" name="submit" class="btn btn-primary" value="Y">Submit</button>
<button type="reset" class="btn btn-danger">Reset</button> </td>
</tr>
</table>
</div>
</form>
</div>
</body>
</html>
