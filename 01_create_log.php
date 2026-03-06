<?php
// 
session_start();
include "dbase.php"; 
include "islogin.php"; 

// Include library PHPSpreadsheet untuk membaca Excel
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pesan = "";
$iduser   = $_SESSION['DEFAULT_IDUSER'];

$excel_data = array();
$show_excel_preview = false;

function left($str, $length) {
     return substr($str, 0, $length);
}

function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function validate_date($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function validate_jnsbisnis($jns) {
    $allowed = array('A: ADMINISTRATION', 'B: BUSINESS', 'D: DEVELOPING', 'M: MAINTENANCE');
    return in_array(strtoupper($jns), $allowed);
}

function validate_prioritas($prioritas) {
    $allowed = array('Sangat Tinggi', 'Tinggi', 'Biasa', '1', '2', '3');
    return in_array($prioritas, $allowed);
}

/**
 * Ekstrak gambar base64 dari HTML Quill, simpan ke file, ganti src dengan path file.
 * Kolom database jadi bersih tanpa base64.
 */
function extractAndSaveImages($html) {
    $uploads_dir = 'uploads/log_images/';
    if (!is_dir($uploads_dir)) {
        mkdir($uploads_dir, 0755, true);
    }

    // Cari semua <img src="data:image/...;base64,...">
    $pattern = '/<img([^>]*)src="data:image\/([^;]+);base64,([^"]+)"([^>]*)>/i';

    $html = preg_replace_callback($pattern, function($matches) use ($uploads_dir) {
        $ext = strtolower($matches[2]);
        // Normalkan ekstensi
        if ($ext === 'jpeg') $ext = 'jpg';
        if (!in_array($ext, ['jpg', 'png', 'gif', 'webp'])) $ext = 'png';

        $filename  = 'img_' . uniqid('', true) . '.' . $ext;
        $filepath  = $uploads_dir . $filename;
        $imageData = base64_decode($matches[3]);

        if ($imageData !== false && file_put_contents($filepath, $imageData) !== false) {
            // Ganti dengan path file, pertahankan atribut lain
            return '<img' . $matches[1] . 'src="' . $filepath . '"' . $matches[4] . '>';
        }
        // Jika gagal simpan, hapus saja img agar tidak merusak DB
        return '';
    }, $html);

    return $html;
}

function parse_excel_file($file_path) {
    try {
        $spreadsheet = IOFactory::load($file_path);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        
        $data = array();
        
        for ($row = 1; $row <= $highestRow; $row++) {
            $rowData = array();
            for ($col = 'A'; $col <= $highestColumn; $col++) {
                $cellValue = $worksheet->getCell($col . $row)->getValue();
                
                // Handle date columns (D and E for tglorder and tglselesai)
                if (($col == 'D' || $col == 'E') && $row > 1) {
                    if (is_numeric($cellValue)) {
                        // Convert Excel date to PHP date
                        $dateValue = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($cellValue);
                        $cellValue = $dateValue->format('Y-m-d');
                    }
                }
                
                $rowData[] = trim($cellValue);
            }
            
            if (count(array_filter($rowData)) > 0) {
                $data[] = $rowData;
            }
        }
        
        return array(
            'success' => true, 
            'data' => $data, 
            'total_rows' => count($data)
        );
    } catch (Exception $e) {
        return array(
            'success' => false, 
            'message' => 'Error membaca file Excel: ' . $e->getMessage()
        );
    }
}

// Process Excel Import
if (isset($_POST['import_excel'])) {
    if (isset($_FILES['excel_file']) && $_FILES['excel_file']['error'] == 0) {
        $file_name = $_FILES['excel_file']['name'];
        $file_tmp = $_FILES['excel_file']['tmp_name'];
        $file_size = $_FILES['excel_file']['size'];
        
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_ext, array('xlsx', 'xls'))) {
            $pesan = "<font color=red><strong>Error:</strong> File harus berformat Excel (.xlsx atau .xls)</font>";
        }
        elseif ($file_size > 5242880) { // 5MB
            $pesan = "<font color=red><strong>Error:</strong> Ukuran file maksimal 5MB</font>";
        }
        else {
            $parse_result = parse_excel_file($file_tmp);
            
            if (!$parse_result['success']) {
                $pesan = "<font color=red><strong>Error:</strong> " . $parse_result['message'] . "</font>";
            } else {
                $excel_rows = $parse_result['data'];
                
                if (count($excel_rows) < 2) {
                    $pesan = "<font color=red><strong>Error:</strong> File Excel kosong atau tidak memiliki data</font>";
                } else {
                    $header = $excel_rows[0];
                    $error_messages = array();

                    $expected_headers = array('No','Jenis Report','Mitra','Tanggal Order','Tanggal Selesai','Dikerjakan Oleh','Prioritas','Order Melalui','Uraian Order','Aktivitas Layanan');
                    
                    if (count($header) !== 10) {
                        $pesan = "<font color=red><strong>Error:</strong> Format header Excel tidak sesuai template. Header harus memiliki 10 kolom.<br>";
                        $pesan .= "<strong>Jumlah kolom ditemukan:</strong> " . count($header) . "<br>";
                        $pesan .= "<strong>Header ditemukan:</strong> " . implode(' | ', $header) . "</font>";
                    } else {
                        $header_valid = true;
                        $header_errors = array();
                        
                        for ($i = 0; $i < count($expected_headers); $i++) {
                            $header_trimmed = trim($header[$i]);
                            if ($header_trimmed !== $expected_headers[$i]) {
                                $header_valid = false;
                                $header_errors[] = "Kolom " . ($i+1) . ": Ditemukan '{$header[$i]}', seharusnya '{$expected_headers[$i]}'";
                            }
                        }
                        
                        if (!$header_valid) {
                            $pesan = "<font color=red><strong>Error:</strong> Nama kolom header tidak sesuai template.<br>";
                            $pesan .= "<strong>Header yang benar:</strong> No | Jenis Report | Mitra | Tanggal Order | Tanggal Selesai | Dikerjakan Oleh | Prioritas | Order Melalui | Uraian Order | Aktivitas Layanan<br><br>";
                            $pesan .= "<strong>Detail error:</strong><br>";
                            foreach ($header_errors as $err) {
                                $pesan .= "• " . $err . "<br>";
                            }
                            $pesan .= "</font>";
                        } else {
                            for ($i = 1; $i < count($excel_rows); $i++) {
                                $data = $excel_rows[$i];
                                $baris = $i + 1;
                                
                                if (count(array_filter($data)) > 0) {
                                    if (count($data) !== 10) {
                                        $error_messages[] = "Baris $baris: Jumlah kolom tidak sesuai (harus 10 kolom, ditemukan " . count($data) . " kolom)";
                                        continue;
                                    }
                                    
                                    $nomor = sanitize_input($data[0]);
                                    $jnsbisnis = sanitize_input(strtoupper($data[1]));
                                    $kodcustomer = sanitize_input($data[2]);
                                    $tglorder = sanitize_input($data[3]);
                                    $tgltarget = sanitize_input($data[4]);
                                    $dikerjakan = sanitize_input($data[5]);
                                    $prioritas = sanitize_input($data[6]);
                                    $fasorder = sanitize_input(strtoupper($data[7]));
                                    $desorder = sanitize_input($data[8]);
                                    $deslayan = sanitize_input($data[9]);
                                    
                                    // Convert prioritas text to number if needed
                                    if ($prioritas == 'Sangat Tinggi') $prioritas = '1';
                                    if ($prioritas == 'Tinggi') $prioritas = '2';
                                    if ($prioritas == 'Biasa') $prioritas = '3';
                                    
                                    if (empty($nomor) || !is_numeric($nomor)) {
                                        $error_messages[] = "Baris $baris: Nomor tidak valid (harus berupa angka)";
                                        continue;
                                    }
                                    if (empty($kodcustomer)) {
                                        $error_messages[] = "Baris $baris: Customer / Mitra tidak boleh kosong";
                                        continue;
                                    }
                                    if (empty($jnsbisnis)) {
                                        $error_messages[] = "Baris $baris: Kel. bisnis tidak boleh kosong";
                                        continue;
                                    }
                                    if (empty($tglorder)) {
                                        $error_messages[] = "Baris $baris: Tanggal order tidak boleh kosong";
                                        continue;
                                    }
                                    if (empty($tgltarget)) {
                                        $error_messages[] = "Baris $baris: Tanggal target (selesai) tidak boleh kosong";
                                        continue;
                                    }
                                    if (empty($dikerjakan)) {
                                        $error_messages[] = "Baris $baris: User yang mengerjakan tidak boleh kosong";
                                        continue;
                                    }
                                    if (empty($prioritas)) {
                                        $error_messages[] = "Baris $baris: Prioritas tidak boleh kosong";
                                        continue;
                                    }
                                    if (!validate_jnsbisnis($jnsbisnis)) {
                                        $error_messages[] = "Baris $baris: Jenis bisnis '$jnsbisnis' tidak valid";
                                        continue;
                                    }
                                    if (!validate_date($tglorder)) {
                                        $error_messages[] = "Baris $baris: Format tanggal order tidak valid (harus YYYY-MM-DD)";
                                        continue;
                                    }
                                    if (!validate_date($tgltarget)) {
                                        $error_messages[] = "Baris $baris: Format tanggal target (selesai) tidak valid (harus YYYY-MM-DD)";
                                        continue;
                                    }
                                    if (!validate_prioritas($prioritas)) {
                                        $error_messages[] = "Baris $baris: Prioritas '$prioritas' tidak valid (harus 1/2/3 atau Sangat Tinggi/Tinggi/Biasa)";
                                        continue;
                                    }
                                    if (strlen($kodcustomer) > 50) {
                                        $error_messages[] = "Baris $baris: Kode customer terlalu panjang (max 50 karakter)";
                                        continue;
                                    }
                                    if (strlen($fasorder) > 100) {
                                        $error_messages[] = "Baris $baris: Order melalui terlalu panjang (max 100 karakter)";
                                        continue;
                                    }
                                    if (strlen($desorder) > 1000) {
                                        $error_messages[] = "Baris $baris: Deskripsi order terlalu panjang (max 1000 karakter)";
                                        continue;
                                    }
                                    if (strlen($deslayan) > 2000) {
                                        $error_messages[] = "Baris $baris: Deskripsi layanan terlalu panjang (max 2000 karakter)";
                                        continue;
                                    }
                                    
                                    $check_customer = $conn->prepare("SELECT kodcustomer FROM rcustomer WHERE kodcustomer = ? OR nmcustomer = ?");
                                    $check_customer->execute([$kodcustomer, $kodcustomer]);
                                    if ($check_customer->rowCount() == 0) {
                                        $error_messages[] = "Baris $baris: Customer / Mitra '$kodcustomer' tidak ditemukan di database";
                                        continue;
                                    }

                                    $customer_row = $check_customer->fetch(PDO::FETCH_ASSOC);
                                    $kodcustomer = $customer_row['kodcustomer'];
                                    
                                    $check_user = $conn->prepare("SELECT iduser FROM ruser WHERE iduser = ? OR nama = ?");
                                    $check_user->execute([$dikerjakan, $dikerjakan]);
                                    if ($check_user->rowCount() == 0) {
                                        $error_messages[] = "Baris $baris: User '$dikerjakan' tidak ditemukan di database";
                                        continue;
                                    }
                                    $user_row = $check_user->fetch(PDO::FETCH_ASSOC);
                                    $dikerjakan = $user_row['iduser'];
                                    
                                    $excel_data[] = array(
                                        'nomor' => $nomor,
                                        'kodcustomer' => $kodcustomer,
                                        'jnsbisnis' => $jnsbisnis,
                                        'tglorder' => $tglorder,
                                        'tgltarget' => $tgltarget,
                                        'fasorder' => $fasorder,
                                        'desorder' => $desorder,
                                        'deslayan' => $deslayan,
                                        'dikerjakan' => $dikerjakan,
                                        'prioritas' => $prioritas
                                    );
                                }
                            }
                            
                            if (count($excel_data) > 0) {
                                $show_excel_preview = true;
                                $pesan = "<font color=blue><strong>Berhasil memuat " . count($excel_data) . " baris data dari Excel!</strong><br>";
                                if (count($error_messages) > 0) {
                                    $pesan .= "<font color=orange>Dengan " . count($error_messages) . " baris error yang diabaikan.</font><br>";
                                }
                                $pesan .= "Silakan periksa data di tabel preview dan klik 'Import Semua Data' untuk menyimpan.</font>";
                            } else {
                                if (count($error_messages) > 0) {
                                    $pesan = "<font color=red><strong>Error:</strong> Tidak ada data valid yang dapat diimport<br><br>";
                                    $pesan .= "<strong>Detail Error:</strong><br>";
                                    foreach (array_slice($error_messages, 0, 10) as $err) {
                                        $pesan .= "• " . $err . "<br>";
                                    }
                                    if (count($error_messages) > 10) {
                                        $pesan .= "• Dan " . (count($error_messages) - 10) . " error lainnya...<br>";
                                    }
                                    $pesan .= "</font>";
                                } else {
                                    $pesan = "<font color=red>Error: Tidak ada data dalam file Excel</font>";
                                }
                            }
                        }
                    }
                }
            }
        }
    } else {
        $error_code = $_FILES['excel_file']['error'];
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $pesan = "<font color=red><strong>Error:</strong> File terlalu besar</font>";
                break;
            case UPLOAD_ERR_NO_FILE:
                $pesan = "<font color=red><strong>Error:</strong> Tidak ada file yang diupload</font>";
                break;
            default:
                $pesan = "<font color=red><strong>Error:</strong> Gagal mengupload file (Error code: $error_code)</font>";
        }
    }
}

