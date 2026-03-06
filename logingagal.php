
<html>

<head>
<meta name="GENERATOR" content="Microsoft FrontPage 5.0">
<meta name="ProgId" content="FrontPage.Editor.Document">
<meta http-equiv="Content-Type" content="text/html; charset=windows-1252">
<title>Login Validation</title>

</head>

<body>
<?php
   $pesan = "";
  if (isset($_SESSION['DEFAULT_MESSAGE'])) {
   $pesan = $_SESSION['DEFAULT_MESSAGE'];
  } 
?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" >
  <tr bgcolor="#0099CC"> 
    <td height="20" colspan="2">&nbsp;</td>
    <td width="23%" height="20"><div align="right"><a href="signout.php">Back</a></div></td>
  </tr>
  <tr> 
    <td width="76%" align="left" valign="top"> <table class="clsText" border="0" cellspacing="0" cellpadding="0" >
        <tr> 
          <td width="1" height="20">&nbsp;</td>
          <td width="565" valign="top" class="td"> <p style="margin-bottom: 0"><font color="#FF0000"><b><font size="3">Login 
              Gagal !</font></b></font></p>
            <p style="margin-top: 0"><font color="#000000" size="2" face="Verdana"><br>
              <?php
			    if (isset($_GET['err']) && $_GET['err'] == 'active') {
                    echo "<font color='red'><b>Login Gagal. Akun Anda tidak aktif !</b></font>";
                } else if (isset($_SESSION['DEFAULT_MESSAGE'])){
                    echo $_SESSION['DEFAULT_MESSAGE'];
                } else {
                    echo "Login Gagal !";
                }
			?>
              </font></p></td>
          <td width="51">&nbsp;</td>
        </tr>
      </table></td>
  </tr>
</table>
</body>

</html>