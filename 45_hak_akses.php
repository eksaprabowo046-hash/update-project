<?php
// 45_hak_akses.php
// Manage per-user menu access rights
session_start();
include "dbase.php";
include "islogin.php";

// AJAX: Ambil data hak akses user dalam format JSON (dipanggil dari halaman User Management)
if (isset($_GET['get_akses']) && isset($_GET['iduser'])) {
    $iduser_target = $_GET['iduser'];
    $result = [];
    try {
        $stmt = $conn->prepare("SELECT menu_nama, aktif FROM tbl_hak_akses WHERE iduser = ?");
        $stmt->execute([$iduser_target]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['menu_nama']] = intval($row['aktif']);
        }
        
        // Jika user belum punya data hak akses sama sekali, auto-insert default
        if (empty($result)) {
            // Ambil kodjab user
            $stmt_user = $conn->prepare("SELECT kodjab FROM ruser WHERE iduser = ?");
            $stmt_user->execute([$iduser_target]);
            $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
            $user_kodjab = $user_data ? $user_data['kodjab'] : 3;
            
            $default_off_staff = ['Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan', 'Pinjaman'];
            $hidden_staff_menus = ['Input User'];
            $allMenusMaster = $conn->query("SELECT menu_nama FROM setting_hak_akses ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($allMenusMaster as $menu) {
                if ($user_kodjab != 1 && in_array($menu, $hidden_staff_menus)) continue;
                
                if ($user_kodjab != 1 && $user_kodjab != 2 && in_array($menu, $default_off_staff)) {
                    $aktif = 0;
                } else {
                    $aktif = 1;
                }
                
                $stmt_ins = $conn->prepare("INSERT IGNORE INTO tbl_hak_akses (iduser, menu_nama, aktif) VALUES (?, ?, ?)");
                $stmt_ins->execute([$iduser_target, $menu, $aktif]);
                $result[$menu] = $aktif;
            }
        }
    } catch (PDOException $e) {
        // Abaikan jika tabel belum ada
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Only Admin (kodjab=1) can access this page
if ($kodjab != 1) {
    echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
    exit;
}

// 1. Auto-create tbl_hak_akses if not exists
try {
    $checkTable = $conn->query("SHOW TABLES LIKE 'tbl_hak_akses'");
    if ($checkTable->rowCount() == 0) {
        $conn->exec("CREATE TABLE tbl_hak_akses (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            iduser VARCHAR(50) NOT NULL,
            menu_nama VARCHAR(100) NOT NULL,
            aktif TINYINT(1) DEFAULT 1 COMMENT '1=boleh akses, 0=tidak',
            UNIQUE KEY unik_user_menu (iduser, menu_nama)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");
    }
} catch (PDOException $e) {
    // ignore
}

// 2. Ensure setting_hak_akses has all menus (master menu list)
$defaultMenus = [
    'Log',
    'Input User',
    'Implementasi',
    'Invoice',
    'Laporan Log',
    'Laporan Invoice',
    'Laporan Kehadiran',
    'Perizinan',
    'Lembur',
    'Penggajian',
    'Keuangan',
    'About',
    'Kalender',
    'Customer',
    'Dashboard',
    'Pinjaman'
];

try {
    // Ensure table exists
    $checkTable = $conn->query("SHOW TABLES LIKE 'setting_hak_akses'");
    if ($checkTable->rowCount() == 0) {
        $conn->exec("CREATE TABLE setting_hak_akses (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            menu_nama VARCHAR(100) NOT NULL,
            akses_admin TINYINT(1) DEFAULT 1,
            akses_staff TINYINT(1) DEFAULT 1
        )");
    }
    
    foreach ($defaultMenus as $menu) {
        $conn->exec("INSERT IGNORE INTO setting_hak_akses (menu_nama) VALUES ('" . addslashes($menu) . "')");
    }
    
    // Hapus menu yang tidak perlu dikonfigurasi via hak akses (hardcode di kode)
    $conn->exec("DELETE FROM setting_hak_akses WHERE menu_nama = 'Hak Akses'");
    $conn->exec("DELETE FROM tbl_hak_akses WHERE menu_nama = 'Hak Akses'");
} catch (PDOException $e) {
    // ignore
}

// 3. Handle Save
$pesan = '';
if (isset($_POST['save_akses'])) {
    $target_user = $_POST['target_user'];
    $menus_aktif = isset($_POST['menus']) ? $_POST['menus'] : [];
    
    // Get all menus from master
    $allMenus = $conn->query("SELECT menu_nama FROM setting_hak_akses ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($allMenus as $menu) {
        $aktif = in_array($menu, $menus_aktif) ? 1 : 0;
        
        // UPSERT: Insert or Update
        $stmt = $conn->prepare("INSERT INTO tbl_hak_akses (iduser, menu_nama, aktif) VALUES (?, ?, ?) 
                                ON DUPLICATE KEY UPDATE aktif = ?");
        $stmt->execute([$target_user, $menu, $aktif, $aktif]);
    }
    
    $pesan = "<div class='alert alert-success'><i class='fa fa-check-circle'></i> Hak akses untuk <strong>" . htmlspecialchars($target_user) . "</strong> berhasil disimpan! Memuat ulang menu...</div>";
    // Redirect agar menutab.php reload dengan hak akses terbaru
    echo "<script>setTimeout(function(){ window.location.href = 'index.php?par=45&user=" . urlencode($target_user) . "'; }, 1000);</script>";
}

// 4. Get selected user (from GET for dropdown change, or from POST after save)
$selectedUser = '';
if (isset($_POST['save_akses'])) {
    $selectedUser = $_POST['target_user'];
} elseif (isset($_GET['user'])) {
    $selectedUser = $_GET['user'];
}

// 5. Fetch all users
$users = $conn->query("SELECT iduser, nama, kodjab, divisi FROM ruser WHERE stsaktif = 1 ORDER BY nik ASC, nama ASC")->fetchAll(PDO::FETCH_ASSOC);

// 6. Fetch all menus from master
$menus = $conn->query("SELECT * FROM setting_hak_akses ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

// 7. Fetch selected user's current access
$userAccess = [];
if ($selectedUser) {
    $stmt = $conn->prepare("SELECT menu_nama, aktif FROM tbl_hak_akses WHERE iduser = ?");
    $stmt->execute([$selectedUser]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $userAccess[$row['menu_nama']] = $row['aktif'];
    }
}

// Menus yang disembunyikan total untuk Staff (tidak muncul sama sekali)
$hidden_staff = ['Input User'];

// Get selected user's name and kodjab for display
$selectedUserName = '';
$selectedUserKodjab = 0;
foreach ($users as $u) {
    if ($u['iduser'] == $selectedUser) {
        $jabatan = ($u['kodjab'] == 1) ? "Admin" : (($u['kodjab'] == 3) ? "Staff" : "No Access");
        $divisi = !empty($u['divisi']) ? $u['divisi'] : '-';
        $selectedUserName = $u['nama'] . ' (' . $jabatan . ' - ' . $divisi . ')';
        $selectedUserKodjab = $u['kodjab'];
        break;
    }
}
?>

<style>
.modal {
  text-align: center;
  padding: 0!important;
}
.modal:before {
  content: '';
  display: inline-block;
  height: 100%;
  vertical-align: middle;
  margin-right: -4px;
}
.modal-dialog {
  display: inline-block;
  text-align: left;
  vertical-align: middle;
  width: 90%;
  max-width: 700px;
}
.user-selector {
    background: #f5f5f5;
    padding: 15px;
    border-radius: 5px;
    margin-bottom: 20px;
    border: 1px solid #ddd;
}
.menu-table .checkbox-cell {
    text-align: center;
    vertical-align: middle;
    width: 100px;
}
.menu-table .menu-name {
    vertical-align: middle;
    font-weight: bold;
}
.btn-select-all {
    margin-right: 5px;
    margin-bottom: 5px;
}
.user-info-badge {
    display: inline-block;
    padding: 5px 12px;
    border-radius: 3px;
    font-size: 13px;
    margin-left: 10px;
}
</style>

<div class="row">
    <ol class="breadcrumb">
        <li><i class="fa fa-home"></i> KELOLA HAK AKSES</li>
    </ol>
    <section class="panel">
        <header class="panel-heading">
            <strong>Pengaturan Hak Akses Per User</strong>
            <br><small>Pilih user, lalu centang menu yang boleh diakses.</small>
        </header>
        <div class="panel-body">
            <?php if ($pesan) echo $pesan; ?>
            
            <!-- USER SELECTOR -->
            <div class="user-selector">
                <form method="GET" action="index.php">
                    <input type="hidden" name="par" value="45">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label><i class="fa fa-user"></i> Pilih User:</label>
                        <div class="row">
                            <div class="col-md-6">
                                <select name="user" class="form-control" onchange="this.form.submit()">
                                    <option value="">-- Pilih User --</option>
                                    <?php foreach ($users as $u) { 
                                        $jab = ($u['kodjab'] == 1) ? "Admin" : (($u['kodjab'] == 3) ? "Staff" : "No Access");
                                        $div = !empty($u['divisi']) ? $u['divisi'] : '-';
                                        $selected = ($u['iduser'] == $selectedUser) ? 'selected' : '';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($u['iduser']); ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($u['nama']); ?> (<?php echo $jab; ?> - <?php echo htmlspecialchars($div); ?>) — <?php echo htmlspecialchars($u['iduser']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <?php if ($selectedUser) { ?>
            <!-- ACCESS CONFIGURATION -->
            <form method="POST" action="index.php?par=45">
                <input type="hidden" name="target_user" value="<?php echo htmlspecialchars($selectedUser); ?>">
                
                <div class="alert alert-info">
                    <i class="fa fa-info-circle"></i> 
                    Mengelola akses untuk: <strong><?php echo htmlspecialchars($selectedUserName); ?></strong>
                    <?php if (empty($userAccess)) { ?>
                        <br><small><i class="fa fa-exclamation-triangle"></i> User ini belum punya pengaturan hak akses. Semua menu dicentang default (aktif). Klik <strong>Simpan</strong> untuk menyimpan.</small>
                    <?php } ?>
                </div>

                <!-- Quick Actions -->
                <div style="margin-bottom: 15px;">
                    <button type="button" class="btn btn-success btn-sm btn-select-all" onclick="toggleAll(true)">
                        <i class="fa fa-check-square-o"></i> Centang Semua
                    </button>
                    <button type="button" class="btn btn-danger btn-sm btn-select-all" onclick="toggleAll(false)">
                        <i class="fa fa-square-o"></i> Hapus Semua
                    </button>
                </div>

                <table class="table table-bordered table-striped table-hover menu-table">
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">No</th>
                            <th>Nama Menu</th>
                            <th style="text-align: center; width: 120px;">Akses</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        foreach ($menus as $row) { 
                            $menu = $row['menu_nama'];
                            
                            // Skip menus that are completely hidden for Staff
                            if ($selectedUserKodjab != 1 && in_array($menu, $hidden_staff)) {
                                continue;
                            }
                            
                            // Default OFF untuk Staff pada menu tertentu
                            $default_off_staff = ['Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan', 'Pinjaman'];
                            
                            // If user has existing config, use it. If no config yet, apply defaults.
                            if (isset($userAccess[$menu])) {
                                $isChecked = ($userAccess[$menu] == 1);
                            } else {
                                // Staff: menu tertentu default nonaktif
                                if ($selectedUserKodjab != 1 && in_array($menu, $default_off_staff)) {
                                    $isChecked = false;
                                } else {
                                    $isChecked = true;
                                }
                            }
                        ?>
                        <tr>
                            <td align="center" style="vertical-align: middle;"><?php echo $no++; ?></td>
                            <td class="menu-name"><?php echo htmlspecialchars($menu); ?></td>
                            <td class="checkbox-cell">
                                <label style="margin-bottom: 0; cursor: pointer;">
                                    <input type="checkbox" name="menus[]" value="<?php echo htmlspecialchars($menu); ?>" 
                                           <?php echo $isChecked ? 'checked' : ''; ?>
                                           onchange="updateLabel(this)">
                                    <span class="<?php echo $isChecked ? 'text-success' : 'text-danger'; ?>" style="font-weight: bold;">
                                        <?php echo $isChecked ? 'Aktif' : 'Nonaktif'; ?>
                                    </span>
                                </label>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>

                <div style="margin-top: 15px; text-align: right;">
                    <button type="submit" name="save_akses" class="btn btn-primary btn-lg">
                        <i class="fa fa-save"></i> Simpan Hak Akses
                    </button>
                </div>
            </form>
            <?php } else { ?>
            <!-- No user selected message -->
            <div class="alert alert-warning" style="text-align: center; padding: 40px;">
                <i class="fa fa-hand-o-up" style="font-size: 48px; display: block; margin-bottom: 15px;"></i>
                <h4>Silakan pilih user terlebih dahulu</h4>
                <p>Pilih user dari dropdown di atas untuk mengatur hak aksesnya.</p>
            </div>
            <?php } ?>

            <div class="alert alert-info" style="margin-top: 20px;">
                <strong>Catatan:</strong> 
                <br> - Hak akses sekarang dikonfigurasi <strong>per user</strong>, bukan per jabatan.
                <br> - Centang menu yang boleh diakses oleh user tersebut.
                <br> - User baru yang belum dikonfigurasi akan mendapat akses <strong>default semua aktif</strong>.
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
function toggleAll(state) {
    $('input[name="menus[]"]').each(function() {
        $(this).prop('checked', state);
        updateLabel(this);
    });
}

function updateLabel(checkbox) {
    var label = $(checkbox).next('span');
    if ($(checkbox).is(':checked')) {
        label.text('Aktif').removeClass('text-danger').addClass('text-success');
    } else {
        label.text('Nonaktif').removeClass('text-success').addClass('text-danger');
    }
}
</script>