if (isset($_POST['cancel_preview'])) {
    $excel_data = array();
    $show_excel_preview = false;
    $pesan = "<font color=orange>Preview dibatalkan. Silakan upload file Excel lagi jika diperlukan.</font>";
}

if (isset($_POST['confirm_import'])) {
    $imported_data = json_decode($_POST['excel_data_json'], true);
    $success_count = 0;
    $error_count = 0;
    
    if (is_array($imported_data)) {
        foreach ($imported_data as $data) {
            $nomor = sanitize_input($data['nomor']);
            $kodcustomer = sanitize_input($data['kodcustomer']);
            $jnsbisnis = sanitize_input($data['jnsbisnis']);
            $tglorder = sanitize_input($data['tglorder']);
            $tgltarget = sanitize_input($data['tgltarget']);
            $fasorder = sanitize_input($data['fasorder']);
            $desorder = sanitize_input($data['desorder']);
            $deslayan = sanitize_input($data['deslayan']);
            $dikerjakan = sanitize_input($data['dikerjakan']);
            $prioritas = sanitize_input($data['prioritas']);
            
            if (empty($kodcustomer) || empty($jnsbisnis) || empty($tglorder) || 
                empty($tgltarget) || empty($dikerjakan) || empty($prioritas)) {
                $error_count++;
                continue;
            }
            
            if (!validate_jnsbisnis($jnsbisnis) || !validate_date($tglorder) || 
                !validate_date($tgltarget) || !validate_prioritas($prioritas)) {
                $error_count++;
                continue;
            }
            
            $sql = "INSERT INTO tlog (iduser, jnsbisnis, kodcustomer, tglorder, tgltarget, userorder, prioritas, fasorder, desorder, deslayan) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            try {
                $qins = $conn->prepare($sql);
                $qins->execute([
                    $iduser,
                    $jnsbisnis,
                    $kodcustomer,
                    $tglorder,
                    $tgltarget,
                    $dikerjakan,
                    $prioritas,
                    $fasorder,
                    $desorder,
                    $deslayan
                ]);
                $success_count++;
            } catch (PDOException $e) {
                $error_count++;
            }
        }
        
        $pesan = "<font color=blue><strong>Import selesai!</strong><br>";
        $pesan .= "Berhasil: $success_count data | Gagal: $error_count data</font>";
    } else {
        $pesan = "<font color=red><strong>Error:</strong> Data tidak valid</font>";
    }
    
    $excel_data = array();
    $show_excel_preview = false;
}

