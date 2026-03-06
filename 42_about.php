<?php
// About - Profile DSI & Dokumen (dengan Upload per Divisi)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($conn)) { include "dbase.php"; }
include "islogin.php"; 

$pesan_upload = "";
$divisiList = ['Dokumen Internal', 'Dokumen Marketing', 'Dokumen Produksi', 'Dokumen Akuntansi'];

// Migrasi: ubah kolom divisi dari ENUM ke VARCHAR jika diperlukan
try {
    // Cek tipe kolom divisi
    $colInfo = $conn->query("SHOW COLUMNS FROM tdokumen_about LIKE 'divisi'")->fetch(PDO::FETCH_ASSOC);
    if ($colInfo && strpos($colInfo['Type'], 'enum') !== false) {
        // Ubah dari ENUM ke VARCHAR agar bisa simpan "Dokumen Internal" dll
        $conn->exec("ALTER TABLE tdokumen_about MODIFY COLUMN divisi VARCHAR(100) NOT NULL DEFAULT ''");
        // Remap old ENUM values ke format baru
        $conn->exec("UPDATE tdokumen_about SET divisi = 'Dokumen Internal' WHERE divisi = 'Internal'");
        $conn->exec("UPDATE tdokumen_about SET divisi = 'Dokumen Marketing' WHERE divisi = 'Marketing'");
        $conn->exec("UPDATE tdokumen_about SET divisi = 'Dokumen Produksi' WHERE divisi = 'Produksi'");
    }
    // Fix empty divisi
    $conn->exec("UPDATE tdokumen_about SET divisi = 'Dokumen Internal' WHERE divisi IS NULL OR TRIM(divisi) = ''");
    // Fix old SOP labels
    $conn->exec("UPDATE tdokumen_about SET divisi = REPLACE(divisi, 'SOP ', 'Dokumen ') WHERE divisi LIKE 'SOP %'");
} catch (Exception $e) {}
$kodjab = $_SESSION['DEFAULT_KODJAB'] ?? '';
$iduser = $_SESSION['DEFAULT_IDUSER'] ?? '';

// Ambil divisi user dari ruser
$userDivisi = '';
try {
    $stmtDiv = $conn->prepare("SELECT divisi FROM ruser WHERE iduser = :id");
    $stmtDiv->execute([':id' => $iduser]);
    $rowDiv = $stmtDiv->fetch(PDO::FETCH_ASSOC);
    if ($rowDiv) $userDivisi = $rowDiv['divisi'];
} catch (PDOException $e) {}

// Mapping divisi user ke kategori Dokumen
$divisiToDok = [
    'Marketing & Bisnis' => 'Dokumen Marketing',
    'Marketing'          => 'Dokumen Marketing',
    'Produksi'           => 'Dokumen Produksi',
    'Akunting'           => 'Dokumen Akuntansi',
    'Akuntansi'          => 'Dokumen Akuntansi',
    'Management'         => 'Dokumen Internal',
    'Programmer'         => 'Dokumen Internal',
];

// Tentukan kategori Dokumen yang boleh user KELOLA (upload/hapus)
// Direktur & GM = semua, Manager = sesuai divisinya
$userDokCategories = [];
if ($kodjab == 1 || $kodjab == 2) {
    $userDokCategories = $divisiList;
} elseif ($kodjab == 3) {
    // Manager Marketing & Bisnis
    $userDokCategories = ['Dokumen Marketing'];
} elseif ($kodjab == 4) {
    // Manager Produksi
    $userDokCategories = ['Dokumen Produksi'];
}

// Tentukan kategori Dokumen yang boleh user LIHAT
// Direktur & GM = semua, Manager/Staff = Dokumen Internal + divisi sendiri + kategori upload-nya
$userViewCategories = [];
if ($kodjab == 1 || $kodjab == 2) {
    $userViewCategories = $divisiList;
} else {
    $userViewCategories = ['Dokumen Internal'];
    if (!empty($userDivisi) && isset($divisiToDok[$userDivisi])) {
        $dokUser = $divisiToDok[$userDivisi];
        if ($dokUser !== 'Dokumen Internal' && !in_array($dokUser, $userViewCategories)) {
            $userViewCategories[] = $dokUser;
        }
    }
    // Tambahkan kategori yang boleh dikelola (upload) ke view
    foreach ($userDokCategories as $cat) {
        if (!in_array($cat, $userViewCategories)) {
            $userViewCategories[] = $cat;
        }
    }
}

