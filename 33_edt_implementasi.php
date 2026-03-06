<?php
// 
include "dbase.php";
include "islogin.php";

// Handle AJAX request untuk get data edit (HARUS DI AWAL SEBELUM OUTPUT APAPUN)
if (isset($_POST['ajax_get_data']) && $_POST['ajax_get_data'] == 'Y') {
    header('Content-Type: application/json');
    
    $id_to_edit = (int)$_POST['id'];
    $sql_edit = "SELECT t.*, r.nmcustomer 
                 FROM timplementasi t
                 LEFT JOIN rcustomer r ON t.kodcustomer = r.kodcustomer
                 WHERE t.id = :id AND t.stsdel = 0"; 
    try {
        $q_edit = $conn->prepare($sql_edit);
        $q_edit->bindParam(':id', $id_to_edit, PDO::PARAM_INT);
        $q_edit->execute();
        
        if ($q_edit->rowCount() > 0) {
            $edit_data = $q_edit->fetch(PDO::FETCH_ASSOC);
            // Pastikan tglselesai tidak null untuk JavaScript
            if (empty($edit_data['tglselesai'])) {
                $edit_data['tglselesai'] = '';
            }
            echo json_encode(['success' => true, 'data' => $edit_data]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit; // PENTING: Exit di sini
}

// Handle update via AJAX (HARUS DI AWAL SEBELUM OUTPUT APAPUN)
if (isset($_POST['update']) && $_POST['update'] == 'Y') {
    header('Content-Type: application/json');
    
    // Load session variables untuk update
    $iduser = $_SESSION['DEFAULT_IDUSER'];
    $kodjab = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;
    
    if ($kodjab != 1) {
        echo json_encode(['success' => false, 'message' => 'Anda tidak memiliki hak akses untuk mengedit data ini.']);
        exit;
    }

    $id_edit       = (int)$_POST['id_edit'];
    $kodcustomer   = trim($_POST['kodcustomer']);
    $dikerjakan    = trim($_POST['dikerjakan']);
    $prioritas     = trim($_POST['prioritas']);
    
    $aktivitas_list   = isset($_POST['aktivitas']) ? $_POST['aktivitas'] : [];
    $tglmulai_list    = isset($_POST['tglmulai']) ? $_POST['tglmulai'] : [];
    $tglselesai_list  = isset($_POST['tglselesai']) ? $_POST['tglselesai'] : [];
    $isselesai_list   = isset($_POST['isselesai']) ? $_POST['isselesai'] : [];

    if (empty($id_edit) || empty($aktivitas_list[0]) || empty($kodcustomer)) {
        echo json_encode(['success' => false, 'message' => 'Data tidak lengkap (Customer, Aktivitas pertama harus diisi).']);
        exit;
    }

    $conn->beginTransaction();
    
    try {
        $aktivitas   = trim($aktivitas_list[0]);
        $tglmulai    = $tglmulai_list[0];
        $tglselesai  = !empty($tglselesai_list[0]) ? $tglselesai_list[0] : null;
        $isselesai   = isset($isselesai_list[0]) ? (int)$isselesai_list[0] : 0;

        // Update record yang sudah ada
        $sql_upd = "UPDATE timplementasi SET 
                        kodcustomer = :kodcustomer, 
                        aktivitas = :aktivitas, 
                        tglmulai = :tglmulai, 
                        tglselesai = :tglselesai, 
                        userorder = :dikerjakan, 
                        userpj = :prioritas,
                        isselesai = :isselesai
                    WHERE 
                        id = :id"; 
        
        $q_upd = $conn->prepare($sql_upd);
        $q_upd->bindParam(':kodcustomer', $kodcustomer, PDO::PARAM_STR);
        $q_upd->bindParam(':aktivitas', $aktivitas, PDO::PARAM_STR);
        $q_upd->bindParam(':tglmulai', $tglmulai, PDO::PARAM_STR);
        
        if ($tglselesai === null) {
            $q_upd->bindParam(':tglselesai', $tglselesai, PDO::PARAM_NULL);
        } else {
            $q_upd->bindParam(':tglselesai', $tglselesai, PDO::PARAM_STR);
        }
        
        $q_upd->bindParam(':dikerjakan', $dikerjakan, PDO::PARAM_STR);
        $q_upd->bindParam(':prioritas', $prioritas, PDO::PARAM_STR);
        $q_upd->bindParam(':isselesai', $isselesai, PDO::PARAM_INT);
        $q_upd->bindParam(':id', $id_edit, PDO::PARAM_INT);
        $q_upd->execute();
        
        // Insert data baru (index [1] dst.) jika ada
        if (count($aktivitas_list) > 1) {
            $sql_ins = "INSERT INTO timplementasi (iduser, kodcustomer, aktivitas, tglmulai, tglselesai, userorder, userpj, isselesai, stsdel) 
                        VALUES (:iduser, :kodcustomer, :aktivitas, :tglmulai, :tglselesai, :dikerjakan, :prioritas, :isselesai, 0)";
            $q_ins = $conn->prepare($sql_ins);

            for ($i = 1; $i < count($aktivitas_list); $i++) {
                $new_aktivitas = trim($aktivitas_list[$i]);
                if (!empty($new_aktivitas)) {
                    $new_tglmulai   = $tglmulai_list[$i];
                    $new_tglselesai = !empty($tglselesai_list[$i]) ? $tglselesai_list[$i] : null;
                    $new_isselesai  = isset($isselesai_list[$i]) ? (int)$isselesai_list[$i] : 0;

                    $q_ins->bindParam(':iduser', $iduser, PDO::PARAM_STR);
                    $q_ins->bindParam(':kodcustomer', $kodcustomer, PDO::PARAM_STR);
                    $q_ins->bindParam(':aktivitas', $new_aktivitas, PDO::PARAM_STR);
                    $q_ins->bindParam(':tglmulai', $new_tglmulai, PDO::PARAM_STR);
                    
                    if ($new_tglselesai === null) {
                        $q_ins->bindParam(':tglselesai', $new_tglselesai, PDO::PARAM_NULL);
                    } else {
                        $q_ins->bindParam(':tglselesai', $new_tglselesai, PDO::PARAM_STR);
                    }
                    
                    $q_ins->bindParam(':dikerjakan', $dikerjakan, PDO::PARAM_STR);
                    $q_ins->bindParam(':prioritas', $prioritas, PDO::PARAM_STR);
                    $q_ins->bindParam(':isselesai', $new_isselesai, PDO::PARAM_INT);
                    $q_ins->execute();
                }
            }
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Data berhasil diupdate' . (count($aktivitas_list) > 1 ? ' (termasuk ' . (count($aktivitas_list) - 1) . ' aktivitas baru)' : '')]);
        exit;
        
    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Error update: ' . $e->getMessage()]);
        exit;
    }
}

// Setelah AJAX handlers, baru load variabel untuk halaman normal
$pesan = $_SESSION['PESAN'] ?? '';
unset($_SESSION['PESAN']);
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = isset($_SESSION['DEFAULT_KODJAB']) ? $_SESSION['DEFAULT_KODJAB'] : 0;

$tgl   = isset($_GET['tgl']) ? trim($_GET['tgl']) : date('Y-m-d');
$sdtgl = isset($_GET['sdtgl']) ? trim($_GET['sdtgl']) : date('Y-m-d');

$filter_client     = isset($_GET['filter_client']) ? trim($_GET['filter_client']) : '';
$filter_aktivitas  = isset($_GET['filter_aktivitas']) ? trim($_GET['filter_aktivitas']) : '';
$filter_tgl_mulai  = isset($_GET['filter_tgl_mulai']) ? trim($_GET['filter_tgl_mulai']) : '';
$filter_tgl_selesai = isset($_GET['filter_tgl_selesai']) ? trim($_GET['filter_tgl_selesai']) : '';
$filter_dikerjakan = isset($_GET['filter_dikerjakan']) ? trim($_GET['filter_dikerjakan']) : '';

if (isset($_GET['para']) && $_GET['para'] == "ditolak") {
    $pesan = $_GET['pesan'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Update Implementasi</title>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    
    <style>
    .filter-container {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 15px;
        flex-wrap: wrap;
    }
    .filter-container input,
    .filter-container select {
        padding: 5px 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 13px;
    }
    .filter-container label {
        margin: 0;
        font-weight: normal;
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.5);
    }

    .modal.fade {
        opacity: 0;
        transition: opacity 0.15s linear;
    }

    .modal.fade.in {
        opacity: 1;
    }

    .modal-content {
        background-color: #fefefe;
        margin: 2% auto;
        padding: 0;
        border: 1px solid #888;
        width: 90%;
        max-width: 900px;
        border-radius: 5px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.5);
    }

    .modal.fade .modal-content {
        transform: translate(0, -25%);
        transition: transform 0.3s ease-out;
    }

    .modal.fade.in .modal-content {
        transform: translate(0, 0);
    }

    .modal-header {
        padding: 15px;
        background-color: #3c8dbc;
        color: white;
        border-radius: 5px 5px 0 0;
        border-bottom: 1px solid #e5e5e5;
    }

    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }

    .modal-body {
        padding: 20px;
        max-height: 70vh;
        overflow-y: auto;
        position: relative;
    }

    .modal-footer {
        padding: 15px;
        background-color: #f4f4f4;
        border-radius: 0 0 5px 5px;
        text-align: right;
        border-top: 1px solid #e5e5e5;
    }

    .close {
        color: white;
        float: right;
        font-size: 28px;
        font-weight: bold;
        line-height: 20px;
        cursor: pointer;
        opacity: 0.8;
        background: transparent;
        border: 0;
        padding: 0;
    }

    .close:hover,
    .close:focus {
        opacity: 1;
        text-decoration: none;
    }

    .modal-backdrop {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        left: 0;
        z-index: 9998;
        background-color: #000;
    }

    .modal-backdrop.fade {
        opacity: 0;
        transition: opacity 0.15s linear;
    }

    .modal-backdrop.fade.in {
        opacity: 0.5;
    }
    </style>
</head>

<body style="background-color: #ecf0f5;">

    <div class="row">
        <section class="panel">
            <div class="panel-body"> 
                <ol class="breadcrumb" style="background-color: #fff; border-bottom: 1px solid #eee;">
                    <li><i class="fa fa-home"></i> UPDATE IMPLEMENTASI</li>
                </ol>
                
                <?php if (!empty($pesan)): ?>
                    <h4 style="margin-top: 15px;"><?php echo $pesan; ?></h4>
                <?php endif; ?>

                <form role="form" method="GET" action="index.php" style="margin-bottom: 20px;">
                    <div class="form-inline" style="margin-bottom: 15px; display: flex; gap: 10px;">
                        <div class="form-group">
                            <label>Tanggal : </label>
                            <input name="tgl" id="dp1" type="text" value="<?php echo htmlspecialchars($tgl); ?>" size="16" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Sampai Tanggal : </label>
                            <input name="sdtgl" id="dp2" type="text" value="<?php echo htmlspecialchars($sdtgl); ?>" size="16" class="form-control">
                        </div>
                    </div>
                    
                    <div class="filter-container">
                        <label><strong>Filter:</strong></label>
                        
                        <select id="filterClient" name="filter_client" class="form-control" style="width: 200px;">
                            <option value="">-- Semua Client --</option>
                            <?php
                                $qk_cust_filter = $conn->prepare("SELECT * FROM rcustomer WHERE status = 1 ORDER BY nmcustomer"); 
                                $qk_cust_filter->execute(); 
                                while($rsk_cust_filter = $qk_cust_filter->fetch()){ 
                                    $selected = ($filter_client == $rsk_cust_filter['nmcustomer']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($rsk_cust_filter['nmcustomer'])."' $selected>".htmlspecialchars($rsk_cust_filter['nmcustomer'])."</option>\n";
                                }
                            ?>
                        </select>
                        
                        <input type="text" id="filterAktivitas" name="filter_aktivitas" placeholder="Aktivitas" class="form-control" style="width: 150px;"
                            value="<?php echo htmlspecialchars($filter_aktivitas); ?>" />
                        
                        <input type="date" id="filterTglMulai" name="filter_tgl_mulai" placeholder="Tgl Mulai" class="form-control" style="width: 160px;"
                            value="<?php echo htmlspecialchars($filter_tgl_mulai); ?>" />
                        
                        <input type="date" id="filterTglSelesai" name="filter_tgl_selesai" placeholder="Tgl Selesai" class="form-control" style="width: 160px;"
                            value="<?php echo htmlspecialchars($filter_tgl_selesai); ?>" />
                        
                        <select id="filterDikerjakan" name="filter_dikerjakan" class="form-control" style="width: 200px;">
                            <option value="">-- Semua User --</option>
                            <?php
                                $qk_user_filter = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
                                $qk_user_filter->execute(); 
                                while($rsk_user_filter = $qk_user_filter->fetch()){ 
                                    $selected = ($filter_dikerjakan == $rsk_user_filter['nama']) ? 'selected' : '';
                                    echo "<option value='".htmlspecialchars($rsk_user_filter['nama'])."' $selected>".htmlspecialchars($rsk_user_filter['nama'])."</option>\n";
                                }
                            ?>
                        </select>
                    </div>

                    <div>
                        <input type="hidden" name="par" id="par" value="33">
                        <button type="submit" name="submit" class="btn btn-primary">Submit</button>
                        <a href="index.php?par=33" class="btn btn-danger">Reset</a>
                    </div>
                </form>

                <div align="center" style="margin-bottom: 15px;">
                    <?php echo " Dari tanggal " . htmlspecialchars($tgl) . "&nbsp;&nbsp;&nbsp; Sampai tanggal " . htmlspecialchars($sdtgl); ?>
                </div>
                
                <div class="box-body">
                    <table id="contoh" class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Nama Client</th>
                                <th>Aktivitas</th>
                                <th>Tgl Mulai</th>
                                <th>Tgl Selesai</th>
                                <th>Dikerjakan Oleh</th>
                                <th>Terlambat</th>
                                <th>Selesai</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            try {
                                $params = [
                                    ':tgl' => $tgl, 
                                    ':sdtgl' => $sdtgl
                                ];
                                
                                $strsql = "SELECT 
                                        t.id, t.aktivitas, t.tglmulai, t.tglselesai, t.iduser, t.kodcustomer, t.userorder, t.userpj, t.isselesai,
                                        r.nmcustomer,
                                        u_order.nama AS nama_dikerjakan_oleh,
                                        u_pj.nama AS nama_penanggung_jawab
                                        FROM timplementasi t 
                                        LEFT JOIN rcustomer r ON t.kodcustomer = r.kodcustomer 
                                        LEFT JOIN ruser u_order ON t.userorder = u_order.iduser
                                        LEFT JOIN ruser u_pj ON t.userpj = u_pj.iduser
                                        WHERE (t.tglmulai >= :tgl AND t.tglmulai <= :sdtgl) AND t.stsdel = 0 ";
                                
                                if (!empty($filter_client)) {
                                    $strsql .= " AND r.nmcustomer = :filter_client ";
                                    $params[':filter_client'] = $filter_client;
                                }
                                if (!empty($filter_aktivitas)) {
                                    $strsql .= " AND t.aktivitas LIKE :filter_aktivitas ";
                                    $params[':filter_aktivitas'] = '%' . $filter_aktivitas . '%';
                                }
                                if (!empty($filter_tgl_mulai)) {
                                    $strsql .= " AND t.tglmulai = :filter_tgl_mulai ";
                                    $params[':filter_tgl_mulai'] = $filter_tgl_mulai;
                                }
                                if (!empty($filter_tgl_selesai)) {
                                    $strsql .= " AND t.tglselesai = :filter_tgl_selesai ";
                                    $params[':filter_tgl_selesai'] = $filter_tgl_selesai;
                                }
                                if (!empty($filter_dikerjakan)) {
                                    $strsql .= " AND u_order.nama = :filter_dikerjakan ";
                                    $params[':filter_dikerjakan'] = $filter_dikerjakan;
                                }
                                
                                $strsql .= " ORDER BY r.nmcustomer, t.tglmulai";
                                
                                $sql = $conn->prepare($strsql);
                                $sql->execute($params);
                                
                                $no = 1;
                                $current_client = "";
                                
                                while ($rs = $sql->fetch(PDO::FETCH_ASSOC)) {
                                    $terlambat_status = "Tidak";
                                    $tgl_selesai = $rs['tglselesai'];
                                    $tgl_hari_ini = date('Y-m-d');

                                    if ($tgl_selesai < $tgl_hari_ini) {
                                        $terlambat_status = "<span style='color: red; font-weight: bold;'>Ya</span>";
                                    }

                                    echo "<tr>";
                                    echo "<td align=center>" . $no . "</td>";
                                    $client_name = $rs['nmcustomer'];
                                    if ($client_name != $current_client) {
                                        echo "<td><strong>" . htmlspecialchars($client_name) . "</strong></td>";
                                        $current_client = $client_name; 
                                    } else {
                                        echo "<td></td>"; 
                                    }
                                    echo "<td>" . htmlspecialchars($rs['aktivitas']) . "</td>";
                                    echo "<td>" . htmlspecialchars(date('d/m/Y', strtotime($rs['tglmulai']))) . "</td>";
                                    echo "<td>" . ($rs['tglselesai'] ? htmlspecialchars(date('d/m/Y', strtotime($rs['tglselesai']))) : '-') . "</td>";
                                    echo "<td>" . htmlspecialchars($rs['nama_dikerjakan_oleh']) . "</td>";
                                    echo "<td>" . $terlambat_status . "</td>";

                                    $status_selesai = ($rs['isselesai'] == 1) 
                                        ? "<span style='color:green;font-weight:bold;'>Ya</span>" 
                                        : "Belum";
                                    echo "<td>" . $status_selesai . "</td>";
                                    
                                    echo "<td>
                                            <button type='button' class='btn btn-primary btn-xs btn-edit' 
                                                data-id='" . $rs['id'] . "'";
                                    
                                    if ($kodjab != 1) {
                                        echo " disabled";
                                    }
                                    
                                    echo ">Edit</button>
                                          </td>";
                                    
                                    echo "</tr>";
                                    $no++;
                                }
                            } catch (PDOException $e) {
                                echo "<tr><td colspan='9' class='text-center'>Terjadi error: " . $e->getMessage() . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>

    <!-- Modal Edit -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <span class="close">&times;</span>
                <h3>Edit Implementasi</h3>
            </div>
            <div class="modal-body">
                <form id="editForm">
                    <div class="row">
                        <div class="col-xs-12 col-md-6">
                            <div class="form-group">
                                <label>Customer / Mitra</label>	
                                <select name="kodcustomer" id="modal_kodcustomer" class="select2-modal form-control" required> 
                                    <option value="" disabled>-- Pilih Customer --</option>
                                    <?php
                                        $qk_cust = $conn->prepare("SELECT * FROM rcustomer WHERE status = 1 ORDER BY kodcustomer "); 
                                        $qk_cust->execute(); 
                                        while($rsk_cust = $qk_cust->fetch()){ 
                                            echo "<option value='".$rsk_cust['kodcustomer']."'>".$rsk_cust['nmcustomer']."</option>\n";
                                        }
                                    ?>
                                </select> 
                            </div>
                        </div>	
                        
                        <div class="col-xs-12 col-md-3">
                            <div class="form-group">
                                <label>Dikerjakan Oleh</label>	
                                <select name="dikerjakan" id="modal_dikerjakan" class="form-control" required> 
                                    <option value="" disabled>-- Pilih User --</option>
                                    <?php
                                        $qk_user = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC"); 
                                        $qk_user->execute(); 
                                        while($rsk_user = $qk_user->fetch()){ 
                                            echo "<option value='".$rsk_user['iduser']."'>".$rsk_user['nama']."</option>\n"; 
                                        }
                                    ?>
                                </select> 
                            </div>
                        </div>
                        <div class="col-xs-12 col-md-3">
                            <div class="form-group">
                                <label>Penanggung Jawab</label>	
                                <select name="prioritas" id="modal_prioritas" class="form-control" required> 
                                    <option value="" disabled>-- Pilih User --</option>
                                    <?php
                                        $qk_user->execute();
                                        while($rsk_user = $qk_user->fetch()){ 
                                            echo "<option value='".$rsk_user['iduser']."'>".$rsk_user['nama']."</option>\n"; 
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
                                        <th style="width: 40%;">Aktivitas</th>
                                        <th style="width: 15%;">Tgl Mulai</th>
                                        <th style="width: 15%;">Tgl Selesai</th>
                                        <th style="width: 15%;">Selesai</th>
                                        <th style="width: 15%;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="modal_activity_table_body">
                                </tbody>
                            </table>
                            
                            <button type="button" class="btn btn-success" id="modal_add_activity_btn">
                                <i class="fa fa-plus"></i> Tambah Aktivitas
                            </button>
                        </div>
                    </div>
                    
                    <input type="hidden" name="update" value="Y">
                    <input type="hidden" name="id_edit" id="modal_id_edit">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="btnSaveEdit">Update Data</button>
                <button type="button" class="btn btn-danger" id="btnCancelEdit">Batal</button>
            </div>
        </div>
    </div>

<script type="text/javascript">
var modal = document.getElementById("editModal");
var span = document.getElementsByClassName("close")[0];
var backdrop = null;

// Fungsi untuk membuat backdrop (Bootstrap style)
function createBackdrop() {
    backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade';
    document.body.appendChild(backdrop);
    
    // Trigger reflow
    backdrop.offsetHeight;
    backdrop.classList.add('in');
}

// Fungsi untuk remove backdrop
function removeBackdrop() {
    if (backdrop) {
        backdrop.classList.remove('in');
        setTimeout(function() {
            if (backdrop && backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
                backdrop = null;
            }
        }, 150); // 150ms sesuai Bootstrap
    }
}

// Fungsi untuk membuka modal dengan Bootstrap fade
function openModal() {
    // Tambahkan backdrop
    createBackdrop();
    
    // Tampilkan modal
    modal.style.display = "block";
    modal.classList.add('fade');
    
    // Trigger reflow untuk memastikan animasi berjalan
    modal.offsetHeight;
    
    // Tambahkan class 'in' untuk fade in
    modal.classList.add('in');
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

// Fungsi untuk menutup modal dengan Bootstrap fade
function closeModal() {
    // Remove class 'in' untuk fade out
    modal.classList.remove('in');
    
    // Remove backdrop
    removeBackdrop();
    
    // Tunggu animasi selesai baru hide modal (300ms untuk modal-content transform)
    setTimeout(function() {
        modal.style.display = "none";
        modal.classList.remove('fade');
        document.body.style.overflow = '';
    }, 300);
}

// Event listener untuk tombol close (X)
span.onclick = function(e) {
    e.preventDefault();
    closeModal();
}

// Event listener untuk click di backdrop
$(document).on('click', '.modal-backdrop', function() {
    closeModal();
});

// Event listener untuk tombol Batal
document.getElementById("btnCancelEdit").onclick = function() {
    closeModal();
}

// Fungsi-fungsi lainnya tetap sama
function addModalActivityRow() {
    const tableBody = document.getElementById('modal_activity_table_body');
    if (!tableBody) return;
    
    const newRow = document.createElement('tr');
    const today = "<?php echo date('Y-m-d'); ?>";

    newRow.innerHTML = `
        <td><input type="text" name="aktivitas[]" class="form-control" placeholder="Deskripsi aktivitas..."></td>
        <td><input type="date" name="tglmulai[]" class="form-control" value="${today}"></td>
        <td><input type="date" name="tglselesai[]" class="form-control"></td>
        <td>
            <select name="isselesai[]" class="form-control">
                <option value="0">Belum</option>
                <option value="1">Ya</option>
            </select>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="removeModalActivityRow(this)">
                <i class="fa fa-minus"></i> Hapus
            </button>
        </td>
    `;
    
    tableBody.appendChild(newRow);
    updateModalRemoveButtons();
}

function removeModalActivityRow(button) {
    const row = button.parentNode.parentNode;
    row.parentNode.removeChild(row);
    updateModalRemoveButtons();
}

function updateModalRemoveButtons() {
    const tableBody = document.getElementById('modal_activity_table_body');
    if (!tableBody) return;
    
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
        
        if (rows.length > 0) {
            const firstButton = rows[0].querySelector('.btn-danger');
            if (firstButton) {
                firstButton.disabled = true;
            }
        }
    }
}

(function($) {
    setTimeout(function() {
        $('#contoh_wrapper').find('.dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate').remove();
        
        if ($.fn.DataTable.isDataTable('#contoh')) {
            $('#contoh').DataTable().clear().destroy();
        }
        
        if ($('#contoh').parent().hasClass('dataTables_wrapper')) {
            $('#contoh').unwrap();
        }
        
        var table = $('#contoh').DataTable({
            "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
            "pageLength": 10,
            "dom": 'lrtrip',
            "destroy": true
        });
        
        if (typeof $.fn.datepicker === 'function') {
            $('#dp1').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
            $('#dp2').datepicker({ format: 'yyyy-mm-dd', autoclose: true });
        }
    }, 100);
    
    // Handle Edit Button Click
    $(document).on('click', '.btn-edit', function() {
        var id = $(this).data('id');
        
        // AJAX request to get data
        $.ajax({
            url: 'index.php?par=33',
            type: 'POST',
            data: {
                ajax_get_data: 'Y',
                id: id
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    // Populate form
                    $('#modal_id_edit').val(data.id);
                    $('#modal_kodcustomer').val(data.kodcustomer);
                    $('#modal_dikerjakan').val(data.userorder);
                    $('#modal_prioritas').val(data.userpj);
                    
                    // Clear and add first activity row
                    $('#modal_activity_table_body').empty();
                    var tglSelesaiValue = data.tglselesai ? data.tglselesai : '';
                    var firstRow = `
                        <tr>
                            <td>
                                <input type="text" name="aktivitas[]" class="form-control" required 
                                    value="${escapeHtml(data.aktivitas)}">
                            </td>
                            <td>
                                <input type="date" name="tglmulai[]" class="form-control" required
                                    value="${data.tglmulai}">
                            </td>
                            <td>
                                <input type="date" name="tglselesai[]" class="form-control" 
                                    value="${tglSelesaiValue}">
                            </td>
                            <td>
                                <select name="isselesai[]" class="form-control">
                                    <option value="0" ${data.isselesai == 0 ? 'selected' : ''}>Belum</option>
                                    <option value="1" ${data.isselesai == 1 ? 'selected' : ''}>Ya</option>
                                </select>
                            </td>
                            <td>
                                <button type="button" class="btn btn-danger btn-sm" onclick="removeModalActivityRow(this)" disabled>
                                    <i class="fa fa-minus"></i> Hapus
                                </button>
                            </td>
                        </tr>
                    `;
                    $('#modal_activity_table_body').append(firstRow);
                    updateModalRemoveButtons();
                    
                    // Show modal dengan animasi Bootstrap
                    openModal();
                    
                    // Reinitialize select2 for modal
                    if ($.fn.select2) {
                        $('.select2-modal').select2({
                            dropdownParent: $('#editModal')
                        });
                    }
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.log('AJAX Error:', xhr.responseText);
                try {
                    var responseText = xhr.responseText;
                    var jsonStart = responseText.indexOf('{"success"');
                    if (jsonStart > -1) {
                        var jsonText = responseText.substring(jsonStart);
                        var jsonData = JSON.parse(jsonText);
                        if (jsonData.success) {
                            var data = jsonData.data;
                            $('#modal_id_edit').val(data.id);
                            $('#modal_kodcustomer').val(data.kodcustomer);
                            $('#modal_dikerjakan').val(data.userorder);
                            $('#modal_prioritas').val(data.userpj);
                            
                            $('#modal_activity_table_body').empty();
                            var tglSelesaiValue = data.tglselesai ? data.tglselesai : '';
                            var firstRow = `
                                <tr>
                                    <td>
                                        <input type="text" name="aktivitas[]" class="form-control" required 
                                            value="${escapeHtml(data.aktivitas)}">
                                    </td>
                                    <td>
                                        <input type="date" name="tglmulai[]" class="form-control" required
                                            value="${data.tglmulai}">
                                    </td>
                                    <td>
                                        <input type="date" name="tglselesai[]" class="form-control" 
                                            value="${tglSelesaiValue}">
                                    </td>
                                    <td>
                                        <select name="isselesai[]" class="form-control">
                                            <option value="0" ${data.isselesai == 0 ? 'selected' : ''}>Belum</option>
                                            <option value="1" ${data.isselesai == 1 ? 'selected' : ''}>Ya</option>
                                        </select>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeModalActivityRow(this)" disabled>
                                            <i class="fa fa-minus"></i> Hapus
                                        </button>
                                    </td>
                                </tr>
                            `;
                            $('#modal_activity_table_body').append(firstRow);
                            updateModalRemoveButtons();
                            openModal();
                            
                            if ($.fn.select2) {
                                $('.select2-modal').select2({
                                    dropdownParent: $('#editModal')
                                });
                            }
                            return;
                        }
                    }
                } catch(e) {
                    console.log('Failed to parse JSON:', e);
                }
                alert('Terjadi kesalahan saat mengambil data: ' + error);
            }
        });
    });
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Handle Save Edit
    $('#btnSaveEdit').on('click', function() {
        // Validasi form
        var customer = $('#modal_kodcustomer').val();
        var dikerjakan = $('#modal_dikerjakan').val();
        var prioritas = $('#modal_prioritas').val();
        var aktivitas = $('input[name="aktivitas[]"]').eq(0).val();
        
        if (!customer || !dikerjakan || !prioritas || !aktivitas) {
            alert('Customer, Dikerjakan Oleh, Penanggung Jawab, dan Aktivitas pertama harus diisi!');
            return false;
        }
        
        var formData = $('#editForm').serialize();
        
        // Disable button untuk mencegah double click
        $('#btnSaveEdit').prop('disabled', true).text('Menyimpan...');
        
        $.ajax({
            url: 'index.php?par=33',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#btnSaveEdit').prop('disabled', false).text('Update Data');
                
                if (response.success) {
                    alert(response.message);
                    closeModal();
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                $('#btnSaveEdit').prop('disabled', false).text('Update Data');
                console.log('Save Error:', xhr.responseText);
                
                try {
                    var responseText = xhr.responseText;
                    var jsonStart = responseText.indexOf('{"success"');
                    if (jsonStart > -1) {
                        var jsonText = responseText.substring(jsonStart);
                        var jsonData = JSON.parse(jsonText);
                        
                        if (jsonData.success) {
                            alert(jsonData.message);
                            closeModal();
                            location.reload();
                            return;
                        } else {
                            alert('Error: ' + jsonData.message);
                            return;
                        }
                    }
                } catch(e) {
                    console.log('Failed to parse JSON:', e);
                }
                
                alert('Terjadi kesalahan saat menyimpan data: ' + error);
            }
        });
    });
    
    $('#modal_add_activity_btn').on('click', addModalActivityRow);
    
    // ESC key untuk menutup modal (Bootstrap style)
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('in')) {
            closeModal();
        }
    });
    
})(jQuery);
</script>

</body>
</html>