if (isset($_POST['ins'])) {
   $kodcustomer   = trim($_POST['kodcustomer']);
   $jnsbisnis  = trim($_POST['jnsbisnis']); 
   $tglorder    = $_POST['tglorder'];
   $tgltarget    = $_POST['tgltarget'];
   $fasorder  = trim($_POST['fasorder']);
   $desorder  = addslashes(extractAndSaveImages(trim($_POST['desorder'])));
   $deslayan   = addslashes(extractAndSaveImages($_POST['deslayan'])); 
   $dikerjakan	 = $_POST['dikerjakan'];
   $prioritas = $_POST['prioritas'];
   
   $issukses = 1;
   
   // Cek apakah kolom idsprint sudah ada di tabel tlog
   $idsprint_val = isset($_POST['idsprint']) && $_POST['idsprint'] !== '' ? intval($_POST['idsprint']) : null;
   $has_idsprint_col = false;
   try { $has_idsprint_col = $conn->query("SHOW COLUMNS FROM tlog LIKE 'idsprint'")->rowCount() > 0; } catch (PDOException $e) {}
   
   // Subtask: id_parent
   $id_parent_val = isset($_POST['id_parent']) && $_POST['id_parent'] !== '' ? intval($_POST['id_parent']) : null;
   
   if ($has_idsprint_col && $idsprint_val !== null) {
       $sql = "insert into tlog (iduser,kodcustomer,jnsbisnis,tglorder,fasorder,desorder,deslayan,userorder,tgltarget,prioritas,idsprint) ";
       $sql .= "values ('$iduser','$kodcustomer','$jnsbisnis','$tglorder','$fasorder','$desorder','$deslayan','$dikerjakan','$tgltarget','$prioritas',$idsprint_val) ";
   } else {
       $sql = "insert into tlog (iduser,kodcustomer,jnsbisnis,tglorder,fasorder,desorder,deslayan,userorder,tgltarget,prioritas) "; 
       $sql .= "values ('$iduser','$kodcustomer','$jnsbisnis','$tglorder','$fasorder','$desorder','$deslayan','$dikerjakan','$tgltarget', '$prioritas') ";
   }
   
   // Tambahkan id_parent ke query jika dipilih
   if ($id_parent_val !== null) {
       $sql = preg_replace('/\)\s*values\s*\(/i', ',id_parent) values (', $sql);
       $sql = preg_replace('/\)\s*$/', ','.$id_parent_val.') ', $sql);
   }
 
   try { 
	   $qins = $conn->prepare($sql);
	   $qins->execute(); 
  	   $newIdLog = $conn->lastInsertId();
	   $kodbrgpesan  = $kodbrg;
	   $pesan = "<font color=blue>New record <strong> </strong> created successfully</font>"; 

	   // Proses multiple file uploads (jika ada)
	   if ($newIdLog && isset($_FILES['file_upload']) && is_array($_FILES['file_upload']['name'])) {
		   $uploadDir = 'uploads/log/';
		   if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);
		   $allowedImage = ['jpg','jpeg','png','gif'];
		   $allowedVideo = ['mp4','avi','mov','wmv','mkv'];
		   $allowedExt  = array_merge($allowedImage, $allowedVideo);
		   $maxSize     = 50 * 1024 * 1024;
		   $descriptions = isset($_POST['file_desc']) ? $_POST['file_desc'] : [];
		   $savedFiles = [];
		   foreach ($_FILES['file_upload']['name'] as $i => $origName) {
			   if ($_FILES['file_upload']['error'][$i] !== UPLOAD_ERR_OK) continue;
			   if (empty($origName)) continue;
			   $tmpName = $_FILES['file_upload']['tmp_name'][$i];
			   $fileSize = $_FILES['file_upload']['size'][$i];
			   $fileExt = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
			   $desc = isset($descriptions[$i]) ? trim($descriptions[$i]) : '';
			   if (!in_array($fileExt, $allowedExt) || $fileSize > $maxSize) continue;
			   $newFileName = 'log_' . $newIdLog . '_' . date('Ymd_His') . '_' . $i . '.' . $fileExt;
			   if (move_uploaded_file($tmpName, $uploadDir . $newFileName)) {
				   $savedFiles[] = [
					   'filename'      => $newFileName,
					   'original_name' => $origName,
					   'file_size'     => $fileSize,
					   'file_type'     => in_array($fileExt, $allowedImage) ? 'image' : 'video',
					   'extension'     => $fileExt,
					   'description'   => $desc,
					   'upload_date'   => date('Y-m-d H:i:s'),
					   'uploaded_by'   => $iduser
				   ];
			   }
		   }
		   if (!empty($savedFiles)) {
			   // Pastikan kolom file_uploads ada
			   try { if ($conn->query("SHOW COLUMNS FROM tlog LIKE 'file_uploads'")->rowCount() == 0)
				   $conn->exec("ALTER TABLE tlog ADD COLUMN file_uploads TEXT NULL"); } catch (PDOException $e2) {}
			   $jsonFiles = json_encode($savedFiles);
			   $conn->prepare("UPDATE tlog SET file_uploads=? WHERE idlog=?")->execute([$jsonFiles, $newIdLog]);
		   }
	   }
	} catch (PDOException $e) {
		$pesan = "<font color=red>Error insert. Make sure data is correct.</font>";
	}
}