// ========== PROSES UPLOAD (user dengan hak akses) ==========
if (!empty($userDokCategories) && isset($_POST['upload_dok'])) {
    $nama_dok = trim($_POST['nama_dokumen'] ?? '');
    $divisi   = trim($_POST['divisi'] ?? '');
    $uploader = $_SESSION['DEFAULT_IDUSER'] ?? '';

    if (empty($nama_dok) || empty($divisi)) {
        $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Nama dokumen dan divisi wajib diisi!</div>";
    } elseif (!in_array($divisi, $userDokCategories)) {
        $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Anda tidak memiliki akses untuk menambah dokumen di kategori ini!</div>";;
    } elseif (!isset($_FILES['file_dok']) || $_FILES['file_dok']['error'] !== UPLOAD_ERR_OK) {
        $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> File wajib dipilih!</div>";
    } else {
        $file     = $_FILES['file_dok'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['pdf','doc','docx','xls','xlsx','ppt','pptx','jpg','jpeg','png'];
        $maxSize  = 10 * 1024 * 1024; // 10MB

        if (!in_array($ext, $allowed)) {
            $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Format file tidak diizinkan! (PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, JPG, PNG)</div>";
        } elseif ($file['size'] > $maxSize) {
            $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Ukuran file maksimal 10MB!</div>";
        } else {
            $uploadDir = 'uploads/dokumen/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $newName = 'dok_' . strtolower($divisi) . '_' . date('Ymd_His') . '_' . rand(100,999) . '.' . $ext;
            $target  = $uploadDir . $newName;

            if (move_uploaded_file($file['tmp_name'], $target)) {
                try {
                    // Use positional params (named :divisi was not binding correctly)
                    $stmt = $conn->prepare("INSERT INTO tdokumen_about (nama_dokumen, nama_file, divisi, uploaded_by) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$nama_dok, $newName, $divisi, $uploader]);
                    $lastId = $conn->lastInsertId();
                    
                    // Update divisi secara eksplisit untuk memastikan nilai tersimpan
                    $conn->prepare("UPDATE tdokumen_about SET divisi = ? WHERE id = ?")->execute([$divisi, $lastId]);
                    
                    $pesan_upload = "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Dokumen <strong>" . htmlspecialchars($nama_dok) . "</strong> berhasil diupload ke divisi <strong>{$divisi}</strong>!</div>";
                } catch (PDOException $e) {
                    unlink($target); // Hapus file upload jika insert database gagal
                    $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Error database: " . $e->getMessage() . "</div>";
                }
            } else {
                $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Gagal mengupload file!</div>";
            }
        }
    }
}

// ========== PROSES HAPUS (user dengan hak akses) ==========
if (!empty($userDokCategories) && isset($_POST['hapus_dok'])) {
    $id_hapus = $_POST['id_hapus'] ?? 0;
    try {
        // Ambil data file dan divisi untuk cek hak akses
        $stmt = $conn->prepare("SELECT nama_file, divisi FROM tdokumen_about WHERE id = :id");
        $stmt->execute([':id' => $id_hapus]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && in_array($row['divisi'], $userDokCategories)) {
            $filepath = 'uploads/dokumen/' . $row['nama_file'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            $stmt = $conn->prepare("DELETE FROM tdokumen_about WHERE id = :id");
            $stmt->execute([':id' => $id_hapus]);
            $pesan_upload = "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Dokumen berhasil dihapus!</div>";
        }
    } catch (PDOException $e) {
        $pesan_upload = "<div class='alert alert-danger'><i class='fa fa-times-circle'></i> Error: " . $e->getMessage() . "</div>";
    }
}

// ========== AMBIL DATA DOKUMEN ==========
$dokumen_per_divisi = [];
foreach ($divisiList as $d) {
    $dokumen_per_divisi[$d] = [];
}
try {
    $stmt = $conn->prepare("
        SELECT d.*, 
               CASE WHEN d.divisi IS NULL OR TRIM(d.divisi) = '' THEN 'Dokumen Internal' ELSE d.divisi END AS divisi_fix,
               r.nama AS nama_uploader 
        FROM tdokumen_about d 
        LEFT JOIN ruser r ON d.uploaded_by = r.iduser 
        ORDER BY divisi_fix, d.tgl_upload DESC
    ");
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $div = $row['divisi_fix'];
        if (isset($dokumen_per_divisi[$div])) {
            $dokumen_per_divisi[$div][] = $row;
        } else {
            // Fallback: masukkan ke Dokumen Internal
            $dokumen_per_divisi['Dokumen Internal'][] = $row;
        }
    }
} catch (PDOException $e) {
    // Tabel mungkin belum ada
}

// Warna badge per divisi
$divisiColors = [
    'Dokumen Internal'  => ['bg' => '#0d47a1', 'gradient' => 'linear-gradient(135deg, #0d47a1, #1565c0)', 'icon' => 'fa-briefcase'],
    'Dokumen Marketing' => ['bg' => '#0d47a1', 'gradient' => 'linear-gradient(135deg, #0d47a1, #1565c0)', 'icon' => 'fa-bullhorn'],
    'Dokumen Produksi'  => ['bg' => '#0d47a1', 'gradient' => 'linear-gradient(135deg, #0d47a1, #1565c0)', 'icon' => 'fa-cogs'],
    'Dokumen Akuntansi' => ['bg' => '#0d47a1', 'gradient' => 'linear-gradient(135deg, #0d47a1, #1565c0)', 'icon' => 'fa-money'],
];

// Icon berdasarkan ekstensi
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    switch ($ext) {
        case 'pdf': return '<i class="fa fa-file-pdf-o" style="color:#c0392b;"></i>';
        case 'doc': case 'docx': return '<i class="fa fa-file-word-o" style="color:#2980b9;"></i>';
        case 'xls': case 'xlsx': return '<i class="fa fa-file-excel-o" style="color:#27ae60;"></i>';
        case 'ppt': case 'pptx': return '<i class="fa fa-file-powerpoint-o" style="color:#d35400;"></i>';
        case 'jpg': case 'jpeg': case 'png': return '<i class="fa fa-file-image-o" style="color:#8e44ad;"></i>';
        default: return '<i class="fa fa-file-o" style="color:#7f8c8d;"></i>';
    }
}
?>

<style>
  .profile-card, .doc-card, .upload-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-bottom: 20px;
    overflow: hidden;
  }
  .profile-card .card-header {
    background: linear-gradient(135deg, #1a237e, #283593);
    color: #fff;
    padding: 15px 20px;
  }
  .profile-card .card-header h4 {
    margin: 0;
    font-weight: 400;
  }
  .profile-card .card-header small {
    color: #b3c6ff;
  }
  .profile-card .card-body {
    padding: 15px 20px;
  }
  .profile-table td {
    padding: 8px 12px;
    vertical-align: top;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
  }
  .profile-table td:first-child {
    font-weight: bold;
    width: 180px;
    color: #333;
    white-space: nowrap;
  }
  .profile-table td:nth-child(2) {
    width: 10px;
    text-align: center;
  }
  .profile-table tr:last-child td {
    border-bottom: none;
  }

  /* Upload Card */
  .upload-card .card-header {
    background: linear-gradient(135deg, #00695c, #00897b);
    color: #fff;
    padding: 15px 20px;
  }
  .upload-card .card-header h4 {
    margin: 0;
    font-weight: 400;
  }
  .upload-card .card-body {
    padding: 20px;
  }

  /* Divisi Section */
  .divisi-section {
    margin-bottom: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
    overflow: hidden;
  }
  .divisi-header {
    color: #fff;
    padding: 12px 20px;
    font-size: 14px;
    font-weight: 500;
  }
  .divisi-header i {
    margin-right: 8px;
  }
  .divisi-body {
    padding: 0;
  }
  .divisi-body table {
    margin-bottom: 0;
  }
  .divisi-body .empty-msg {
    padding: 15px 20px;
    color: #999;
    font-size: 13px;
    font-style: italic;
  }

  .btn-hapus-dok {
    padding: 2px 8px;
    font-size: 11px;
  }

  /* Pagination */
  .pagination-wrapper {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-top: 1px solid #eee;
    background: #fafafa;
    font-size: 13px;
  }
  .pagination-wrapper select {
    padding: 3px 8px;
    border: 1px solid #ccc;
    border-radius: 3px;
    font-size: 12px;
  }
  .pagination-wrapper .page-btns button {
    padding: 4px 10px;
    margin: 0 2px;
    border: 1px solid #ccc;
    background: #fff;
    border-radius: 3px;
    cursor: pointer;
    font-size: 12px;
  }
  .pagination-wrapper .page-btns button:hover {
    background: #e8e8e8;
  }
  .pagination-wrapper .page-btns button.active {
    background: #0d47a1;
    color: #fff;
    border-color: #0d47a1;
  }
  .pagination-wrapper .page-btns button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
</style>

<body>

<div class="row"> 
    <ol class="breadcrumb">
	  <li><i class="fa fa-info-circle"></i> ABOUT</li> 
	</ol> 

	<!-- ========== PROFILE PERUSAHAAN ========== -->
	<div class="profile-card">
	  <div class="card-header">
	    <h4>Profile Perusahaan</h4>
	    <small>PT. Duta Solusi Informatika</small>
	  </div>
	  <div class="card-body">
	    <table class="profile-table" width="100%">
		  <tr>
		    <td>Nama Perusahaan</td>
		    <td>:</td>
		    <td>PT. Duta Solusi Informatika (DSI)</td>
		  </tr>
		  <tr>
		    <td>Lokasi</td>
		    <td>:</td>
		    <td>Semarang, Jawa Tengah</td>
		  </tr>
		  <tr>
		    <td>Bidang Usaha</td>
		    <td>:</td>
		    <td>IT Inventory & Customs Compliance Solutions</td>
		  </tr>
		  <tr>
		    <td>Produk Utama</td>
		    <td>:</td>
		    <td>esikatERP (Web-based ERP System)</td>
		  </tr>
		  <tr>
		    <td>Keahlian</td>
		    <td>:</td>
		    <td>Bonded Zone (KABER), KITE, SEZ, Integrasi CEISA 4.0 & INSW</td>
		  </tr>
		  <tr>
		    <td>Pengalaman</td>
		    <td>:</td>
		    <td>10+ tahun di industri manufaktur</td>
		  </tr>
		  <tr>
		    <td>Sektor Klien</td>
		    <td>:</td>
		    <td>Tekstil, Garmen, Elektronik, Footwear</td>
		  </tr>
		  <tr>
		    <td>Website</td>
		    <td>:</td>
		    <td><a href="https://esikaterp.id" target="_blank">esikaterp.id <i class="fa fa-external-link"></i></a></td>
		  </tr>
		</table>
	  </div>
	</div>

	<!-- ========== FORM UPLOAD DOKUMEN (hanya kodjab 1) ========== -->
	<?php if (!empty($userDokCategories)): ?>
	<div class="upload-card">
	  <div class="card-header">
	    <h4><i class="fa fa-cloud-upload"></i> &nbsp;Upload Dokumen</h4>
	  </div>
	  <div class="card-body">
	    <?php echo $pesan_upload; ?>
	    <form method="POST" action="index.php?par=42" enctype="multipart/form-data">
	      <div class="row">
	        <div class="col-sm-4">
	          <div class="form-group">
	            <label>Kategori Dokumen <span style="color:red;">*</span></label>
	            <select name="divisi" class="form-control" required>
	              <option value="">-- Pilih Kategori Dokumen --</option>
	              <?php foreach ($userDokCategories as $d): ?>
	              <option value="<?= $d ?>"><?= $d ?></option>
	              <?php endforeach; ?>
	            </select>
	          </div>
	        </div>
	        <div class="col-sm-4">
	          <div class="form-group">
	            <label>Nama Dokumen <span style="color:red;">*</span></label>
	            <input type="text" name="nama_dokumen" class="form-control" placeholder="Contoh: Form Penyerahan Asset" required autocomplete="off">
	          </div>
	        </div>
	        <div class="col-sm-4">
	          <div class="form-group">
	            <label>Pilih File <span style="color:red;">*</span></label>
	            <input type="file" name="file_dok" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" required>
	            <small class="text-muted">PDF, DOC, XLS, PPT, JPG, PNG (Maks. 10MB)</small>
	          </div>
	        </div>
	      </div>
	      <div class="text-right">
	        <button type="submit" name="upload_dok" class="btn btn-primary">
	          <i class="fa fa-upload"></i> Upload
	        </button>
	      </div>
	    </form>
	  </div>
	</div>
	<?php endif; ?>

	<!-- ========== DAFTAR DOKUMEN PER DIVISI ========== -->
	<?php foreach ($userViewCategories as $divisi): 
	    $docs = $dokumen_per_divisi[$divisi];
	    $color = $divisiColors[$divisi];
	?>
	<div class="divisi-section">
	  <div class="divisi-header" style="background: <?= $color['gradient'] ?>;">
	    <i class="fa <?= $color['icon'] ?>"></i> <?= $divisi ?>
	    <span class="badge" style="background: rgba(255,255,255,0.25); margin-left: 8px;"><?= count($docs) ?> dokumen</span>
	  </div>
	  <div class="divisi-body">
	    <?php if (empty($docs)): ?>
	      <div class="empty-msg"><i class="fa fa-info-circle"></i> Belum ada dokumen untuk <?= $divisi ?>.</div>
	    <?php else: 
	      $sectionId = 'sop_' . preg_replace('/[^a-z0-9]/', '', strtolower($divisi));
	    ?>
	      <table class="table table-striped table-hover" style="margin-bottom:0;" id="table_<?= $sectionId ?>">
	        <thead>
	          <tr>
	            <th width="5%" style="text-align:center;">#</th>
	            <th width="35%">Nama Dokumen</th>
	            <th width="20%">Tanggal Upload</th>
	            <th width="20%">Diupload Oleh</th>
	            <th width="20%" style="text-align:center;">Aksi</th>
	          </tr>
	        </thead>
	        <tbody>
	        <?php $no = 1; foreach ($docs as $doc): ?>
	          <tr>
	            <td style="text-align:center;"><?= $no++ ?></td>
	            <td>
	              <?= getFileIcon($doc['nama_file']) ?>
	              &nbsp;<?= htmlspecialchars($doc['nama_dokumen']) ?>
	            </td>
	            <td><?= date('d/m/Y H:i', strtotime($doc['tgl_upload'])) ?></td>
	            <td><?= htmlspecialchars($doc['nama_uploader'] ?? $doc['uploaded_by'] ?? '-') ?></td>
	            <td style="text-align:center;">
	              <a href="uploads/dokumen/<?= htmlspecialchars($doc['nama_file']) ?>" 
	                 target="_blank" class="btn btn-xs btn-success" title="Download / Lihat">
	                <i class="fa fa-download"></i> Buka
	              </a>
	              <?php if (in_array($divisi, $userDokCategories)): ?>
	              <button type="button" class="btn btn-xs btn-danger" 
	                      onclick="hapusDokumen(<?= $doc['id'] ?>, '<?= htmlspecialchars(addslashes($doc['nama_dokumen'])) ?>')" title="Hapus">
	                <i class="fa fa-trash-o"></i> Hapus
	              </button>
	              <?php endif; ?>
	            </td>
	          </tr>
	        <?php endforeach; ?>
	        </tbody>
	      </table>
	      <div class="pagination-wrapper" id="pag_<?= $sectionId ?>">
	        <div>
	          Tampilkan 
	          <select onchange="changePageSize('<?= $sectionId ?>', this.value)">
	            <option value="5" selected>5</option>
	            <option value="10">10</option>
	            <option value="25">25</option>
	            <option value="50">50</option>
	          </select>
	          data per halaman
	        </div>
	        <div>
	          <span id="info_<?= $sectionId ?>"></span>
	        </div>
	        <div class="page-btns" id="btns_<?= $sectionId ?>"></div>
	      </div>
	    <?php endif; ?>
	  </div>
	</div>
	<?php endforeach; ?>

	<p style="margin-top:5px;"><font size=-1 color='#999'><i class="fa fa-info-circle"></i> Klik "Buka" untuk membuka dokumen di tab baru.</font></p>

</div> 

<?php if (!empty($userDokCategories)): ?>
<!-- Form tersembunyi untuk hapus -->
<form id="form_hapus_dok" method="POST" action="index.php?par=42" style="display:none;">
    <input type="hidden" name="hapus_dok" value="1">
    <input type="hidden" name="id_hapus" id="id_hapus_dok">
</form>
<?php endif; ?>

<script>
function hapusDokumen(id, nama) {
    if (confirm('Yakin ingin menghapus dokumen "' + nama + '"?')) {
        document.getElementById('id_hapus_dok').value = id;
        document.getElementById('form_hapus_dok').submit();
    }
}

// ===== Pagination =====
var pagState = {};

function initPagination(sectionId, pageSize) {
    var table = document.getElementById('table_' + sectionId);
    if (!table) return;
    var rows = table.querySelectorAll('tbody tr');
    pagState[sectionId] = {
        rows: rows,
        total: rows.length,
        pageSize: pageSize || 5,
        currentPage: 1
    };
    renderPage(sectionId);
}

function renderPage(sectionId) {
    var s = pagState[sectionId];
    if (!s) return;
    var totalPages = Math.ceil(s.total / s.pageSize);
    if (totalPages < 1) totalPages = 1;
    if (s.currentPage > totalPages) s.currentPage = totalPages;

    var start = (s.currentPage - 1) * s.pageSize;
    var end = start + s.pageSize;

    for (var i = 0; i < s.rows.length; i++) {
        s.rows[i].style.display = (i >= start && i < end) ? '' : 'none';
        // Update nomor urut
        s.rows[i].querySelector('td').textContent = (i + 1);
    }

    // Info
    var infoEl = document.getElementById('info_' + sectionId);
    if (infoEl) {
        var showStart = s.total > 0 ? start + 1 : 0;
        var showEnd = Math.min(end, s.total);
        infoEl.textContent = showStart + '-' + showEnd + ' dari ' + s.total;
    }

    // Buttons
    var btnsEl = document.getElementById('btns_' + sectionId);
    if (btnsEl) {
        var html = '';
        html += '<button ' + (s.currentPage <= 1 ? 'disabled' : '') + ' onclick="goPage(\'' + sectionId + '\',' + (s.currentPage - 1) + ')">&laquo;</button>';
        for (var p = 1; p <= totalPages; p++) {
            html += '<button class="' + (p === s.currentPage ? 'active' : '') + '" onclick="goPage(\'' + sectionId + '\',' + p + ')">' + p + '</button>';
        }
        html += '<button ' + (s.currentPage >= totalPages ? 'disabled' : '') + ' onclick="goPage(\'' + sectionId + '\',' + (s.currentPage + 1) + ')">&raquo;</button>';
        btnsEl.innerHTML = html;
    }
}

function goPage(sectionId, page) {
    if (!pagState[sectionId]) return;
    pagState[sectionId].currentPage = page;
    renderPage(sectionId);
}

function changePageSize(sectionId, size) {
    if (!pagState[sectionId]) return;
    pagState[sectionId].pageSize = parseInt(size);
    pagState[sectionId].currentPage = 1;
    renderPage(sectionId);
}

// Init semua tabel saat halaman load
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($divisiList as $dInit): 
        $sid = preg_replace('/[^a-z0-9]/', '', strtolower($dInit));
        if (!empty($dokumen_per_divisi[$dInit])): ?>
    initPagination('<?= $sid ?>', 5);
    <?php endif; endforeach; ?>
});
</script>

</body>

</html>
