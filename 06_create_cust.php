<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
 
$iduser = $_SESSION['DEFAULT_IDUSER'];  

function left($str, $length) {
    return substr($str, 0, $length);
} 

// INSERT
if (isset($_GET['ins'])) {   
    $kodcustomer = trim($_GET['kodcustomer']); 
    $nmcustomer = trim($_GET['nmcustomer']); 
    $almtcustomer = $_GET['almtcustomer']; 
    $iduser = $_SESSION['DEFAULT_IDUSER']; 
    
    $sql = "insert into rcustomer (iduser,kodcustomer,almtcustomer,nmcustomer,status) "; 
    $sql = $sql . "values ('$iduser','$kodcustomer','$almtcustomer','$nmcustomer',1)"; 
    
    try { 
        $qins = $conn->prepare($sql);
        $qins->execute(); 
        $kodbrgpesan = $kodcustomer;
        $pesan = "<font color=blue>New record <strong> ".$kodbrgpesan."  </strong> created successfully</font>"; 
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error insert <strong> ".$kodbrgpesan." </strong>. Make sure data is correct.</font>"; 
    }
}

// UPDATE
if (isset($_GET['upd'])) {
    $kodcustomer = trim($_GET['kodcustomer']); 
    $nmcustomer = trim($_GET['nmcustomer']); 
    $almtcustomer = $_GET['almtcustomer']; 
    $status = $_GET['status'];
    
    $sql = "UPDATE rcustomer SET nmcustomer='$nmcustomer', almtcustomer='$almtcustomer', status='$status' WHERE kodcustomer='$kodcustomer'";
    
    try { 
        $qupd = $conn->prepare($sql);
        $qupd->execute(); 
        $pesan = "<font color=blue>Record <strong> ".$kodcustomer."  </strong> updated successfully</font>"; 
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error update <strong> ".$kodcustomer." </strong>. Make sure data is correct.</font>"; 
    }
}

// DELETE 
if (isset($_GET['del'])) {
    $kodcustomer = trim($_GET['kodcustomer']);
    $istidakbolehdel = 0;	  
    $sqlcek = "select * from tlog WHERE kodcustomer='$kodcustomer'"; 
    
    try { 
        $qcek = $conn->prepare($sqlcek);
        $qcek->execute(); 
        if($qcek->rowCount() > 0){ 
            $istidakbolehdel = 1;
            $pesan = "<font color=red>Customer <strong>".$kodcustomer."</strong> has been used in Log Table.</font><br>";
            $pesan = $pesan."<font color=red>Tidak boleh delete yang sudah ada lognya</font>";
        }				
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error checking. Make sure <strong>".$kodcustomer."</strong> is correct.</font>";
    }
    
    if ($istidakbolehdel==0){
        $sqld = "DELETE FROM rcustomer WHERE kodcustomer='$kodcustomer'";
        
        try { 
            $qdel = $conn->prepare($sqld);
            $qdel->execute();
            $pesan = "<font color=blue>One record <strong>".$kodcustomer."</strong> deleted successfully</font>"; 
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error delete. Make sure <strong>".$kodcustomer."</strong> is correct.</font>";
        }
    }	
}	

// GET DATA FOR EDIT
$edit_mode = false;
$edit_data = array();
if (isset($_GET['edit'])) {
    $edit_mode = true;
    $kodcustomer_edit = trim($_GET['edit']);
    
    try {
        $sql_edit = $conn->prepare("SELECT * FROM rcustomer WHERE kodcustomer=?");
        $sql_edit->execute([$kodcustomer_edit]);
        $edit_data = $sql_edit->fetch();
    } catch (PDOException $e) {
        $pesan = "<font color=red>Error loading data for edit.</font>";
    }
}

?>	 

<script type="text/javascript">
$(document).ready(function(){
    $('.search-box-kategori input[type="text"]').on("keyup input", function(){
        var inputVal = $(this).val();
        var resultDropdown = $(this).siblings(".result");
        if(inputVal.length){
            $.get("01a_search_kategori.php", {term: inputVal}).done(function(data){
                resultDropdown.html(data);
            });
        } else{
            resultDropdown.empty();
        }
    });
    
    $(document).on("click", ".result p", function(){
        $(this).parents(".search-box-kategori").find('input[type="text"]').val($(this).text());
        $(this).parent(".result").empty();
    });
});
</script>

<script type="text/javascript"> 
function stopRKey(evt) { 
    var evt = (evt) ? evt : ((event) ? event : null); 
    var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
    if ((evt.keyCode == 13) && (node.type=="text")) {return false;} 
} 
document.onkeypress = stopRKey; 
</script>

<script language="JavaScript" type="text/JavaScript">
function kembali(){
    history.back();
}
</script>

<script languange="Javascript">
function pilih(id){
    location.replace("index.php?par=01&idkategori="+id);
}
</script>

<script type="text/javascript">
function validasi_input(form){
    if (form.kodcustomer.value == ""){
        alert("Kode customer masih kosong!");
        form.kodcustomer.focus();
        return (false);
    }
    if (form.nmcustomer.value == ""){
        alert("Nama customer masih kosong!");
        form.nmcustomer.focus();
        return (false);
    }
    if (form.almtcustomer.value == ""){
        alert("Alamat customer masih kosong!");
        form.almtcustomer.focus();
        return (false);
    }
    return (true);
}
</script>