//DELETE 
if (isset($_GET['del'])) {
   $idlog = trim($_GET['idlog']);	 
   
   $istidakbolehdel = 0;	  
   $sqlcek = "select * from tlog WHERE idlog='$idlog'  and iduser='$iduser' "; 
    try { 
	   $qcek = $conn->prepare($sqlcek);
	   $qcek->execute(); 
	   if($qcek->rowCount() <1){ 
	      $istidakbolehdel = 1;
		  $pesan = "<font color=red>Log <strong>".$idlog." </strong> bukan milik user <strong>".$iduser." </strong></font><br>";
		  $pesan = $pesan. "<font color=red>Tidak boleh delete yang bukan lognya</font>";
	   }				
	}
	catch (PDOException $e)	{
		  $pesan =  "<font color=red>Error checking. Make sure <strong>".$idlog."</strong> is correct.</font>" ;
	}
    
	if ($istidakbolehdel == 0){
	   $sqld = "UPDATE tlog SET stsdel=1 WHERE idlog=$idlog  "; 
	   try { 
		   $qdel = $conn->prepare($sqld);
		   $qdel->execute();
		   $pesan = "<font color=blue>One record <strong>".$kodbrgpesan."</strong> deleted successfully</font>"; 
		}
		catch (PDOException $e)	{
			  $pesan =  "<font color=red>Error delete. Make sure <strong>idlog</strong> is correct.</font>" ;
		}
	}	 
}	

if (isset($_GET['para'])) { 
  $pesan = $_GET['pesan'];
}

?>	 
<!-- Tambahkan referensi ke library Bootstrap dan jQuery -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  
  <!-- Tambahkan referensi ke library Bootstrap Datepicker -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<style>
.breadcrumb {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.breadcrumb-title {
    flex: 1;
}
.excel-preview-table {
    max-height: 400px;
    overflow-y: auto;
    margin-top: 15px;
}
.excel-preview-table table {
    font-size: 12px;
}
.excel-preview-table th {
    background-color: #f5f5f5;
    position: sticky;
    top: 0;
    z-index: 10;
}
/* Quill editor — sesuaikan dengan Bootstrap form-control */
.ql-toolbar.ql-snow {
    border: 1px solid #ccc;
    border-bottom: 1px solid #e0e0e0;
    border-radius: 4px 4px 0 0;
    padding: 4px 8px;
    background: #fafafa;
    font-family: 'Lato', sans-serif;
}
.ql-container.ql-snow {
    border: 1px solid #ccc;
    border-top: none;
    border-radius: 0 0 4px 4px;
    min-height: 120px;
    background: #fff;
    font-size: 14px;
    font-family: 'Lato', sans-serif;
    color: #797979;
}
.ql-editor {
    min-height: 120px;
    padding: 8px 12px;
    line-height: 1.5;
    color: #797979;
}
.ql-editor.ql-blank::before {
    color: #aaa;
    font-style: normal;
    font-size: 14px;
    font-family: 'Lato', sans-serif;
}
/* Sama dengan *:focus {outline: none} di style.css */
.ql-container.ql-snow:focus-within,
.ql-toolbar.ql-snow:focus-within {
    outline: none;
    box-shadow: none;
    border-color: #ccc;
}
</style>

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
    
    <?php if ($show_excel_preview): ?>
    $('#importModal').modal('show');
    <?php endif; ?>
});
</script>

<script>
  $(document).ready(function() {
    $('.datepicker').datepicker({
      format: 'yyyy-mm-dd',
      autoclose: true
    });
  });
</script>

