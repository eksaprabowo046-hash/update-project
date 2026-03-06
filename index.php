<?php
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

error_reporting(E_COMPILE_ERROR | E_RECOVERABLE_ERROR | E_ERROR | E_CORE_ERROR);

//Start the Session
session_start();


function islogin()
{
  $hasil = false;
  if (isset($_SESSION['DEFAULT_ISLOGIN'])) {
    if ($_SESSION['DEFAULT_ISLOGIN'] == "mK&%~%h#867H4z") {
      $hasil = true;
    } else {
      $hasil = false;
    }
  } else {
    $hasil = false;
  }
  return $hasil;
}

function nameKolomNilai($value){
  $name = "";
  if ($value == 1) {
    $name = "Evaluasi";
  } elseif ($value == 2) {
    $name = "Kurang Baik";
  } elseif ($value == 3) {
    $name = "Normal";
  } elseif ($value == 4) {
    $name = "Baik";
  } elseif ($value == 5) {
    $name = "Sangat Baik";
  }

  return $name;
}

include "dbase.php";
$iduser   = $_SESSION['DEFAULT_IDUSER'];
$kodjab   = $_SESSION['DEFAULT_KODJAB'];

// Load hak akses user untuk pengecekan di header
$hak_akses_header = array();
try {
    $stmt_h = $conn->prepare("SELECT menu_nama, aktif FROM tbl_hak_akses WHERE iduser = ?");
    $stmt_h->execute([$iduser]);
    while ($row_h = $stmt_h->fetch(PDO::FETCH_ASSOC)) {
        $hak_akses_header[$row_h['menu_nama']] = $row_h['aktif'];
    }
} catch (Exception $e) {}
$show_dashboard = !isset($hak_akses_header['Dashboard']) || $hak_akses_header['Dashboard'] == 1;



// Intercept AJAX requests untuk kalender (sebelum HTML di-output)
if (isset($_GET['par']) && $_GET['par'] == '47' && isset($_REQUEST['action']) && $_REQUEST['action'] != '') {
    include "47_kalender.php";
    exit;
}

// Intercept Excel export untuk Laporan Penggajian (sebelum HTML di-output)
if (isset($_GET['par']) && $_GET['par'] == '51' && isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Cek hak akses Penggajian
    if (isset($hak_akses_header['Penggajian']) && $hak_akses_header['Penggajian'] == 0) {
        die('Anda tidak memiliki akses!');
    }
    include "51_lap_penggajian.php";
    exit;
}

// Intercept AJAX requests untuk detail kehadiran
if (isset($_GET['par']) && $_GET['par'] == '20' && isset($_GET['ajax_detail'])) {
    include "20_kehadiran.php";
    exit;
}

// Intercept AJAX requests untuk Sprint (plan items, stats, files)
if (isset($_GET['par']) && $_GET['par'] == '53' && (isset($_GET['ajax_plans']) || isset($_GET['ajax_stats']) || isset($_GET['ajax_log_detail']) || isset($_GET['ajax_sprint_files']))) {
    include "53_input_sprint.php";
    exit;
}

?>

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Creative - Bootstrap 3 Responsive Admin Template">
  <meta name="author" content="GeeksLabs">
  <meta name="keyword" content="Creative, Dashboard, Admin, Template, Theme, Bootstrap, Responsive, Retina, Minimal">
  <link rel="shortcut icon" href="img/favicon.png">

  <title>DSI LOG BOOK</title>

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet">
  <link href="css/dataTables.bootstrap.min.css" rel="stylesheet">
  <!-- bootstrap theme  -->
  <link href="css/bootstrap-theme.css" rel="stylesheet">
  <!--external css-->
  <!-- font icon -->
  <link href="css/elegant-icons-style.css" rel="stylesheet" />
  <link href="assets/font-awesome/css/font-awesome.min.css" rel="stylesheet" />
  <!-- datepicker -->
  <link href="css/bootstrap-datepicker.css" rel="stylesheet" />
  <link href="assets/bootstrap-select/bootstrap-select.min.css" rel="stylesheet" />
  <link href="assets/select2/select2.css" rel="stylesheet" />

  <!-- Custom styles-->
  <link href="css/style.css" rel="stylesheet">
  <link href="css/style-responsive.css" rel="stylesheet" />
  
  <script src="js/jquery.js"></script>
</head>

