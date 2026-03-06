<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 
$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0; 

$is_edit_mode = false;
$edit_data = null;
$id_to_edit = 0;

if (isset($_GET['edit']) && $_GET['edit'] == 'Y' && isset($_GET['id'])) {
    
    if ($kodjab != 1) {
        $pesan = "<font color=red>Anda tidak memiliki hak akses untuk mengedit data ini. Hanya user dengan kodjab 1 yang diizinkan.</font>";
        $is_edit_mode = false;
    } else {
        $id_to_edit = (int)$_GET['id'];
        
        $sql_edit = "SELECT * FROM timplementasi WHERE id = :id AND stsdel = 0"; 
        try {
            $q_edit = $conn->prepare($sql_edit);
            $q_edit->bindParam(':id', $id_to_edit, PDO::PARAM_INT);
            $q_edit->execute();
            
            if ($q_edit->rowCount() > 0) {
                $is_edit_mode = true;
                $edit_data = $q_edit->fetch(PDO::FETCH_ASSOC);
            } else {
                $pesan = "<font color=red>Data tidak ditemukan.</font>";
            }
        } catch (PDOException $e) {
            $pesan = "<font color=red>Error fetching data for edit: " . $e->getMessage() . "</font>";
        }
    }
}

if (isset($_POST['update'])) {

    // Cek hak akses kodjab
    if ($kodjab != 1) {
         $pesan =  "<font color=red>Error update. Anda tidak memiliki hak akses. Hanya user dengan kodjab 1 yang diizinkan.</font>" ;
    } else {
        // User diizinkan, lanjutkan proses update
        $id_edit       = (int)$_POST['id_edit'];
        $kodcustomer   = trim($_POST['kodcustomer']);
        $dikerjakan	 = $_POST['dikerjakan'];
        $prioritas     = $_POST['prioritas'];
        
        $aktivitas_list  = $_POST['aktivitas'];
        $tglmulai_list   = $_POST['tglmulai'];
        $tglselesai_list = $_POST['tglselesai'];
        
        if (empty($id_edit) || empty($aktivitas_list[0]) || empty($kodcustomer)) {
            $pesan =  "<font color=red>Error update. Data tidak lengkap (Customer, Aktivitas pertama).</font>" ;
            $is_edit_mode = true;
            $edit_data = [
                'id' => $id_edit,
                'kodcustomer' => $kodcustomer,
                'aktivitas' => $aktivitas_list[0] ?? '',
                'tglmulai' => $tglmulai_list[0] ?? '',
                'tglselesai' => $tglselesai_list[0] ?? null,
                'userorder' => $dikerjakan,
                'userpj' => $prioritas
            ];
        } else {
            
            $conn->beginTransaction();
            
            try {
                $aktivitas   = $aktivitas_list[0];
                $tglmulai    = $tglmulai_list[0];
                $tglselesai  = !empty($tglselesai_list[0]) ? $tglselesai_list[0] : null;

                $sql_upd = "UPDATE timplementasi SET 
                                kodcustomer = :kodcustomer, 
                                aktivitas = :aktivitas, 
                                tglmulai = :tglmulai, 
                                tglselesai = :tglselesai, 
                                userorder = :dikerjakan, 
                                userpj = :prioritas
                            WHERE 
                                id = :id";
                
                $q_upd = $conn->prepare($sql_upd);
                $q_upd->bindParam(':kodcustomer', $kodcustomer);
                $q_upd->bindParam(':aktivitas', $aktivitas);
                $q_upd->bindParam(':tglmulai', $tglmulai);
                $q_upd->bindParam(':tglselesai', $tglselesai);
                $q_upd->bindParam(':dikerjakan', $dikerjakan);
                $q_upd->bindParam(':prioritas', $prioritas);
                $q_upd->bindParam(':id', $id_edit, PDO::PARAM_INT);
                $q_upd->execute();
                
                // 2. INSERT data baru (index [1] dst.)
                $sql_ins = "INSERT INTO timplementasi (iduser, kodcustomer, aktivitas, tglmulai, tglselesai, userorder, userpj) 
                            VALUES (:iduser, :kodcustomer, :aktivitas, :tglmulai, :tglselesai, :dikerjakan, :prioritas)";
                $q_ins = $conn->prepare($sql_ins);

                // Loop mulai dari index 1 (aktivitas baru)
                for ($i = 1; $i < count($aktivitas_list); $i++) {
                    $new_aktivitas = $aktivitas_list[$i];
                    if (!empty($new_aktivitas)) {
                        $new_tglmulai   = $tglmulai_list[$i];
                        $new_tglselesai = !empty($tglselesai_list[$i]) ? $tglselesai_list[$i] : null;

                        $q_ins->bindParam(':iduser', $iduser); // $iduser dari session
                        $q_ins->bindParam(':kodcustomer', $kodcustomer);
                        $q_ins->bindParam(':aktivitas', $new_aktivitas);
                        $q_ins->bindParam(':tglmulai', $new_tglmulai);
                        $q_ins->bindParam(':tglselesai', $new_tglselesai);
                        $q_ins->bindParam(':dikerjakan', $dikerjakan);
                        $q_ins->bindParam(':prioritas', $prioritas);
                        $q_ins->execute();
                    }
                }
                
                $conn->commit();
                
                $pesan = "<font color=blue>Record updated successfully (termasuk aktivitas baru jika ada)</font>"; 
                $is_edit_mode = false; 
                
            } catch (PDOException $e) {
                $conn->rollBack();
                $pesan =  "<font color=red>Error update. " . $e->getMessage() . "</font>" ; 
                $is_edit_mode = true;
                $edit_data = [
                    'id' => $id_edit,
                    'kodcustomer' => $kodcustomer,
                    'aktivitas' => $aktivitas_list[0] ?? '',
                    'tglmulai' => $tglmulai_list[0] ?? '',
                    'tglselesai' => $tglselesai_list[0] ?? null,
                    'userorder' => $dikerjakan,
                    'userpj' => $prioritas
                ];
            }
        }
    } // akhir dari cek kodjab
}