<script type="text/javascript"> 
function stopRKey(evt) { 
  var evt = (evt) ? evt : ((event) ? event : null); 
  var node = (evt.target) ? evt.target : ((evt.srcElement) ? evt.srcElement : null); 
  if ((evt.keyCode == 13) && (node.type=="text"))  {return false;} 
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
  if (form.idkategori.value == "blm_pilih"){
    alert("Kelompok barang belum dipilih!");
    form.idkategori.focus();
    return (false);
  }
  
  if (form.barcode.value == ""){
    alert("Barcode masih kosong!");
    form.barcode.focus();
    return (false);
  }
  if (form.nmbrg.value == ""){
    alert("Nama Barang masih kosong!");
    form.nmbrg.focus();
    return (false);
  }
  if (form.satuan1.value == ""){
    alert("Satuan grosir masih kosong!");
    form.satuan1.focus();
    return (false);
  }
  if (form.satuan2.value == ""){
    alert("Satuan ecer masih kosong!");
    form.satuan2.focus();
    return (false);
  }
  if (form.hrgbeli1.value <1 ){
    alert("Harga beli grosir tidak boleh nol!");
    form.hrgbeli1.focus();
    return (false);
  }
  if (form.hrgbeli2.value <1 ){
    alert("Harga beli ecer tidak boleh nol!");
    form.hrgbeli2.focus();
    return (false);
  }
  if (form.hrgjual1.value <1 ){
    alert("Harga jual grosir tidak boleh nol!");
    form.hrgjual1.focus();
    return (false);
  }
  if (form.hrgjual2.value <1 ){
    alert("Harga jual ecer tidak boleh nol!");
    form.hrgjual2.focus();
    return (false);
  }
return (true);
}
</script>

<script language="javascript">
function ConfirmDelete(){
  var x = confirm("Are you sure you want to delete?");
  if (x)
      return true;
  else
      return false;
}

function downloadTemplate() {
  window.location.href = '01_download_template.php';
}

function cancelPreview() {
  var form = document.createElement('form');
  form.method = 'POST';
  form.action = 'index.php?par=01';
  
  var input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'cancel_preview';
  input.value = 'Y';
  form.appendChild(input);
  
  document.body.appendChild(form);
  form.submit();
}
</script>

<script>
// Fungsi upload dinamis untuk Create Log
var uploadIndex01 = 0;
function tambahBaris01() {
	var div = document.createElement('div');
	div.style.cssText = 'display:flex;align-items:center;gap:8px;margin-bottom:8px;';
	div.innerHTML =
		'<input type="file" name="file_upload[]" accept="image/*,video/*" class="form-control" style="flex:1;">'+
		'<input type="text" name="file_desc[]" placeholder="Keterangan gambar..." class="form-control" style="flex:1;">'+
		'<button type="button" class="btn btn-danger btn-xs" onclick="this.parentNode.remove()"><i class="fa fa-times"></i></button>';
	document.getElementById('uploadRows01').appendChild(div);
	uploadIndex01++;
}
</script>

<body>
 
