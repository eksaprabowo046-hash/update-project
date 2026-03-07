<?php
include "islogin.php";	  

?>
<script type="text/javascript" src="dropdowntabfiles/dropdowntabs.js">
</script>
<!-- CSS for Drop Down Tabs Menu #1 -->
<link rel="stylesheet" type="text/css" href="dropdowntabfiles/ddcolortabs.css" />

<!-- CSS for Drop Down Tabs Menu #2 -->
<link rel="stylesheet" type="text/css" href="dropdowntabfiles/bluetabs.css" />

<!-- CSS for Drop Down Tabs Menu #3 -->
<link rel="stylesheet" type="text/css" href="dropdowntabfiles/slidingdoors.css" />


<!-- CSS for Drop Down Tabs Menu #4 -->
<link rel="stylesheet" type="text/css" href="dropdowntabfiles/glowtabs.css" />

<!-- CSS for Drop Down Tabs Menu #5 -->
<link rel="stylesheet" type="text/css" href="dropdowntabfiles/halfmoontabs.css" />

<style>
/* Flyout submenu styling */
.dropmenudiv_e .has-submenu {
  position: relative;
}
.dropmenudiv_e .has-submenu > a {
  display: block;
  text-indent: 5px;
  font: bold 12px Verdana;
  border: 0 solid #657f27;
  border-bottom-width: 1px;
  padding: 4px 15px 4px 5px;
  text-decoration: none;
  color: #000;
  background-color: #b8e6ff;
  cursor: pointer;
}
.dropmenudiv_e .has-submenu > a::after {
  content: '\25B6';
  float: right;
  font-size: 9px;
  margin-top: 2px;
  margin-right: 2px;
}
.dropmenudiv_e .has-submenu > a:hover {
  background-color: #4AFFFF;
}
.dropmenudiv_e .submenu {
  display: none;
  position: absolute;
  left: 100%;
  top: 0;
  min-width: 180px;
  background-color: #88D9FF;
  border: 1px solid #657f27;
  z-index: 200;
}
.dropmenudiv_e .submenu a {
  display: block;
  text-indent: 5px;
  font: normal 12px Verdana;
  border: 0 solid #657f27;
  border-bottom-width: 1px;
  padding: 3px 0;
  text-decoration: none;
  color: black;
}
.dropmenudiv_e .submenu a:hover {
  background-color: #4AFFFF;
}
.dropmenudiv_e .has-submenu:hover > .submenu {
  display: block;
}
/* Nested submenu (level 2) */
.dropmenudiv_e .submenu .has-submenu {
  position: relative;
}
.dropmenudiv_e .submenu .has-submenu > a {
  display: block;
  text-indent: 5px;
  font: bold 12px Verdana;
  border: 0 solid #657f27;
  border-bottom-width: 1px;
  padding: 3px 15px 3px 5px;
  text-decoration: none;
  color: #000;
  background-color: #b8e6ff;
  cursor: pointer;
}
.dropmenudiv_e .submenu .has-submenu > a::after {
  content: '\25B6';
  float: right;
  font-size: 9px;
  margin-top: 2px;
  margin-right: 2px;
}
.dropmenudiv_e .submenu .has-submenu > a:hover {
  background-color: #4AFFFF;
}
.dropmenudiv_e .submenu .has-submenu .submenu {
  display: none;
  position: absolute;
  left: 100%;
  top: 0;
  min-width: 180px;
  background-color: #88D9FF;
  border: 1px solid #657f27;
  z-index: 300;
}
.dropmenudiv_e .submenu .has-submenu:hover > .submenu {
  display: block;
}
</style>

<body>
<?php
// Fetch per-user permissions from tbl_hak_akses
$hak_akses_user = array();
try {
    $stmt_hak = $conn->prepare("SELECT menu_nama, aktif FROM tbl_hak_akses WHERE iduser = ?");
    $stmt_hak->execute([$iduser]);
    while ($row_hak = $stmt_hak->fetch(PDO::FETCH_ASSOC)) {
        $hak_akses_user[$row_hak['menu_nama']] = $row_hak['aktif'];
    }
    
    // Jika user belum punya data hak akses, auto-insert default
    if (empty($hak_akses_user)) {
        $default_off_staff = ['Invoice', 'Laporan Invoice', 'Penggajian', 'Keuangan', 'Pinjaman'];
        $hidden_staff_menus = ['Input User'];
        $checkMaster = $conn->query("SHOW TABLES LIKE 'setting_hak_akses'");
        if ($checkMaster->rowCount() > 0) {
            $masterMenus = $conn->query("SELECT menu_nama FROM setting_hak_akses ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($masterMenus as $m) {
                if ($kodjab != 1 && in_array($m, $hidden_staff_menus)) continue;
                $aktif_val = ($kodjab != 1 && $kodjab != 2 && in_array($m, $default_off_staff)) ? 0 : 1;
                $conn->prepare("INSERT IGNORE INTO tbl_hak_akses (iduser, menu_nama, aktif) VALUES (?, ?, ?)")
                     ->execute([$iduser, $m, $aktif_val]);
                $hak_akses_user[$m] = $aktif_val;
            }
        }
    }
} catch (Exception $e) {
    // Fallback if table doesn't exist yet
}