if (isset($_POST['ins'])) {
   $kodcustomer   = trim($_POST['kodcustomer']);
   $dikerjakan	 = $_POST['dikerjakan'];
   $prioritas = $_POST['prioritas'];
   
   $aktivitas_list = $_POST['aktivitas'];
   $tglmulai_list  = $_POST['tglmulai'];
   $tglselesai_list = $_POST['tglselesai'];
   
   $conn->beginTransaction();

   try { 
       $sql = "INSERT INTO timplementasi (iduser, kodcustomer, aktivitas, tglmulai, tglselesai, userorder, userpj) 
               VALUES (:iduser, :kodcustomer, :aktivitas, :tglmulai, :tglselesai, :dikerjakan, :prioritas)";
       $qins = $conn->prepare($sql);

       foreach ($aktivitas_list as $i => $aktivitas) {
           if (!empty($aktivitas)) {
               $tglmulai   = $tglmulai_list[$i];
               $tglselesai = !empty($tglselesai_list[$i]) ? $tglselesai_list[$i] : null;
               
               $qins->bindParam(':iduser', $iduser);
               $qins->bindParam(':kodcustomer', $kodcustomer);
               $qins->bindParam(':aktivitas', $aktivitas);
               $qins->bindParam(':tglmulai', $tglmulai);
               $qins->bindParam(':tglselesai', $tglselesai);
               $qins->bindParam(':dikerjakan', $dikerjakan);
               $qins->bindParam(':prioritas', $prioritas);
     
               $qins->execute(); 
           }
       }
       
       $conn->commit();
       $pesan = "<font color=blue>New record(s) created successfully</font>"; 

	}//try
	   catch (PDOException $e)	{
          $conn->rollBack();
		  $pesan =  "<font color=red>Error insert. Make sure data is correct. Error: " . $e->getMessage() . "</font>" ; 
    }//catch
}
   