<div class="row"> 
    <ol class="breadcrumb">
	  <span class="breadcrumb-title"><i class="fa fa-home"></i> CREATE LOG</span>
	  <button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#importModal">
	      <i class="fa fa-upload"></i> Import Excel
	  </button>
	  <button type="button" class="btn btn-info btn-sm" style="margin-left: 8px;" onclick="downloadTemplate()">
	      <i class="fa fa-download"></i> Download Template
	  </button>
	</ol> 
	
	
	<div class="modal fade" id="importModal" tabindex="-1" role="dialog" aria-labelledby="importModalLabel">
	  <div class="modal-dialog modal-lg" role="document">
	    <div class="modal-content">
	      <div class="modal-header">
	        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
	          <span aria-hidden="true">&times;</span>
	        </button>
	        <h4 class="modal-title" id="importModalLabel">Import Data dari Excel</h4>
	      </div>
	      
	      <?php if (!$show_excel_preview): ?>
	      <form method="POST" enctype="multipart/form-data" action="index.php?par=01">
	        <div class="modal-body">
	          <div class="form-group">
	            <label>Pilih File Excel <span class="text-danger">*</span></label>
	            <input type="file" name="excel_file" class="form-control" accept=".xlsx,.xls" required>
	            <small class="text-dark">Format file: .xlsx atau .xls | Ukuran maksimal: 5MB</small>
	          </div>
	          <div class="alert alert-info">
	            <strong>Keterangan Header Tabel pada File:</strong><br>
	            <small>• <strong>No:</strong> Nomor urut (angka)</small><br>
              <small>• <strong>Jenis Report:</strong> A: ADMINISTRATION, B: BUSINESS, D: DEVELOPING, M: MAINTENANCE</small><br>
	            <small>• <strong>Mitra:</strong> Mitra / Customer (harus ada di database)</small><br>
	            <small>• <strong>Tanggal Order:</strong> Format YYYY-MM-DD atau gunakan format tanggal Excel</small><br>
	            <small>• <strong>Tanggal Selesai:</strong> Format YYYY-MM-DD atau gunakan format tanggal Excel</small><br>
              <small>• <strong>Dikerjakan Oleh:</strong> ID User (harus ada di database)</small><br>
              <small>• <strong>Prioritas:</strong> Sangat Tinggi/Tinggi/Biasa atau 1/2/3</small><br>
	            <small>• <strong>Order Melalui:</strong> Sarana order (WA/Email/Telp)</small><br>
	            <small>• <strong>Uraian Order:</strong> Deskripsi order (max 1000 karakter)</small><br>
	            <small>• <strong>Aktivitas Layanan:</strong> Aktivitas layanan (max 2000 karakter)</small><br><br>	            
	            <strong class="text-danger">⚠ Catatan Penting:</strong><br>
	            <small class="text-danger">• Header harus sama persis seperti contoh (case sensitive)</small><br>
	            <small class="text-danger">• Customer dan User harus sudah terdaftar di database</small><br>
	            <small class="text-danger">• Data akan divalidasi sebelum disimpan</small><br>
	            <small class="text-danger">• Tanggal bisa menggunakan format Excel Date atau YYYY-MM-DD</small>
	          </div>
	        </div>
	        <div class="modal-footer">
	          <input type="hidden" name="import_excel" value="Y">
	          <button type="button" class="btn btn-default" data-dismiss="modal">Batal</button>
	          <button type="submit" class="btn btn-success">Preview Data</button>
	        </div>
	      </form>
	      <?php else: ?>
	      <form method="POST" action="index.php?par=01">
	        <div class="modal-body">
	          <div class="alert alert-info">
	            <strong>Preview Data:</strong><br>
	            <small>Periksa data di bawah ini sebelum mengimport. Total: <strong><?php echo count($excel_data); ?> baris</strong></small>
	          </div>
	          
	          <div class="excel-preview-table">
	            <table class="table table-bordered table-striped table-condensed">
	              <thead>
                  <tr>
                    <th>No</th>
	                  <th>Jenis Report</th>
	                  <th>Mitra</th>
	                  <th>Tanggal Order</th>
	                  <th>Tanggal Selesai</th>
	                  <th>Dikerjakan Oleh</th>
	                  <th>Prioritas</th>
	                  <th>Order Melalui</th>
	                  <th>Uraian Order</th>
	                  <th>Aktivitas Layanan</th>
	                </tr>
	              </thead>
	              <tbody>
	                <?php 
	                foreach ($excel_data as $row): 
	                    $prioritas_text = '';
	                    switch($row['prioritas']) {
	                        case '1': $prioritas_text = 'Sangat Tinggi'; break;
	                        case '2': $prioritas_text = 'Tinggi'; break;
	                        case '3': $prioritas_text = 'Biasa'; break;
	                        default: $prioritas_text = $row['prioritas'];
	                    }
	                ?>
                  <tr>
                    <td><?php echo htmlspecialchars($row['nomor']); ?></td>
                    <td><?php echo htmlspecialchars($row['jnsbisnis']); ?></td>
	                  <td><?php echo htmlspecialchars($row['kodcustomer']); ?></td>
	                  <td><?php echo htmlspecialchars($row['tglorder']); ?></td>
	                  <td><?php echo htmlspecialchars($row['tgltarget']); ?></td>
	                  <td><?php echo htmlspecialchars($row['dikerjakan']); ?></td>
	                  <td><?php echo $prioritas_text; ?></td>
	                  <td><?php echo htmlspecialchars($row['fasorder']); ?></td>
	                  <td><small><?php echo htmlspecialchars(substr($row['desorder'], 0, 50)) . (strlen($row['desorder']) > 50 ? '...' : ''); ?></small></td>
	                  <td><small><?php echo htmlspecialchars(substr($row['deslayan'], 0, 50)) . (strlen($row['deslayan']) > 50 ? '...' : ''); ?></small></td>
	                </tr>
                  </tr>
	                <?php endforeach; ?>
	              </tbody>
	            </table>
	          </div>
	        </div>
	        <div class="modal-footer">
	          <input type="hidden" name="excel_data_json" value='<?php echo htmlspecialchars(json_encode($excel_data)); ?>'>
	          <input type="hidden" name="confirm_import" value="Y">
	          <button type="button" class="btn btn-default" onclick="cancelPreview()">
	            <i class="fa fa-times"></i> Batal
	          </button>
	          <button type="submit" class="btn btn-primary">
	            <i class="fa fa-check"></i> Import Semua Data
	          </button>
	        </div>
	      </form>
	      <?php endif; ?>
	      
	    </div>
	  </div>
	</div>
	
	<section class="panel">
	  <header class="panel-heading">
		  
		  <form role="form" id="form_create_log" method="POST" action="index.php?par=01" enctype="multipart/form-data"> 
		  
			 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Kelompok Bisnis</label>	
				  
				   <select name="jnsbisnis" id="jnsbisnis" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
					<option value="" selected disabled>-- Pilih Jenis Bisnis --</option>
					<option value="A">A:Administration</option>
				    <option value="B">B:Business</option>
					<option value="D">D:Developing</option>
					<option value="M">M:Maintenance</option>
					</select> 
			 </div>	
			 
		    <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
				  <label>Customer / Mitra</label>	
				  <?php 
				  $kodcustomer  = "";
				  if (isset($_GET['kodcustomer'])) { 
				      $kodcustomer = $_GET['kodcustomer'];
				  }
				  ?>
				   <select name="kodcustomer" id="kodcustomer" class="select2 form-control"  placeholder="Customer..."    onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> 
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
			 
			    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3">
			      <div class="form-group">
			        <label>Tanggal Order</label>
			        <input name="tglorder" id="dp1" size="7" class="form-control" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {tgltarget.focus()}" >
			      </div>
			    </div>
			    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-3 ">
			      <div class="form-group">
			        <label>Target Selesai</label>
			        <input name="tgltarget" id="dp1" size="7" class="form-control datepicker" value="<?php echo $tglini; ?>" onKeyPress="if (event.keyCode==13) {fasorder.focus()}" >
			      </div>
			    </div>
			    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
			     <label>Dikerjakan Oleh</label>	
						  <?php 
						  $dikerjakan = "";
						  if (isset($_GET['dikerjakan'])) { 
						      $dikerjakan = $_GET['dikerjakan'];
						  }
						  ?>
						  <select name="dikerjakan" id="dikerjakan" class="form-control"  placeholder="Dikerjakan Oleh"    onKeyPress="if (event.keyCode==13) {tglorder.focus()}"> 
							<option value="" disabled>-- Pilih User --</option>
								<?php
								$qk = $conn->prepare("SELECT * FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC "); 
								$qk->execute(); 
								while($rsk = $qk->fetch()){ 
								   $selected = ($dikerjakan == $rsk['iduser']) ? 'SELECTED' : (($iduser == $rsk['iduser'] && empty($dikerjakan)) ? 'SELECTED' : '');
								   echo "<option value=".$rsk['iduser']." $selected>".$rsk['nama']."</option>\n"; 
								}
								?>
							</select> 
			    </div>
				<div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
			      <div class="form-group">
			        <label>Status Prioritas</label>
			        <div class="search-box-material">
						<select name="prioritas" id="prioritas" class="form-control" onKeyPress="if (event.keyCode==13) {kodcustomer.focus()}">
							<option value="" selected disabled>-- Pilih Prioritas --</option>
							<option value="1">Sangat Tinggi</option>
				    		<option value="2">Tinggi</option>
							<option value="3">Biasa</option>
						</select> 
			        </div>
			      </div>
			     </div>	
			    <div class="col-xs-12 col-sm-6 col-md-6 col-lg-2 ">
			      <div class="form-group">
			        <label>Order Melalui</label>
			        <div class="search-box-material">
			          <input name="fasorder" onKeyUp="this.value = this.value.toUpperCase();" class="form-control" id="fasorder" type="text1" step="any" autocomplete="off" placeholder="Tulis sarana (WA/Email/Telp) dan nama pengorder..." value="">
			          <div class="result1"></div>
			        </div>
			      </div>
			     </div>
					 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
						  <label>Sprint (Opsional)</label>
						  <select name="idsprint" class="form-control">
							<option value="">-- Tanpa Sprint --</option>
							<?php
							try {
								$qsprint = $conn->prepare("SELECT idsprint, judul FROM tsprint WHERE status='aktif' ORDER BY idsprint DESC");
								$qsprint->execute();
								while($rssprint = $qsprint->fetch()) {
									echo "<option value='".$rssprint['idsprint']."'>".$rssprint['judul']."</option>\n";
								}
							} catch (PDOException $e) {}
							?>
						  </select>
					 </div>
					 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-3">
						  <label>Subtask dari (Opsional)</label>
						  <select name="id_parent" class="select2 form-control">
							<option value="">-- Bukan Subtask --</option>
							<?php
							try {
								$qparent = $conn->prepare("SELECT t.idlog, t.desorder, c.nmcustomer FROM tlog t LEFT JOIN rcustomer c ON t.kodcustomer = c.kodcustomer WHERE t.stsdel = 0 AND t.id_parent IS NULL ORDER BY t.idlog DESC LIMIT 100");
								$qparent->execute();
								while($rsparent = $qparent->fetch()) {
									$parentLabel = '#' . $rsparent['idlog'] . ' - ' . mb_substr(strip_tags($rsparent['desorder']), 0, 50) . ' (' . $rsparent['nmcustomer'] . ')';
									echo "<option value='".$rsparent['idlog']."'>".htmlspecialchars($parentLabel)."</option>\n";
								}
							} catch (PDOException $e) {}
							?>
						  </select>
					 </div>
					 <div class="clearfix"></div>
					 <div class="form-group col-xs-12 col-sm-6 col-md-6 col-lg-6">
						  <label>Uraian Order</label>	
						  <input type="hidden" name="desorder" id="desorder">
						  <div id="desorder_editor"></div>
					 </div>
					 <div class="col-xs-12 col-sm-6 col-md-6 col-lg-6">
						  <div class="form-group">
						    <label>Aktivitas Layanan</label>	
						    <input type="hidden" name="deslayan" id="deslayan">
						    <div id="deslayan_editor"></div>
						  </div>
					 </div>
					 <div class="clearfix"></div>
					 <!-- Upload Gambar/File Bukti -->
					 <div class="form-group col-xs-12" style="margin-top:8px;">
						<label><i class="fa fa-upload"></i> Upload Gambar/File Bukti <small class="text-muted">(opsional)</small></label>
						<div id="uploadRows01"></div>
						<button type="button" class="btn btn-default btn-sm" onclick="tambahBaris01()">
							<i class="fa fa-plus"></i> Tambah File
						</button>
						<small class="help-block">Foto (JPG, JPEG, PNG, GIF) / Video (MP4, AVI, MOV, WMV, MKV). Maks 50MB per file.</small>
					 </div>
					 <div class="col-xs-12" style="margin-top: 10px; text-align: right;">
						    <input type="hidden" name="par" id="par" value="01">
						    <input type="hidden" name="ins" id="ins" value="Y">
						    <button type="submit" name="submit" class="btn btn-primary" value="Y" id="btn_insert_log">Insert Data</button>
						    <button type="reset" class="btn btn-danger" id="btn_reset_log">Reset</button>
					 </div>
			    
		  </form>
		  <div class="clearfix">   </div>
		   
		  <h4><font color="red"><?php echo $pesan;?></font></h4>
	  </header>
	  <section class="content">
	  <div align="center">ORDER HARI INI  
	  </div>
	  <div class="box-body">
	   <table id="contoh" class="table table-bordered table-striped table-hover"> 
		  <thead>
        <tr>
          <th>No</th>
	        <th>Jenis Report</th>
	        <th>Mitra</th>
	        <th>Tanggal Order</th>
	        <th>Tanggal Selesai</th>
	        <th>Dikerjakan Oleh</th>
	        <th>Prioritas</th>
	        <th>Order Melalui</th>
	        <th>Uraian Order</th>
	        <th>Aktivitas Layanan</th>
          <th>Delete</th>
	      </tr>
	    </thead>
		  <tbody>
		  <div id="show-product">
          </div>
		  <?php 
			   $tglsekarang  = date('Y-m-d'); 
			   try { 
			        $sql = $conn->prepare("SELECT * from tlog where tglorder='$tglsekarang' and stsdel=0 order by idlog"); 
					$sql->execute();	 				
					$no=1;
					while($rs = $sql->fetch()) { 
						$prioritas="";
						$prioritas=$rs['prioritas'];
						switch ($prioritas) {
							case '1':
								$prioritas="Sangat Tinggi";
								break;
							case '2':
								$prioritas="Tinggi";
								break;
							case '3':
								$prioritas="Biasa";
								break;
						}
						// Siapkan preview HTML untuk tabel
						$desorder_html = stripslashes($rs['desorder']);
						$deslayan_html = stripslashes($rs['deslayan']);

						$desorder_plain = trim(strip_tags($desorder_html));
						$deslayan_plain = trim(strip_tags($deslayan_html));
						$desorder_long = mb_strlen($desorder_plain) > 100 || stripos($desorder_html, '<img') !== false;
						$deslayan_long  = mb_strlen($deslayan_plain) > 100 || stripos($deslayan_html, '<img') !== false;

						$row_id = $rs['idlog'];
					?>
						<tr>
						  <td align="center"><font size="-1"><?php echo $no; ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['jnsbisnis']); ?>
						    <?php if (!empty($rs['id_parent'])): ?>
						      <br><span class="label label-info" style="font-size:9px; margin-top:4px; display:inline-block;">↳ Subtask dari #<?php echo $rs['id_parent']; ?></span>
						    <?php endif; ?>
						  </font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['kodcustomer']); ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['tglorder']); ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['tgltarget']); ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['userorder']); ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($prioritas); ?></font></td>
						  <td><font size="-1"><?php echo htmlspecialchars($rs['fasorder']); ?></font></td>
						  <td><font size="-1">
						    <div class="rich-preview"><?php echo $desorder_html; ?></div>
						    <?php if ($desorder_long): ?>
						      <a href="#" class="btn-lihat-konten" data-target="#konten_desorder_<?php echo $row_id; ?>" data-title="Uraian Order" title="Lihat selengkapnya"><i class="fa fa-expand text-primary"></i> selengkapnya</a>
						      <div id="konten_desorder_<?php echo $row_id; ?>" style="display:none;"><?php echo $desorder_html; ?></div>
						    <?php endif; ?>
						  </font></td>
						  <td><font size="-1">
						    <div class="rich-preview"><?php echo $deslayan_html; ?></div>
						    <?php if ($deslayan_long): ?>
						      <a href="#" class="btn-lihat-konten" data-target="#konten_deslayan_<?php echo $row_id; ?>" data-title="Aktivitas Layanan" title="Lihat selengkapnya"><i class="fa fa-expand text-primary"></i> selengkapnya</a>
						      <div id="konten_deslayan_<?php echo $row_id; ?>" style="display:none;"><?php echo $deslayan_html; ?></div>
						    <?php endif; ?>
						  </font></td>
						  <td>
						    <form method="GET" action="index.php">
						      <input type="hidden" name="idlog" value="<?php echo $rs['idlog']; ?>">
						      <input type="hidden" name="par" value="01">
						      <input type="hidden" name="del" value="Y">
						      <button type="submit" class="btn btn-danger btn-xs" onclick="return ConfirmDelete();" <?php if ($iduser != $rs['iduser']) echo 'disabled'; ?>>Delete</button>
						    </form>
						  </td>
						</tr>
					<?php
						$no++;
					} 
			   }
			   catch (PDOException $e)	{
				  echo "  
				   <tr>
					  <td></td>
					  <td></td>
					  <td></td>
					  <td></td> 
					  <td></td> 
					</tr>  ";
			  }
		  ?> 
			
		  </tbody>
		</table>
	  </div>
	  </section>
	</section>
   