// Function to check access per user
function cek_akses($menu, $hak_akses_user) {
    if (!isset($hak_akses_user[$menu])) {
        // No record = user belum dikonfigurasi, default aktif (backward compatible)
        return true; 
    }
    return ($hak_akses_user[$menu] == 1);
}

// Check if any Pengaturan sub-menu is accessible
$show_pengaturan = (
    ($kodjab == 1 || $kodjab == 2) || // Input User for Direktur & GM
    cek_akses('About', $hak_akses_user) ||
    cek_akses('Laporan Kehadiran', $hak_akses_user) ||
    cek_akses('Perizinan', $hak_akses_user)
);

// Check if any Keuangan sub-menu is accessible
$show_keuangan = (
    cek_akses('Invoice', $hak_akses_user) ||
    cek_akses('Laporan Invoice', $hak_akses_user) ||
    cek_akses('Penggajian', $hak_akses_user) ||
    cek_akses('Keuangan', $hak_akses_user) ||
    ($kodjab == 1 || $kodjab == 2) || // Direktur/GM selalu bisa
    cek_akses('Pinjaman', $hak_akses_user) // User dengan akses Pinjaman
);

// Check if any Produksi sub-menu is accessible
$show_produksi = (
    cek_akses('Log', $hak_akses_user) ||
    cek_akses('Laporan Log', $hak_akses_user) ||
    cek_akses('Lembur', $hak_akses_user) ||
    cek_akses('Sprint', $hak_akses_user)
);
?>

<div id="moonmenu" class="halfmoon">
<ul>
  <?php // ========== MENU PENGATURAN (Grouped) ========== ?>
  <?php if ($show_pengaturan) { ?>
  <li><a href=# rel=dropmenu_pengaturan>Pengaturan</a></li>
  <?php } ?>

  <?php // ========== MENU KEUANGAN (Grouped) ========== ?>
  <?php if ($show_keuangan) { ?>
  <li><a href=# rel=dropmenu_keuangan_group>Keuangan</a></li>
  <?php } ?>

  <?php // ========== MENU PRODUKSI (Grouped) ========== ?>
  <?php if ($show_produksi) { ?>
  <li><a href=# rel=dropmenu_produksi>Produksi</a></li>
  <?php } ?>

  <?php // ========== MENU MARKETING ========== ?>
  <?php if (cek_akses('Implementasi', $hak_akses_user)) { ?>
  <li><a href=# rel=dropmenu6_e>Marketing</a></li>
  <?php } ?>

  <?php // ========== KALENDER ========== ?>
  <?php if (cek_akses('Kalender', $hak_akses_user)) { ?>
  <li><a href="index.php?par=47" rel=dropmenu_kalender>Kalender</a></li>
  <?php } ?>

</ul> 
</div>


<?php // ========== DROPDOWN: PENGATURAN (Flyout Submenus) ========== ?>
<?php if ($show_pengaturan) { ?>
<div id='dropmenu_pengaturan' class='dropmenudiv_e' style='width: 170px;' align='left'>

<?php if ($kodjab == 1 || $kodjab == 2) { ?>
<div class="has-submenu">
  <a href="#">Input User</a>
  <div class="submenu">
    <a href="index.php?par=44">Data User</a>
    <a href="index.php?par=46">Input Divisi</a>
    <a href="index.php?par=50">Input Jabatan</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Laporan Kehadiran', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Kehadiran</a>
  <div class="submenu">
    <a href='index.php?par=20'>Setting Kehadiran</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Perizinan', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Perizinan</a>
  <div class="submenu">
    <a href="index.php?par=26">Permohonan Izin</a>
    <?php if ($kodjab == 1 || $kodjab == 2) { ?>
    <a href="index.php?par=27">Approval Izin</a>
    <?php } ?>
    <a href="index.php?par=28">Laporan Izin</a>
  </div>
</div>
<?php } ?>



<?php if (cek_akses('About', $hak_akses_user)) { ?>
<a href="index.php?par=42" style="display: block; text-indent: 5px; font: bold 12px Verdana; border: 0 solid #657f27; border-bottom-width: 1px; padding: 4px 5px; text-decoration: none; color: #000; background-color: #b8e6ff;">About</a>
<?php } ?>

</div>
<?php } ?>


<?php 
if (cek_akses('Implementasi', $hak_akses_user)) {
?>
<div id='dropmenu6_e' class='dropmenudiv_e' style='width: 170px;' align='left'>
<div class="has-submenu">
  <a href="#">Administrasi</a>
  <div class="submenu">
    <?php if (cek_akses('Customer', $hak_akses_user)) { ?>
    <a href='#'>Kode Surat</a>
    <a href='index.php?par=06'>Customer</a>
    <a href='#'>-</a>
    <?php } ?>
  </div>
</div>
<div class="has-submenu">
  <a href="#">Pasca Jual</a>
  <div class="submenu">
    <div class="has-submenu">
      <a href="#">Implementasi</a>
      <div class="submenu">
        <a href='index.php?par=31'>Create Implementasi</a>
        <a href='index.php?par=33'>Update Implementasi</a>
        <a href='index.php?par=34'>Laporan Implementasi</a>
      </div>
    </div>
  </div>
</div>
<div class="has-submenu">
  <a href="#">Pemasaran</a>
  <div class="submenu">
    <a href='#'>Daftar Content</a>
    <a href='#'>Tugas Pendukung</a>
  </div>
</div>
<div class="has-submenu">
  <a href="#">Penjualan</a>
  <div class="submenu">
    <a href='#'>List Prospek</a>
    <a href='#'>Laporan Penjualan</a>
  </div>
</div>
</div>
<?php 
}
?>