if (isset($_GET['del'])) {
   $idlog = trim($_GET['id']);	 
   
   $istidakbolehdel = 0;	  
   $sqlcek = "SELECT * FROM timplementasi WHERE id= :idlog  AND iduser= :iduser"; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
       $qcek->bindParam(':idlog', $idlog, PDO::PARAM_INT);
       $qcek->bindParam(':iduser', $iduser);
	   $qcek->execute(); 
       
	   if($qcek->rowCount() < 1){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>Implementasi <strong>".$idlog." </strong> bukan milik user <strong>".$iduser." </strong></font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete yang bukan miliknya</font>";
	   }				
	    
	}//try
	   catch (PDOException $e)	{
		  $pesan =  "<font color=red>Error checking. " . $e->getMessage() . "</font>" ;
	 
	}//catch 
    
	if ($istidakbolehdel == 0){
	   $sqld = "UPDATE timplementasi SET stsdel=1 WHERE id= :idlog"; 
	   try { 
		   $qdel = $conn->prepare($sqld);
           $qdel->bindParam(':idlog', $idlog, PDO::PARAM_INT);
		   $qdel->execute();
		    
		   $pesan = "<font color=blue>One record deleted successfully</font>"; 
		}//try
		   catch (PDOException $e)	{
			  $pesan =  "<font color=red>Error delete. " . $e->getMessage() . "</font>" ;
		 
		}//catch 
	}	
	 
}	

if (isset($_GET['para'])) { 
  $pesan = $_GET['pesan'];
}

?>	 

<body >
 