<script language="javascript">
function ConfirmDelete() {
    var x = confirm("Are you sure you want to delete?");
    if (x)
        return true;
    else
        return false;
}

function CancelEdit() {
    window.location.href = "index.php?par=06";
}
</script>

<body>
 
<div class="row"> 
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i>CREATE LOG</li> 
    </ol> 
    <section class="panel">
        <header class="panel-heading">
            
            <form role="form" method="GET" onSubmit="return validasi_input(this)" action="index.php">  
                
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
                    <label>Kode Customer</label>	
                    <div class="search-box-material">
                        <input name="kodcustomer" 
                               onKeyUp="this.value = this.value.toUpperCase();" 
                               class="form-control" 
                               id="kodcustomer" 
                               type="text" 
                               step="any" 
                               autocomplete="off" 
                               placeholder="Tulis dengan singkatan customer ..." 
                               value="<?php echo $edit_mode ? $edit_data['kodcustomer'] : ''; ?>"
                               <?php echo $edit_mode ? 'readonly' : ''; ?>>
                        <div class="result1"></div>   
                    </div>
                </div>	 
                
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
                    <label>Nama Customer</label>	
                    <div class="search-box-material">
                        <input name="nmcustomer" 
                               class="form-control" 
                               id="nmcustomer" 
                               type="text" 
                               step="any" 
                               autocomplete="off" 
                               placeholder="Nama customer ..." 
                               value="<?php echo $edit_mode ? $edit_data['nmcustomer'] : ''; ?>">
                        <div class="result1"></div>   
                    </div>
                </div>	
                
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
                    <label>Alamat Customer</label>	
                    <div class="search-box-material">
                        <input name="almtcustomer" 
                               class="form-control" 
                               id="almtcustomer" 
                               type="text" 
                               step="any" 
                               autocomplete="off" 
                               placeholder="Alamat customer ..." 
                               value="<?php echo $edit_mode ? $edit_data['almtcustomer'] : ''; ?>">
                        <div class="result1"></div>   
                    </div>
                </div>	
                
                <?php if ($edit_mode) { ?>
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
                    <label>Status</label>	
                    <select name="status" class="form-control" id="status">
                        <option value="1" <?php echo ($edit_data['status'] == 1) ? 'selected' : ''; ?>>Aktif</option>
                        <option value="0" <?php echo ($edit_data['status'] == 0) ? 'selected' : ''; ?>>Non-Aktif</option>
                    </select>
                </div>
                <?php } ?>
                
                <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6"> 
                    <input type="hidden" name="par" id="par" value="06">
                    <?php if ($edit_mode) { ?>
                        <input type="hidden" name="upd" id="upd" value="Y">
                        <button type="submit" name="submit" class="btn btn-primary" value="Y">Update Data</button>
                        <button type="button" class="btn btn-danger" onclick="CancelEdit()">Cancel</button>
                    <?php } else { ?>
                        <input type="hidden" name="ins" id="ins" value="Y">
                        <button type="submit" name="submit" class="btn btn-primary" value="Y">Insert Data</button>
                        <button type="reset" class="btn btn-danger">Reset</button>
                    <?php } ?>
                </div> 
                
            </form>
            <div class="clearfix"></div>
            
            <h4><font color="red"><?php echo $pesan;?></font></h4>
        </header>
        <section class="content">
            <div align="center">Customer</div>
            <div class="box-body">
                <table id="contoh" class="table table-bordered table-striped table-hover"> 
                    <thead> 
                        <tr class="height: 5px;">
                            <th>#</th>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Alamat</th>
                            <th>Status</th>
                            <th>Aksi</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <div id="show-product"></div>
                        <?php 
                        $tglsekarang = date('Y-m-d'); 
                        try { 
                            $sql = $conn->prepare("select * from rcustomer order by nmcustomer"); 
                            $sql->execute();	 				
                            $no=1;
                            while($rs = $sql->fetch()) { 
                                echo "<tr>
                                    <td align=center>".$no."</td>
                                    <td>".$rs['kodcustomer']."</td>
                                    <td>".$rs['nmcustomer']."</td>
                                    <td>".$rs['almtcustomer']."</td>
                                    <td align=center>".($rs['status'] == 1 ? '<span class="label label-success">Aktif</span>' : '<span class="label label-danger">Non-Aktif</span>')."</td>
                                    <td align=center>
                                        <a href='index.php?par=06&edit=".$rs['kodcustomer']."' class='btn btn-warning btn-xs'>Edit</a>
                                        <form method='GET' action='index.php' style='display:inline;'>
                                            <input type='hidden' name='kodcustomer' value='".$rs['kodcustomer']."'>  
                                            <input type='hidden' name='par' value='06'>
                                            <input type='hidden' name='del' value='Y'>
                                            <button type='submit' class='btn btn-danger btn-xs' value='Y' Onclick='return ConfirmDelete();' ";
                                            if ($iduser != $rs['iduser']){
                                                echo "disabled='disabled'";
                                            }
                                            echo ">Delete</button>     
                                        </form> 
                                    </td> 
                                </tr>";
                                $no++;	
                            } 
                        } catch (PDOException $e) {
                            echo "<tr>
                                <td colspan='6' align='center'>No data available</td>
                            </tr>";
                        }
                        ?> 
                    </tbody>
                </table>
            </div>
        </section>
    </section>
</div> 
 
</body>

</html>