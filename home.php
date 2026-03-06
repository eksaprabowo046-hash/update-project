 <?php
// AJAX: Ganti Password (tanpa login)
if (isset($_POST['ajax_ganti_pwd'])) {
    header('Content-Type: application/json');
    include "dbase.php";
    $iduser = trim($_POST['iduser'] ?? '');
    $old_pwd = $_POST['old_pwd'] ?? '';
    $new_pwd = $_POST['new_pwd'] ?? '';
    
    if (empty($iduser) || empty($old_pwd) || empty($new_pwd)) {
        echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi!']);
        exit;
    }
    try {
        $q = $conn->prepare("SELECT passwd FROM ruser WHERE iduser = :iduser");
        $q->execute([':iduser' => $iduser]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            echo json_encode(['success' => false, 'message' => 'ID User tidak ditemukan!']);
            exit;
        }
        if ($row['passwd'] !== $old_pwd) {
            echo json_encode(['success' => false, 'message' => 'Password lama salah!']);
            exit;
        }
        $stmt = $conn->prepare("UPDATE ruser SET passwd = :pwd WHERE iduser = :iduser");
        $stmt->execute([':pwd' => $new_pwd, ':iduser' => $iduser]);
        echo json_encode(['success' => true, 'message' => 'Password berhasil diubah.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

session_start();
$pesankehadiran = $_SESSION['DEFAULT_PESAN'];

function issudahhadir() {
  session_start();
  $iduser=$_SESSION['DEFAULT_IDUSER'];
  require('dbase.php');
  $tanggal =  date("Y-m-d");
  $hadir = ''; 
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = $conn->prepare("select iduser, tanggal, hadir,pulang  from tkehadiran where iduser= '$iduser' and tanggal='$tanggal'");
  $sql->execute();  
  if ($sql->rowCount() > 0){ 
    $rs = $sql->fetch();
    $hadir = $rs['hadir'];
  }    
  return $hadir;
}

function issudahpulang() {
  session_start();
  $iduser=$_SESSION['DEFAULT_IDUSER'];
  require('dbase.php');
  $tanggal =  date("Y-m-d");
  $pulang = ''; 
  $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $sql = $conn->prepare("select iduser, tanggal, hadir,pulang  from tkehadiran where iduser= '$iduser' and tanggal='$tanggal'");
  $sql->execute();  
  if ($sql->rowCount() > 0){ 
    $rs = $sql->fetch();
    $pulang = $rs['pulang'];
  }    
  return $pulang;
}
 
 ?>
<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
<style>
.toggle-pwd {
    cursor: pointer;
    background: transparent;
    border: 1px solid #ccc;
    border-left: none;
    padding: 6px 10px;
    border-radius: 0 4px 4px 0;
    color: #888;
    display: flex;
    align-items: center;
}
.toggle-pwd:hover { color: #337ab7; background: transparent; }
.pwd-group { display: flex; }
.pwd-group .form-control { border-radius: 4px 0 0 4px; border-right: none; }
/* Hide browser native password toggle (Edge/Chrome) */
input[type="password"]::-ms-reveal,
input[type="password"]::-webkit-credentials-auto-fill-button { display: none; }
</style>
</head>

<script language="JavaScript" type="text/JavaScript">
<!--
function cekinputlogin(ciduser,cpassword)
{ 
	var numaric = ciduser;
	if ((ciduser=="") || (cpassword=="")   )
	{    
		alert("Id User dan Password tidak boleh kosong.")
		return false
	}
	for(var j=0; j<numaric.length; j++)
		{
		  var alphaa = numaric.charAt(j);
		  var hh = alphaa.charCodeAt(0);
		  if((hh > 47 && hh<59) || (hh > 64 && hh<91) || (hh > 96 && hh<123))
		  {
		  }
		else	{
		    alert("Karakter masukan ID User yang diizinkan  A-Z, a-z, 0-9 ");
			 return false;
		  }
		}
}
	
//-->
</script>

 
<script type="text/javascript"> 
//Mematikan event enter untuk submit
function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
} 
document.onkeypress = stopRKey; 

function panggil(elem){
	var lain = new Array('ciduser','cpassword');
	for (i=0;i<lain.length;i++) {
		if (lain[i] == elem) {
			document.getElementById(elem).style.background='#4cff15';
		} else {
			document.getElementById(lain[i]).style.background='#FFFFFF';
		}
	}
}
</script>

<?php
require('bacaip.php');
?>

<body onLoad="document.frmlogin.ciduser.focus()" >
<table background="index_files/bgentri.jpg" width="100%"  >

  <tr> 
    <td height="400" valign="top" class="clsText"> 
      <table class="clsText" width="100%">
        <tr> 
           
          <td width="100%" colspan="2"> 
            <div align="center">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
              <?php
		  	  //Awal Login
			  if ( islogin() != true){


	    	  	?>
              	<!-- AWAL LOGIN BARU-->
			  	<form name="frmlogin"  method="POST" 
			  	onSubmit="return cekinputlogin(this.ciduser.value,this.cpassword.value)" action="cekuser.php">
				 <br><br><br>
				 <?php if (isset($_GET['err']) && $_GET['err'] == 'active') { ?>
				 <div style="background-color: #f2dede; color: #a94442; border: 1px solid #ebccd1; padding: 10px 15px; margin-bottom: 10px; border-radius: 4px; text-align: center; max-width: 330px;">
				   <strong>Login Gagal!</strong> Akun Anda tidak aktif.
				 </div>
				 <?php } ?>
				 <br>
			  	<table bordercolor="#333333" border="1" cellpadding="0" cellspacing="0" >
			   	<tr>
			      <td width="330"     > 
				    
                    <table background="index_files/bgentrikecil.jpg"   width="100%" border="0"   >
                        <tr> 
                        <td width="20" valign=bottom  class=clsText> </td>
                        <td width="288"></td>
                        <td width="8"></td>
                      </tr>
                      <tr> 
                        <td valign=bottom  class=clsText><font color="#333333">&nbsp;</font></td>
                          <td colspan="2"><font color="#333333" size="2,0pt" face="Arial">&nbsp;<strong>Login. 
                            </strong></font></td>
                      </tr>
                      <tr> 
                        <td valign=bottom  class=clsText><font color="#333333">&nbsp;</font></td>
                        <td><hr></td>
                        <td><font color="#333333">&nbsp;</font></td>
                      </tr>
                      <tr > 
                        <td height="125" valign=bottom  class=clsText ><font color="#333333">&nbsp;&nbsp;&nbsp;&nbsp; 
                          </font></td>
                        <td><font color="#333333" size="2,0pt" face="Arial"> 
                          <!-- Awal Isi -->
						   
                            <table width="284" cellpadding="0" >
                              <tr > 
                                <div class="form-group">
                                  <label for="usr">ID User:</label>  
                                  <input tabindex="1" maxlength="9" size=20 id="ciduser" class="form-control" name=ciduser onFocus="panggil('ciduser')" onKeyPress="if (event.keyCode==13) {cpassword.focus()}">
                                </div>
                                <div class="form-group">
                                  <label for="usr">Password:</label>
                                  <div class="pwd-group">
                                    <input tabindex="2" maxlength="15" size=20 id="cpassword" class="form-control" name=cpassword type="password" onFocus="panggil('cpassword')" onKeyPress="if (event.keyCode==13) {lSubmit.focus()}" >
                                    <span class="toggle-pwd" onclick="togglePassword()" title="Lihat password">
                                      <i class="fa fa-eye" id="eyeIcon"></i>
                                    </span>
                                  </div>
                                </div>
                                <div class="form-group">
                                  <input tabindex="3" type="submit" name="lSubmit2" value="Masuk" class="btn btn-primary">   
                                </div>
                                <div style="text-align:right; margin-top:-5px;">
                                  <a href="javascript:void(0)" onclick="showGantiPwd()" style="font-size:12px; color:#337ab7;">Ganti Password</a>
                                </div>
                              </tr>
                                
                            </table>
                          <!-- Akhir Isi -->
                          </font></td>
                        <td>&nbsp;</td>
                      </tr>
                      <tr> 
                        <td valign=bottom  class=clsText><font color="#333333">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</font></td>
                        <td><font color="#333333" size="2,0pt" face="Arial">&nbsp;  
                          </font><font color="#333333">&nbsp;&nbsp;</font><font color="#333333" size="2,0pt" face="Arial">&nbsp; 
                          </font><font color="#333333">&nbsp;&nbsp;</font><font color="#333333" size="2,0pt" face="Arial">&nbsp; 
                          </font><font color="#333333">&nbsp;&nbsp;</font><font color="#333333" size="2,0pt" face="Arial">&nbsp; 
                          </font><font color="#333333">&nbsp;&nbsp;</font><font color="#333333" size="2,0pt" face="Arial">&nbsp; 
                          </font><font color="#333333">&nbsp;</font><font color="#333333" size="2,0pt" face="Arial">&nbsp; 
                           </font><font color="#333333">&nbsp; </font></td>
                        <td><font color="#333333">&nbsp; </font></td>
                      </tr>
                    </table>
				</td>
			   </tr>
			 </table>
			  </form> 
			  <!-- AKHIR LOGIN BARU-->
        <?php }?>
              <form name="kehadiran" method="POST" action="kehadiran.php">
                <?php 
                
                //Brojo 2024-04-25
                //CEK IP KANTOR DI DATABASE 
                //JIKA TIDAK ADA DI DATABASE. MAKA ADMIN HARUS REFRESH IP, 
                //SIAPA TAHU KARRENA ADA REFRESH IP DINAMIK
                // $nmkantor = 'DSI1';
                // $noip = ''; 
                // $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                // $sql = $conn->prepare("select noip  from tipkantor where nmkantor= '$nmkantor'");
                // $sql->execute();  
                // if ($sql->rowCount() > 0){ 
                //     $rs = $sql->fetch();
                //     $noip = $rs['noip'];
                // } 
                  
                if (islogin()==true){
                    ?>
                    <div id="message"></div>

                    <script>
                        function success(position) {
                            const latitude = position.coords.latitude;
                            const longitude = position.coords.longitude;
                
                            // Kirim data lokasi ke server untuk diperiksa
                            fetch('check_location.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                                body: JSON.stringify({ latitude: latitude, longitude: longitude })
                            })
                            .then(response => response.text())
                            .then(data => {
                                document.getElementById('message').innerHTML = data;
                            })
                            .catch(error => {
                                console.error('Error:', error);
                            });
                        }
                
                        function error() {
                            document.getElementById('message').innerText = 'Unable to retrieve your location';
                        }
                
                        if (navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(success, error);
                        } else {
                            document.getElementById('message').innerText = 'Geolocation is not supported by your browser';
                        }
                    </script>
                    <?php
                    
                    echo $pesankehadiran . "<br><br>"; 
                    $_SESSION['DEFAULT_PESAN'] =""; 
                    echo "<img src='Logo.jpg'/>";
                }
                
                
                //JIKA ADMIN BISA REFRESH IP 
                if ($kodjab==1) {
                    ?>
                    <br>
                    <br>
                    <input type=submit name=submit value=REFRESH_IP class="btn btn-primary"> 
                    <?php
                }
                  
			          ?>
              </form> 
            </div></td> 
        </tr>
      </table></td>
  </tr>
</table>

<!-- Modal Ganti Password -->
<div id="modalGantiPwd" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999;">
  <div style="background:#fff; margin:8% auto; padding:25px; border-radius:8px; width:360px; box-shadow:0 4px 20px rgba(0,0,0,0.3);">
    <h4 style="margin:0 0 15px 0;"><i class="fa fa-key"></i> Ganti Password</h4>
    <div id="gantiPwdMsg"></div>
    <div class="form-group">
      <label>ID User</label>
      <input type="text" id="gp_iduser" class="form-control" maxlength="9" placeholder="Masukkan ID User">
    </div>
    <div class="form-group">
      <label>Password Lama</label>
      <div class="pwd-group">
        <input type="password" id="gp_old" class="form-control" maxlength="15">
        <span class="toggle-pwd" onclick="togglePwdField('gp_old', this)"><i class="fa fa-eye"></i></span>
      </div>
    </div>
    <div class="form-group">
      <label>Password Baru</label>
      <div class="pwd-group">
        <input type="password" id="gp_new" class="form-control" maxlength="15">
        <span class="toggle-pwd" onclick="togglePwdField('gp_new', this)"><i class="fa fa-eye"></i></span>
      </div>
    </div>
    <div class="form-group">
      <label>Konfirmasi Password Baru</label>
      <div class="pwd-group">
        <input type="password" id="gp_confirm" class="form-control" maxlength="15">
        <span class="toggle-pwd" onclick="togglePwdField('gp_confirm', this)"><i class="fa fa-eye"></i></span>
      </div>
    </div>
    <button class="btn btn-primary" onclick="submitGantiPwd()">Simpan</button>
    <button class="btn btn-default" onclick="closeGantiPwd()">Batal</button>
  </div>
</div>

<script>
function togglePassword() {
    var pwd = document.getElementById('cpassword');
    var icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'fa fa-eye';
    }
}

function togglePwdField(fieldId, btn) {
    var f = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (f.type === 'password') {
        f.type = 'text';
        icon.className = 'fa fa-eye-slash';
    } else {
        f.type = 'password';
        icon.className = 'fa fa-eye';
    }
}
function showGantiPwd() {
    document.getElementById('modalGantiPwd').style.display = 'block';
    document.getElementById('gantiPwdMsg').innerHTML = '';
    document.getElementById('gp_iduser').value = document.getElementById('ciduser').value || '';
    document.getElementById('gp_old').value = '';
    document.getElementById('gp_new').value = '';
    document.getElementById('gp_confirm').value = '';
}

function closeGantiPwd() {
    document.getElementById('modalGantiPwd').style.display = 'none';
}

function submitGantiPwd() {
    var iduser = document.getElementById('gp_iduser').value.trim();
    var oldPwd = document.getElementById('gp_old').value;
    var newPwd = document.getElementById('gp_new').value;
    var confirm = document.getElementById('gp_confirm').value;
    var msg = document.getElementById('gantiPwdMsg');
    
    if (!iduser || !oldPwd || !newPwd || !confirm) {
        msg.innerHTML = '<div style="color:red;margin-bottom:10px;">Semua field wajib diisi!</div>';
        return;
    }
    if (newPwd !== confirm) {
        msg.innerHTML = '<div style="color:red;margin-bottom:10px;">Password baru dan konfirmasi tidak cocok!</div>';
        return;
    }
    if (newPwd.length < 3) {
        msg.innerHTML = '<div style="color:red;margin-bottom:10px;">Password baru minimal 3 karakter!</div>';
        return;
    }
    
    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'home.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        var res = JSON.parse(xhr.responseText);
        if (res.success) {
            msg.innerHTML = '<div style="color:green;margin-bottom:10px;"><strong>Berhasil!</strong> ' + res.message + '</div>';
            setTimeout(function() { closeGantiPwd(); }, 2000);
        } else {
            msg.innerHTML = '<div style="color:red;margin-bottom:10px;">' + res.message + '</div>';
        }
    };
    xhr.send('ajax_ganti_pwd=1&iduser=' + encodeURIComponent(iduser) + '&old_pwd=' + encodeURIComponent(oldPwd) + '&new_pwd=' + encodeURIComponent(newPwd));
}
</script>

</body>
</html>