<div class="row"> 
    <ol class="breadcrumb">
      <li><i class="fa fa-home"></i><?php echo $is_edit_mode ? 'EDIT IMPLEMENTASI' : 'CREATE IMPLEMENTASI'; ?></li> 
	</ol> 
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form"  method="POST" action="index.php?par=31"> 
			 
			<div class="row">
				<div class="col-xs-12 col-md-6">
				  <div class="form-group">
					  <label>Customer / Mitra</label>	
					  <?php 
					  $kodcustomer  ="";
					  if (isset($_GET['kodcustomer'])) { 
						  $kodcustomer  =$_GET['kodcustomer'];
					  }
                      if ($is_edit_mode && $edit_data) {
                          $kodcustomer = $edit_data['kodcustomer'];
                      }
					  ?>
					   <select name="kodcustomer" id="kodcustomer" class="select2 form-control"  placeholder="Customer..." required> 
						<option value="" selected disabled>-- Pilih Customer --</option>
					<?php
						$qk = $conn->prepare("SELECT * FROM rcustomer WHERE status = 1 ORDER BY kodcustomer "); 
						$qk->execute(); 
						while($rsk = $qk->fetch()){ 
							$selected = ($kodcustomer == $rsk['kodcustomer']) ? "SELECTED" : "";
							echo "<option value='".$rsk['kodcustomer']."' $selected>".$rsk['nmcustomer']."</option>\n";
						}
					?>
						</select> 
				   </div>
				 </div>	
				 
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Dikerjakan Oleh</label>	
						  <select name="dikerjakan" id="dikerjakan" class="form-control"  placeholder="Dikerjakan Oleh" > 
							<option value="" selected disabled>-- Pilih User --</option>
								<?php
                                    $selected_user_order = $iduser;
                                    if ($is_edit_mode && $edit_data) {
                                        $selected_user_order = $edit_data['userorder'];
                                    }

									$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
									$qk->execute(); 
									while($rsk = $qk->fetch()){ 
									   if ( $selected_user_order == $rsk['iduser']) {
										  echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
									   } else {
										  echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
									   }	  
									}
								?>
							</select> 
					</div>
				</div>
				<div class="col-xs-12 col-md-3">
					<div class="form-group">
						 <label>Penanggung Jawab</label>	
						  <select name="prioritas" id="prioritas" class="form-control"  placeholder="Penanggung Jawab"  > 
							<option value="" selected disabled>-- Pilih User --</option>
								<?php
                                    $selected_user_pj = $iduser;
                                    if ($is_edit_mode && $edit_data) {
                                        $selected_user_pj = $edit_data['userpj'];
                                    }

									$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
									$qk->execute(); 
									while($rsk = $qk->fetch()){ 
									   if ( $selected_user_pj == $rsk['iduser']) {
										  echo "<option value=".$rsk['iduser']." SELECTED>".$rsk['nama']."</option>\n"; 
									   } else {
										  echo "<option value=".$rsk['iduser'].">".$rsk['nama']."</option>\n"; 
									   }	  
									}
								?>
							</select> 
					</div>
				</div>
			</div>
			
			<div class="row">
                <div class="col-xs-12">
                    <label>Daftar Aktivitas</label>
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 50%;">Aktivitas</th>
                                <th style="width: 20%;">Tgl Mulai</th>
                                <th style="width: 20%;">Tgl Selesai</th>
                                <th style="width: 10%;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="activity_table_body">
                            
                            <?php if ($is_edit_mode && $edit_data): ?>
                                <tr>
                                    <td>
                                        <input type="text" name="aktivitas[]" class="form-control" required 
                                               value="<?php echo htmlspecialchars($edit_data['aktivitas']); ?>">
                                    </td>
                                    <td>
                                        <input type="date" name="tglmulai[]" class="form-control" 
                                               value="<?php echo $edit_data['tglmulai']; ?>">
                                    </td>
                                    <td>
                                        <input type="date" name="tglselesai[]" class="form-control" 
                                               value="<?php echo $edit_data['tglselesai']; ?>">
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)" disabled>
                                            <i class="fa fa-minus"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php elseif (!$is_edit_mode): // Hanya tampilkan 1 baris kosong saat mode INSERT ?>
                                <tr>
                                    <td><input type="text" name="aktivitas[]" class="form-control" placeholder="Deskripsi aktivitas..." required></td>
                                    <td><input type="date" name="tglmulai[]" class="form-control" value="<?php echo date('Y-m-d'); ?>"></td>
                                    <td><input type="date" name="tglselesai[]" class="form-control"></td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)" disabled>
                                            <i class="fa fa-minus"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                    </table>
                    
                    <button type="button" class="btn btn-success" id="add_activity_btn">
                        <i class="fa fa-plus"></i> Tambah Aktivitas
                    </button>
                    </div>
            </div>
            
            <hr>

			<div class="row">
				 <div class="col-xs-12"> 
					<div class="form-group"> 
						 <input type="hidden" name="par" id="par" value="31">
                         
                         <?php if ($is_edit_mode): ?>
                             <input type="hidden" name="update" id="update" value="Y">
                             <input type="hidden" name="id_edit" id="id_edit" value="<?php echo $id_to_edit; ?>">
                             <button type="submit" name="submit" class="btn btn-warning" value="Y">Update Data</button>
                             <a href="index.php?par=31" class="btn btn-danger">Batal</a>
                         <?php else: ?>
                             <input type="hidden" name="ins" id="ins" value="Y">
                             <button type="submit" name="submit" class="btn btn-primary" value="Y">Insert Data</button>
                             <button type="reset" class="btn btn-danger">Reset</button>
                         <?php endif; ?>
                         </div> 
				 </div>
			</div> 
          </form>
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">DAFTAR IMPLEMENTASI
	  </div>
	  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead> 
			<tr class="height: 5px;">
			  <th>#</th>
              <th>Nama Client</th>
              <th>Aktivitas</th>
              <th>Tgl Mulai</th>
              <th>Tgl Selesai</th>
			  <th>Dikerjakan Oleh</th>
			  <th>Penanggung Jawab</th> 
              <th>Aksi</th> </tr>
		  </thead>
		  <tbody>
		  <?php 
			   try { 
			        $sql = $conn->prepare("
                        SELECT 
                            t.id, t.iduser, t.aktivitas, t.tglmulai, t.tglselesai,
                            r.nmcustomer,
                            u_order.nama AS nama_dikerjakan_oleh,
                            u_pj.nama AS nama_penanggung_jawab
                        FROM 
                            timplementasi t
                        LEFT JOIN 
                            rcustomer r ON t.kodcustomer = r.kodcustomer
                        LEFT JOIN 
                            ruser u_order ON t.userorder = u_order.iduser
                        LEFT JOIN 
                            ruser u_pj ON t.userpj = u_pj.iduser
                        WHERE 
                            t.stsdel=0 
                        ORDER BY 
                            r.nmcustomer, t.tglmulai
                    "); 
					$sql->execute();	 				
					
					$no=1;
					$hasData = false;
                    $current_client = ""; 
					
                    while($rs = $sql->fetch()) { 
					    $hasData = true;
                        $client_name = $rs['nmcustomer'];
                        
                        echo "<tr>
                            <td align='center'><font size='-1'>".$no."</font></td>";
                        
                        if ($client_name != $current_client) {
                            echo "<td><font size='-1'><strong>".htmlspecialchars($client_name)."</strong></font></td>";
                            $current_client = $client_name; 
                        } else {
                            echo "<td><font size='-1'></font></td>"; 
                        }
                        
                        echo "
                            <td><font size='-1'>".htmlspecialchars($rs['aktivitas'])."</font></td> 
                            <td><font size='-1'>".date('d/m/Y', strtotime($rs['tglmulai']))."</font></td>
                            <td><font size='-1'>".($rs['tglselesai'] ? date('d/m/Y', strtotime($rs['tglselesai'])) : '-')."</font></td>
                            <td><font size='-1'>".htmlspecialchars($rs['nama_dikerjakan_oleh'])."</font></td>
                            <td><font size='-1'>".htmlspecialchars($rs['nama_penanggung_jawab'])."</font></td>
                            <td>";
					    ?>
                          
                          <?php
                            // Tombol Edit muncul jika kodjab == 1
                            if ($kodjab == 1) {
                          ?>
                                <a href="index.php?par=31&edit=Y&id=<?php echo $rs['id'];?>" 
                                   class="btn btn-warning btn-xs" 
                                   style="margin-right: 5px;">
                                   Edit
                                </a>
                          <?php
                            } else {
                                // Jika bukan kodjab=1, tombol Edit nonaktif
                                echo "<button class='btn btn-warning btn-xs' style='margin-right: 5px;' disabled>Edit</button>";
                            }
                          ?>


                          <?php 
                            // Tombol Delete hanya muncul untuk pemilik data
                            if ($iduser == $rs['iduser']) {
                          ?>
                                <form method="GET" action="index.php" style="margin: 0; display: inline-block;">
								    <input type="hidden" name="id" value="<?php echo  $rs['id'];?>">  
								    <input type="hidden" name="par" value="31">
								    <input type="hidden" name="del" value="Y">
                   				    <button type="submit" class="btn btn-danger btn-xs" onclick="return ConfirmDelete();">
                                        Delete
                                    </button>     
							    </form> 
                          <?php
                            } else {
                                // Jika bukan pemilik, tombol Delete nonaktif
                                echo "<button class='btn btn-danger btn-xs' disabled>Delete</button>";
                            }
                          ?>
                          <?php	
						   echo "</td></tr>";
						$no++;	
					}
					
					if (!$hasData) {
					    echo "<tr><td colspan='8' align='center'>Tidak ada data</td></tr>";
					}
			   }
			   catch (PDOException $e)	{
				  echo "<tr><td colspan='8' align='center'>Error: ".$e->getMessage()."</td></tr>";
			  }
			 
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<script type="text/javascript">
document.addEventListener('DOMContentLoaded', function() {
    const addBtn = document.getElementById('add_activity_btn');
    if (addBtn) { 
        addBtn.addEventListener('click', addActivityRow);
    }

    document.onkeypress = function(evt) { 
      var evt = (evt) ? evt : ((event) ? event : null); 
      var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
      if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
    };
    
    updateRemoveButtons(); 
});

function addActivityRow() {
    const tableBody = document.getElementById('activity_table_body');
    const newRow = document.createElement('tr');

    const today = "<?php echo date('Y-m-d'); ?>";

    newRow.innerHTML = `
        <td><input type="text" name="aktivitas[]" class="form-control" placeholder="Deskripsi aktivitas..."></td>
        <td><input type="date" name="tglmulai[]" class="form-control" value="${today}"></td>
        <td><input type="date" name="tglselesai[]" class="form-control"></td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeActivityRow(this)">
                <i class="fa fa-minus"></i> Hapus
            </button>
        </td>
    `;
    
    tableBody.appendChild(newRow);
    
    updateRemoveButtons();
}

function removeActivityRow(button) {
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
    
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const tableBody = document.getElementById('activity_table_body');
    const rows = tableBody.getElementsByTagName('tr');
    
    if (rows.length === 1) {
        const firstButton = rows[0].querySelector('.btn-danger');
        if (firstButton) {
            firstButton.disabled = true;
        }
    } else {
        for (let i = 0; i < rows.length; i++) {
            const button = rows[i].querySelector('.btn-danger');
            if (button) {
                button.disabled = false;
            }
        }
        
        <?php if ($is_edit_mode): ?>
        if (rows.length > 0) {
            const firstButton = rows[0].querySelector('.btn-danger');
            if (firstButton) {
                firstButton.disabled = true;
            }
        }
        <?php endif; ?>
    }
}

function ConfirmDelete() {
    return confirm("Are you sure you want to delete?");
}
</script>

</body>

</html>