<body>
  <!-- container section start -->
  <section id="container">
    <!--main content start-->
    <section id="main">
      <table width="100%" style="padding: 1% 2%;">

        <td width="100%" style="padding: 1% 2%;">
          <!--overview start-->
          <table width="100%" style="padding: 1% 2%;" border="0" cellspacing="0" cellpadding="0">
            <tr>
              <th scope="row">
                <!----- AWAL HEADER ----->
                <?php include 'headerkantor.php'; ?>
                <table width="100%" style="padding: 0 2%;" border="0">
                  <tr>
                    <td>
                      <div align="left">
                        <font color="#006600" size="2">
                          &nbsp;User :
                          <?php
                          $username = $_SESSION['DEFAULT_USERNAME'];
                          if (islogin() == true) {
                            echo " <font color=#FF0000>" . $username . " </font> ";
                          } else {
                            echo " Belum Login";
                          }
                          ?>
                        </font>
                      </div>
                    </td>
                    <td>
                      <div align="right">
                        <font size="2"> <a href="javascript:history.back()"> Back </a>| <a href="index.php">Home</a>
                          <?php
                          if (islogin() == true) {
                          ?>
                            <?php if ($show_dashboard) { ?>| <a href="index.php?par=43" target="_parent">Dashboard </a><?php } ?>
                            | <a href="index.php?par=00a" target="_parent">Ubah Password </a>
                            | <a href="signout.php" target="_parent">Logout </a>
                          <?php
                          }
                          ?>
                        </font>
                      </div>
                    </td>
                  </tr>
                </table>
                <!----- AKHIR HEADER ----->
              </th>
            </tr>
            <table width="100%" style="padding: 1% 2%;" border="0">
              <tr>
                <th scope="row">
                  <!----- AWAL BODY ----->
                  <?php
                  //Awal Awal Menu
                  // Halaman login gagal harus bisa diakses TANPA login
                  if (isset($_GET['par']) && $_GET['par'] == "x1b") {
                      include "logingagal.php";
                  } else if (islogin() == true) {
                    include "menutab.php";

                    // === SERVER-SIDE ACCESS CONTROL ===
                    // Mapping par -> menu_nama di tbl_hak_akses
                    $par_menu_map = [
                        '01' => 'Log', '01_edt' => 'Log', '02' => 'Log', '03' => 'Log', '03_edt' => 'Log',
                        '04' => 'Laporan Log', '07' => 'Laporan Log', '08' => 'Laporan Log',
                        '24' => 'Laporan Log', '25' => 'Laporan Log', '29' => 'Laporan Log', '30' => 'Laporan Log',
                        '06' => 'Customer',
                        '09' => 'Invoice', '10' => 'Invoice', '11' => 'Invoice', '12' => 'Invoice',
                        '13' => 'Invoice', '14' => 'Invoice',
                        '15' => 'Laporan Invoice', '16' => 'Laporan Invoice', '17' => 'Laporan Invoice',
                        '18' => 'Laporan Invoice', '19' => 'Laporan Invoice', '22' => 'Laporan Invoice',
                        '20' => 'Laporan Kehadiran', '21' => 'Laporan Kehadiran', '23' => 'Laporan Kehadiran',
                        '26' => 'Perizinan', '27' => 'Perizinan', '28' => 'Perizinan',
                        '31' => 'Implementasi', '33' => 'Implementasi', '34' => 'Implementasi',
                        '35' => 'Lembur', '35a' => 'Lembur', '36' => 'Lembur', '37' => 'Lembur',
                        '38' => 'Penggajian', '51' => 'Penggajian',
                        '39' => 'Keuangan', '40' => 'Keuangan', '41' => 'Keuangan',
                        '42' => 'About',
                        '43' => 'Dashboard',
                        '47' => 'Kalender',
                        '53' => 'Sprint', '54' => 'Sprint',
                    ];

                    // Halaman khusus admin (Direktur/Manager only)
                    $admin_only_pages = ['44', '45', '46', '50'];

                    // Cek hak akses sebelum routing
                    $akses_ditolak = false;

                    if (isset($_GET['par'])) {
                        // Cek halaman admin (kodjab)
                        if (in_array($_GET['par'], $admin_only_pages) && $kodjab != 1 && $kodjab != 2) {
                            $akses_ditolak = true;
                        }
                        // Cek hak akses menu
                        if (!$akses_ditolak && isset($par_menu_map[$_GET['par']])) {
                            $menu_required = $par_menu_map[$_GET['par']];
                            if (isset($hak_akses_header[$menu_required]) && $hak_akses_header[$menu_required] == 0) {
                                $akses_ditolak = true;
                            }
                        }
                    }

                    if ($akses_ditolak) {
                        echo "<script>alert('Anda tidak memiliki akses ke halaman ini!'); window.location='index.php';</script>";
                    } else if (! isset($_GET['par'])) {
                      include "home.php";
                    } else if ($_GET['par'] == "00") {
                      include "home.php";
                    } else if ($_GET['par'] == "00a") {
                      include "ubahpwd.php";
                    } else if ($_GET['par'] == "44") {
                      include "44_user_management.php";
                    } else if ($_GET['par'] == "45") {
                      include "45_hak_akses.php";
                    } else if ($_GET['par'] == "46") {
                      include "46_input_divisi.php";
                    } else if ($_GET['par'] == "01") {
                      include "01_create_log.php";
                    } else if ($_GET['par'] == "01_edt") {
                      include "01_edt_log.php";
                    } else if ($_GET['par'] == "02") {
                      include "02_del_log.php";
                    } else if ($_GET['par'] == "03") {
                      include "03_edt_log.php";
                    } else if ($_GET['par'] == "03_edt") {
                      include "03_edt.php";
                    } else if ($_GET['par'] == "04") {
                      include "04_lap_log.php";
                    } else if ($_GET['par'] == "06") {
                      include "06_create_cust.php";
                    } else if ($_GET['par'] == "07") {
                      include "07_lap_open.php";
                    } else if ($_GET['par'] == "08") {
                      include "08_lap_peruser.php";
                    } else if ($_GET['par'] == "09") {
                      include "09_create_invoice.php";
                    } else if ($_GET['par'] == "10") {
                      include "10_payment_invoice.php";
                    } else if ($_GET['par'] == "11") {
                      include "11_efaktur_invoice.php";
                    } else if ($_GET['par'] == "12") {
                      include "12_payment_efaktur.php";
                    } else if ($_GET['par'] == "13") {
                      include "13_input_pph.php";
                    } else if ($_GET['par'] == "14") {
                      include "14_payment_pph.php";
                    } else if ($_GET['par'] == "15") {
                      include "15_lap_invoice.php";
                    } else if ($_GET['par'] == "22") {
                      include "22_lap_invoice_cus.php";
                    } else if ($_GET['par'] == "16") {
                      include "16_lap_efaktur.php";
                    } else if ($_GET['par'] == "17") {
                      include "17_lap_pph.php";
                    } else if ($_GET['par'] == "18") {
                      include "18_invoice_expired.php";
                    } else if ($_GET['par'] == "19") {
                      include "19_invoice_belum_bayar.php";
                    } else if ($_GET['par'] == "20") {
                      include "20_kehadiran.php";
                    } else if ($_GET['par'] == "21") {
                      include "21_kehadiran_semua.php";
                    } else if ($_GET['par'] == "23") {
                      include "kehadiran.php";
                    } else if ($_GET['par'] == "24") {
                      include "23_lap_update.php";
                    } else if ($_GET['par'] == "25") {
                      include "24_lap_testing.php";
                    } else if ($_GET['par'] == "26") {
                      include "25_pengajuan_izin.php";
                    } else if ($_GET['par'] == "27") {
                      include "26_approval_izin.php";
                    } else if ($_GET['par'] == "28") {
                      include "27_lap_perizinan.php";
                    } else if ($_GET['par'] == '29') {
                      include "29_grafik_laporan_log.php";
                    } else if ($_GET['par'] == '30') {
                      include "30_lap_nilai.php";
                    } else if ($_GET['par'] == "31") {
                      include "31_create_implementasi.php";
                    // } else if ($_GET['par'] == '32') {
                    //   include "32_del_implementasi.php";
                    } else if ($_GET['par'] == '33') {
                      include "33_edt_implementasi.php";
                    } else if ($_GET['par'] == '34') {
                      include "34_lap_implementasi.php";
                    } else if ($_GET['par'] == '35') {
                      include "35_pengajuan_lembur.php";
                    } else if ($_GET['par'] == '35a') {
                      include "35a_approval_lembur.php";
                    } else if ($_GET['par'] == '36') {
                      include "36_tanggungjwb_lembur.php";
                    } else if ($_GET['par'] == '37') {
                      include "37_lap_lembur.php";
                    } else if ($_GET['par'] == '38') {
                      include "38_penggajian.php";
                    } else if ($_GET['par'] == '39') {
                      include "39_akun.php";
                    } else if ($_GET['par'] == '40a') {
                      include "40a_create_kantong.php";
                    } else if ($_GET['par'] == '40') {
                      include "40_kas.php";
                    } else if ($_GET['par'] == '41') {
                      include "41_lap_kas.php";
                    } else if ($_GET['par'] == '42') {
                      include "42_about.php";
                    } else if ($_GET['par'] == '43') {
                      include "43_dashboard.php";
                    } else if ($_GET['par'] == '47') {
                      include "47_kalender.php";
                    } else if ($_GET['par'] == '48') {
                      include "48_pengajuan_pinjaman.php";
                    } else if ($_GET['par'] == '49') {
                      include "49_approval_pinjaman.php";
                    } else if ($_GET['par'] == '50') {
                      include "50_input_jabatan.php";
                    } else if ($_GET['par'] == '51') {
                      include "51_lap_penggajian.php";
                    } else if ($_GET['par'] == '52') {
                      include "52_lap_pinjaman.php";
                    } else if ($_GET['par'] == '53') {
                      include "53_input_sprint.php";
                    } else if ($_GET['par'] == '54') {
                      include "54_lap_sprint.php";
                    }
                  } else {
                    include "home.php";
                  }


                  ?>
                  <!----- AKHIR BODY ----->
                </th>
              </tr>
              <tr>
                <th scope="row">
                  <!----- AWAL FOOTER ----->
                  <?php
                  include "footer.php";

                  //matikan koneksi db
                  $conn = null;


                  ?>
                  <!----- AKHIR FOOTER ----->
                </th>
              </tr>
            </table>
        </td>

      </table>

    </section>
    <!--main content end-->
  </section>

  <!-- container section start -->

  <!-- javascripts -->
  <!-- <script src="js/jquery.js"></script> -->
  <script src="js/jquery-ui-1.10.4.min.js"></script>
  <!-- <script src="js/jquery-1.8.3.min.js"></script> -->
  <script type="text/javascript" src="js/jquery-ui-1.9.2.custom.min.js"></script>
  <!-- bootstrap -->
  <script src="js/bootstrap.min.js"></script>
  <!-- nice scroll -->
  <script src="js/jquery.scrollTo.min.js"></script>
  <script src="js/jquery.nicescroll.js" type="text/javascript"></script>

  <!-- container section end -->
  <!-- javascripts -->
  <!-- <script src="js/jquery.js"></script> -->
  <script src="js/bootstrap.min.js"></script>
  <!-- dataTables -->
  <?php if ($_GET['par'] <> "12_gen") {
    echo "<script src='js/jquery.dataTables.min.js'></script>";
  } ?>
  <script src="js/dataTables.bootstrap.min.js"></script>
  <!-- nice scroll -->
  <script src="js/jquery.scrollTo.min.js"></script>
  <script src="js/jquery.nicescroll.js" type="text/javascript"></script>

  <!-- jquery ui -->
  <script src="js/jquery-ui-1.9.2.custom.min.js"></script>

  <!--custom checkbox & radio-->
  <script type="text/javascript" src="js/ga.js"></script>
  <!--custom switch-->
  <script src="js/bootstrap-switch.js"></script>
  <!--custom tagsinput-->
  <script src="js/jquery.tagsinput.js"></script>

  <!-- colorpicker -->

  <!-- bootstrap-daterangepicker -->
  <script src="js/daterangepicker.js"></script>
  <script src="js/bootstrap-datepicker.js"></script>
  <script src="assets/bootstrap-select/bootstrap-select.min.js"></script>
  <script src="assets/select2/select2.min.js"></script>
  <script src="js/metronic.js"></script>
  <script type="text/javascript">
    $(document).ready(function() {
      $('#dp1').datepicker({
        //merubah format tanggal datepicker ke dd-mm-yyyy
        format: "yyyy-mm-dd",
        //format: "dd-mm-yyyy",
        //aktifkan kode dibawah untuk melihat perbedaanya, disable baris perintah diatasa
        //format: "dd-mm-yyyy",
        autoclose: true
      });
    });
  </script>
  <script type="text/javascript">
    $(document).ready(function() {
      $('#dp2').datepicker({
        //merubah format tanggal datepicker ke dd-mm-yyyy
        format: "yyyy-mm-dd",
        //format: "dd-mm-yyyy",
        //aktifkan kode dibawah untuk melihat perbedaanya, disable baris perintah diatasa
        //format: "dd-mm-yyyy",
        autoclose: true
      });
      $('#dp3').datepicker({
        //merubah format tanggal datepicker ke dd-mm-yyyy
        format: "yyyy-mm-dd",
        autoclose: true
      });
      $('#dp4').datepicker({
        //merubah format tanggal datepicker ke dd-mm-yyyy
        format: "yyyy-mm-dd",
        autoclose: true
      });
      $('#dp5').datepicker({
        //merubah format tanggal datepicker ke dd-mm-yyyy
        format: "yyyy-mm-dd",
        autoclose: true
      });

      $('.date-picker').datepicker({
        format: "yyyy-mm-dd",
        autoclose: true
      });

      $('.select2me').select2();

      $('.samakan_tanggal').click(function() {
        let value = $(this).is(':checked') ? true : false;
        if (value) {
          let tglSelesai = $('input[name="tglselesai"]').val();
          $('input[name="tgltesting"]').val(tglSelesai);
          $('input[name="tglupdate"]').val(tglSelesai);
        }
      });

      $('input[name="tglselesai"]').change(function() {
        let checkbox = $('.samakan_tanggal').is(':checked') ? true : false;
        let value = $(this).val();
        if (checkbox) {
          $('input[name="tgltesting"]').val(value);
          $('input[name="tglupdate"]').val(value);
        }
      });

      $('.samakan_status').click(function() {
        let value = $(this).is(':checked') ? true : false;
        if (value) {
          let tglSelesai = $('select[name="selesai"]').val();
          $('select[name="testing"]').val(tglSelesai);
          $('select[name="update"]').val(tglSelesai);
        }
      });

      $('select[name="selesai"]').change(function() {
        let checkbox = $('.samakan_status').is(':checked') ? true : false;
        let value = $(this).val();
        if (checkbox) {
          $('select[name="testing"]').val(value);
          $('select[name="update"]').val(value);
        }
      });

      $('.selesai_hariini').click(function() {
        let value = $(this).is(':checked') ? true : false;
        if (value) {
          let dateNow = new Date();
          let tglSelesai = dateNow.getFullYear() + "-" + ("0" + (dateNow.getMonth() + 1)).slice(-2) + "-" + ("0" + dateNow.getDate()).slice(-2);
          $('.selesai_tglorder').prop('checked', false);
          $('input[name="tglselesai"]').val(tglSelesai);
          $('input[name="tgltesting"]').val(tglSelesai);
          $('input[name="tglupdate"]').val(tglSelesai);
          $('select[name="selesai"]').val(1);
          $('select[name="testing"]').val(1);
          $('select[name="update"]').val(1);
          $('textarea[name="deslayan"]').val("done");
        }else{
          $('input[name="tglselesai"]').val("");
          $('input[name="tgltesting"]').val("");
          $('input[name="tglupdate"]').val("");
          $('select[name="selesai"]').val(0);
          $('select[name="testing"]').val(0);
          $('select[name="update"]').val(0);
          $('textarea[name="deslayan"]').val("Dalam Proses");
        }
      });

      $('.selesai_tglorder').click(function() {
        let value = $(this).is(':checked') ? true : false;
        if (value) {
          let tglOrder = $('input[name="tglorder"]').val();
          $('.selesai_hariini').prop('checked', false);
          $('input[name="tglselesai"]').val(tglOrder);
          $('input[name="tgltesting"]').val(tglOrder);
          $('input[name="tglupdate"]').val(tglOrder);
          $('select[name="selesai"]').val(1);
          $('select[name="testing"]').val(1);
          $('select[name="update"]').val(1);
          $('textarea[name="deslayan"]').val("done");
        }else{
          $('input[name="tglselesai"]').val("");
          $('input[name="tgltesting"]').val("");
          $('input[name="tglupdate"]').val("");
          $('select[name="selesai"]').val(0);
          $('select[name="testing"]').val(0);
          $('select[name="update"]').val(0);
          $('textarea[name="deslayan"]').val("Dalam Proses");
        }
      });
    });
  </script>


  <script>
    $(function() {
      <?php if (!isset($_GET['par']) || $_GET['par'] != '33'): ?>
      $('#contoh').DataTable({
          'paging': true,
          'lengthChange': true,
          'searching': <?php echo (isset($_GET['par']) && $_GET['par'] == '33') ? 'false' : 'true'; ?>,
          'ordering': true,
          'info': true,
          'autoWidth': false
      })
      <?php endif; ?>

      $('#gen_po_all').DataTable({
        'paging': false,
        'lengthChange': false,
        'searching': true,
        'ordering': false,
        'info': false,
        'autoWidth': false
      })
      $('#tabelexport').DataTable({
        'paging': false,
        'lengthChange': false,
        'searching': true,
        'ordering': false,
        'info': false,
        'autoWidth': false
      })
    })
  </script>

</body>

</html>