</div> 

<!-- Modal lightbox untuk gambar -->
<div class="modal fade" id="imgModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title">Gambar</h4>
      </div>
      <div class="modal-body text-center">
        <img id="imgModalSrc" src="" style="max-width:100%;">
      </div>
    </div>
  </div>
</div>

<style>
/* Gambar di dalam tabel diperkecil dan bisa diklik */
#contoh td img {
    max-width: 80px;
    max-height: 60px;
    cursor: pointer;
    border: 1px solid #ddd;
    border-radius: 3px;
    padding: 2px;
    transition: opacity 0.2s;
}
#contoh td img:hover {
    opacity: 0.75;
}
/* Modal gambar — kompak, tidak terlalu besar */
#imgModal .modal-body {
    max-height: 60vh;
    overflow-y: auto;
    padding: 15px;
    text-align: center;
}
#imgModal .modal-body img {
    max-width: 400px;
    max-height: 350px;
    height: auto;
    display: block;
    margin: 0 auto 10px auto;
    border-radius: 4px;
    border: 1px solid #ddd;
}
/* Rich HTML preview di tabel */
.rich-preview {
    max-height: 80px;
    overflow: hidden;
    font-size: 12px;
    line-height: 1.4;
    color: #555;
}
.rich-preview p { margin: 0 0 2px 0; }
.rich-preview strong, .rich-preview b { font-weight: bold; }
.rich-preview em, .rich-preview i { font-style: italic; }
.rich-preview u { text-decoration: underline; }
.rich-preview a { color: #337ab7; text-decoration: underline; }
.rich-preview ul, .rich-preview ol { margin: 0 0 2px 14px; padding: 0; }
.rich-preview img { max-width: 60px; max-height: 50px; border: 1px solid #ddd; border-radius: 3px; vertical-align: middle; }
</style>

<script>
// Klik ikon gambar → ambil HTML dari hidden div → tampilkan di modal dengan judul sesuai
$(document).on('click', '.btn-lihat-konten', function(e) {
    e.preventDefault();
    var target = $(this).data('target');
    var title = $(this).data('title') || 'Konten Lengkap';
    var html = $(target).html();
    $('#imgModal .modal-title').text(title);
    $('#imgModal .modal-body').html(html);
    $('#imgModal').modal('show');
});
$('#imgModal').on('hidden.bs.modal', function() {
    $('#imgModal .modal-body').html('');
});
</script>


<script>
// ========== QUILL RICH EDITOR — CREATE LOG ==========
(function() {
    var toolbarOptions = [
        [{ 'header': [1, 2, 3, false] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        ['link', 'image'],
        [{ 'color': [] }, { 'background': [] }],
        ['clean']
    ];

    // Editor: Uraian Order
    var quill_desorder = new Quill('#desorder_editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions },
        placeholder: 'Uraian Order...'
    });

    // Editor: Aktivitas Layanan
    var quill_deslayan = new Quill('#deslayan_editor', {
        theme: 'snow',
        modules: { toolbar: toolbarOptions },
        placeholder: 'Tulis Tanggal dan Aktifitasnya...'
    });

    // Helper: sinkronkan KEDUA editor ke hidden input sekaligus
    function syncEditors() {
        var inputDesorder = document.getElementById('desorder');
        var inputDeslayan = document.getElementById('deslayan');
        if (inputDesorder) inputDesorder.value = quill_desorder.root.innerHTML;
        if (inputDeslayan) inputDeslayan.value = quill_deslayan.root.innerHTML;
    }

    // Sync real-time setiap kali user mengetik di editor manapun
    quill_desorder.on('text-change', syncEditors);
    quill_deslayan.on('text-change', syncEditors);

    // Sync eksplisit saat tombol Insert di-klik (sebelum form submit)
    var btn_insert = document.getElementById('btn_insert_log');
    if (btn_insert) {
        btn_insert.addEventListener('click', syncEditors);
    }

    // Sync backup saat form di-submit (untuk semua metode submit)
    var form_el = document.getElementById('form_create_log');
    if (form_el) {
        form_el.addEventListener('submit', syncEditors);
    }

    // Reset: bersihkan editor dan hidden input
    var btn_reset = document.getElementById('btn_reset_log');
    if (btn_reset) {
        btn_reset.addEventListener('click', function() {
            quill_desorder.setText('');
            quill_deslayan.setText('');
            syncEditors();
        });
    }
})();
</script>

</body>

</html>