<?php // ========== DROPDOWN: PRODUKSI (Flyout Submenus) ========== ?>
<?php if ($show_produksi) { ?>
<div id='dropmenu_produksi' class='dropmenudiv_e' style='width: 170px;' align='left'>

<?php if (cek_akses('Log', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Log</a>
  <div class="submenu">
    <a href='index.php?par=01'>Create Log</a>
    <a href='index.php?par=02'>Hapus Log</a>
    <a href='index.php?par=03'>Update Log</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Laporan Log', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Laporan Log</a>
  <div class="submenu">
    <a href='index.php?par=04'>Laporan Log</a>
    <a href='index.php?par=07'>Laporan Produksi</a>
    <a href='index.php?par=25'>Laporan Testing</a>
    <a href='index.php?par=24'>Laporan Update</a>
    <a href='index.php?par=08'>Laporan User</a>
    <a href='index.php?par=30'>Laporan Belum Dinilai</a>
    <a href='index.php?par=29'>Grafik Laporan</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Lembur', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Lembur</a>
  <div class="submenu">
    <a href='index.php?par=35'>Pengajuan Lembur</a>
    <?php if ($kodjab == 1 || $kodjab == 2) { ?>
    <a href='index.php?par=35a'>Approval SPL</a>
    <?php } ?>
    <a href='index.php?par=36'>Pertanggungjawaban Lembur</a>
    <a href='index.php?par=37'>Laporan Lembur</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Sprint', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Sprint</a>
  <div class="submenu">
    <a href='index.php?par=53'>Input Sprint</a>
    <a href='index.php?par=54'>Laporan Sprint</a>
  </div>
</div>
<?php } ?>

</div>
<?php } ?>

<?php // ========== DROPDOWN: KEUANGAN (Flyout Submenus) ========== ?>
<?php if ($show_keuangan) { ?>
<div id='dropmenu_keuangan_group' class='dropmenudiv_e' style='width: 170px;' align='left'>

<?php if (cek_akses('Invoice', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Invoice</a>
  <div class="submenu">
    <a href='index.php?par=09'>Input Invoice</a>
    <a href='index.php?par=11'>Input EFaktur</a>
    <a href='index.php?par=10'>Invoice Payment</a>
    <a href='index.php?par=12'>EFaktur Payment</a>
    <a href='index.php?par=13'>Input PPh</a>
    <a href='index.php?par=14'>PPh Payment</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Laporan Invoice', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Laporan Invoice</a>
  <div class="submenu">
    <a href='index.php?par=15'>Invoice per Tanggal</a>
    <a href='index.php?par=22'>Invoice per Customer</a>
    <a href='index.php?par=16'>EFaktur</a>
    <a href='index.php?par=17'>PPh</a>
    <a href='index.php?par=18'>Invoice Expired</a>
    <a href='index.php?par=19'>Invoice Belum Bayar</a>
  </div>
</div>
<?php } ?>

<?php if (cek_akses('Penggajian', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Penggajian</a>
  <div class="submenu">
    <a href='index.php?par=38'>Penggajian Bulanan</a>
    <a href='index.php?par=56'>Manajemen THR</a>
    <a href='index.php?par=51'>Laporan Penggajian</a>

  </div>
</div>
<?php } ?>

<?php if (cek_akses('Keuangan', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Kas / Keuangan</a>
  <div class="submenu">
    <a href='index.php?par=39'>Create Akun</a>
    <a href='index.php?par=40a'>Create Kantong</a>
    <a href='index.php?par=40'>Pencatatan Kas</a>
    <a href='index.php?par=41'>Laporan Arus Kas</a>
  </div>
</div>
<?php } ?>

<?php if ($kodjab == 1 || $kodjab == 2 || cek_akses('Pinjaman', $hak_akses_user)) { ?>
<div class="has-submenu">
  <a href="#">Pinjaman</a>
  <div class="submenu">
    <a href='index.php?par=48'>Pengajuan Pinjaman</a>
    <?php if ($kodjab == 1 || $kodjab == 2) { ?>
    <a href='index.php?par=49'>Approval Pinjaman</a>
    <a href='index.php?par=52'>Laporan Pinjaman</a>
    <?php } ?>
  </div>
</div>
<?php } ?>

</div>
<?php } ?>


<script type="text/javascript">
//SYNTAX: tabdropdown.init("menu_id", [integer OR "auto"])
tabdropdown.init("moonmenu")
</script>


<br>
</body>
</html>
