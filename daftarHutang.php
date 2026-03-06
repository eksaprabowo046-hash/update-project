<?php 
require "global.php";

//Menangkap POST
$idusaha = "";
if (isset($_POST['idusaha'])) {
   $idusaha = $_POST['idusaha'];
}
if (isset($_POST['tglmli'])) {
   $tglmli = $_POST['tglmli'];
}
if (isset($_POST['tglsd'])) {
   $tglsd = $_POST['tglsd'];
}


?> 

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Creative - Bootstrap 3 Responsive Admin Template">
  <meta name="author" content="GeeksLabs">
  <meta name="keyword" content="Creative, Dashboard, Admin, Template, Theme, Bootstrap, Responsive, Retina, Minimal">
  <link rel="shortcut icon" href="img/favicon.png">

  <title><?php  echo $webtitle?></title>

  <!-- Bootstrap CSS -->
  <link href="css/bootstrap.min.css" rel="stylesheet"> 
  <link href="css/dataTables.bootstrap.min.css" rel="stylesheet"> 
  <!-- bootstrap theme  -->
  <link href="css/bootstrap-theme.css" rel="stylesheet">
  <!--external css-->
  <!-- font icon -->
  <link href="css/elegant-icons-style.css" rel="stylesheet" />
  <link href="css/font-awesome.min.css" rel="stylesheet" /> 
  <!-- datepicker --> 
  <link href="css/bootstrap-datepicker.css" rel="stylesheet" />
 

  <!-- Custom styles -->
  <link href="css/style.css" rel="stylesheet">
  <link href="css/style-responsive.css" rel="stylesheet" />
  
  <!-- HTML5 shim and Respond.js IE8 support of HTML5 -->
  <!--[if lt IE 9]>
      <script src="js/html5shiv.js"></script>
      <script src="js/respond.min.js"></script>
      <script src="js/lte-ie7.js"></script>
    <![endif]-->
  
  <!-- =======================================================
    Theme Name: NiceAdmin
    Theme URL: https://bootstrapmade.com/nice-admin-bootstrap-admin-html-template/
    Author: BootstrapMade
    Author URL: https://bootstrapmade.com
  ======================================================= -->
</head>

<body>
  <!-- container section start -->
  <section id="container" class="">


    <header class="header dark-bg">
      <div class="toggle-nav">
        <div class="icon-reorder tooltips" data-original-title="Toggle Navigation" data-placement="bottom"><i class="icon_menu"></i></div>
      </div>

      <!--logo start-->
      <a href="index.html" class="logo">mm<span class="lite">DSI</span></a>
      <!--logo end-->

      <div class="nav search-row" id="top_menu">
        <!--  search form start -->
        <ul class="nav top-menu">
          <li>
            <form class="navbar-form">
              <input class="form-control" placeholder="Search" type="text">
            </form>
          </li>
        </ul>
        <!--  search form end -->
      </div>

      <div class="top-nav notification-row">
        <!-- notificatoin dropdown start-->
        <ul class="nav pull-right top-menu">

          <!-- task notificatoin start --> 
          <!-- task notificatoin end -->
          <!-- inbox notificatoin start--> 
          <!-- inbox notificatoin end -->
          <!-- alert notification start--> 
          <!-- alert notification end-->
          <!-- user login dropdown start--> 
          <!-- user login dropdown end -->
        </ul>
        <!-- notificatoin dropdown end-->
      </div>
    </header>
    <!--header end-->

    <!--sidebar start-->
    <?php 
     require  "menu.php";
	?> 
    <!--sidebar end-->

    <!--main content start-->
    <section id="main-content">
      <section class="wrapper">
        <!--overview start-->
        <div class="row">
          <div class="col-lg-12">
            <h3 class="page-header"><i class="fa fa-laptop"></i> Daftar Hutang</h3>
            <ol class="breadcrumb">
              <li><i class="fa fa-home"></i><a href="index.php">Home</a></li>
              <li><i class="fa fa-laptop"></i>Daftar Hutang</li>
            </ol>
          </div>
        </div>

       <div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <header class="panel-heading">
                <form  class="form-inline" method="post" target="_self"> 
					   
				  <div class="form-group">
					<div class="form-group">
					  Toko :   
					  <select  name="idusaha" class="form-control" id="idusaha">
					  <?php
						try { 
							// set error mode
							//$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							// jalankan query
							$result = $conn->prepare("SELECT idusaha, nmusaha FROM ridusaha order by nmusaha");
							$result->execute();	 				
							// tampilkan
							while($row = $result->fetch()) {
								echo "<option value='".$row[0]."'>".$row[1]."</option>";  
							} 
					   }
					   catch (PDOException $e)	{
						   // tampilkan pesan kesalahan jika koneksi gagal
						   print "Koneksi atau query bermasalah: " . $e->getMessage() . "<br/>";
						   die();
						}
						 
					  ?>
					  </select>
					</div>
				  </div>
				  <div class="form-group">
                    Dari tanggal : 
                    <input name="tglmli" id="dp1" type="text" value="" size="16" class="form-control" autocomplete="off"> 
                  </div>
				  <div class="form-group">
					S.d. Tanggal : 
					<input name="tglsd" id="dp2" type="text" value="" size="16" class="form-control" autocomplete="off">
				  </div>
				  <div class="form-group"> 
				    <br>
				  <button name="tombol" type="submit" class="btn btn-default">Submit</button>
				  </div>
				</form> 
				&nbsp;
              </header>
			  <section class="content">
              <div class="box-body">
                <table id="contoh" class="table table-bordered table-striped table-hover">
                  <thead>
                    <tr class="height: 5px;">
                      <th>#</th>
                      <th>Nota Beli</th>
                      <th>Tgl Beli</th>
                      <th>Nama Supplier</th>
					  <th>Tgl Tempo</th> 
					  <th>Total</th>
					  <th>Terbayar</th> 
                    </tr>
                  </thead>
                  <tbody>
				  <?php
				     if (isset($_POST['tombol'])){
				       try { 
							// set error mode
							$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							// jalankan query
							//$sql = $conn->prepare("select a.nopo, a.kodsupplier, b.nmsupplier, a.tglpo, a.tglkirim 
							//							from tmstpo a inner join rsupplier b on a.kodsupplier=b.kodsupplier 
							//							where a.idusaha='$idusaha' and (a.tglpo>='$tglmli'  and a.tglpo<='$tglsd') 
							//							order by a.tglpo");
														
							$sql = $conn->prepare("select a.notabeli, a.tglbeli, b.nmsupplier, a.tgltempo, a.total, a.terbayar, a.idusaha 
														from tmstbeli a inner join rsupplier b on a.kodsupplier=b.kodsupplier 
														where a.idusaha='$idusaha' and a.tglbeli>='$tglmli'  and a.tglbeli<='$tglsd' 
														and b.iskonsinyasi='F' and cbayar='K'");
							$sql->execute();	 				
							// tampilkan
							$no=1;
							while($rs = $sql->fetch()) { 
							   echo "   <tr>
								  <td align=center>".$no."</td>
								  <td>".$rs['notabeli']."</td>
								  <td align=center>".date("d-m-Y", strtotime($rs['tglbeli']))."</td>
								  <td>".$rs['nmsupplier']."</td>
								  <td align=center>".date("d-m-Y", strtotime($rs['tgltempo']))."</td>
								  <td align=right>".number_format($rs['total'],0,",",".")."</td>
								  <td align=right>".number_format($rs['terbayar'],0,",",".")."</td> 
								</tr> ";
								$no++;	
							} 
					   }//try
					   catch (PDOException $e)	{
					      echo "  
						   <tr>
							  <td></td>
							  <td></td>
							  <td></td>
							  <td></td> 
							  <td></td> 
							</tr>  ";
					 
					  }//catch
					} //if (isset($_POST['tombol'])) 
				  ?>
                    
                    
                    
                  </tbody>
                </table>
              </div>
			  </section>
            </section>
          </div>
        </div> 

    

      </section>
      <?php
	   require "footer.php";
	  ?>
    </section>
    <!--main content end-->
  </section>
  <!-- container section start -->

  <!-- javascripts -->
  <script src="js/jquery.js"></script>
  <script src="js/jquery-ui-1.10.4.min.js"></script>
  <script src="js/jquery-1.8.3.min.js"></script>
  <script type="text/javascript" src="js/jquery-ui-1.9.2.custom.min.js"></script>
  <!-- bootstrap -->
  <script src="js/bootstrap.min.js"></script>
  <!-- nice scroll -->
  <script src="js/jquery.scrollTo.min.js"></script>
  <script src="js/jquery.nicescroll.js" type="text/javascript"></script>
 
 

    <!--custome script for all page-->
    <script src="js/scripts.js"></script>
    <!-- custom script for this page
    <script src="js/sparkline-chart.js"></script>
    <script src="js/easy-pie-chart.js"></script>
    <script src="js/jquery-jvectormap-1.2.2.min.js"></script>
    <script src="js/jquery-jvectormap-world-mill-en.js"></script>
    <script src="js/xcharts.min.js"></script>
    <script src="js/jquery.autosize.min.js"></script>
    <script src="js/jquery.placeholder.min.js"></script>
    <script src="js/gdp-data.js"></script>
    <script src="js/morris.min.js"></script>
    <script src="js/sparklines.js"></script>
    <script src="js/charts.js"></script>
	-->
    <script src="js/jquery.slimscroll.min.js"></script>
    <script>
      //knob
      $(function() {
        $(".knob").knob({
          'draw': function() {
            $(this.i).val(this.cv + '%')
          }
        })
      });

      //carousel
      $(document).ready(function() {
        $("#owl-slider").owlCarousel({
          navigation: true,
          slideSpeed: 300,
          paginationSpeed: 400,
          singleItem: true

        });
      });

      //custom select box

      $(function() {
        $('select.styled').customSelect();
      });

      /* ---------- Map ---------- */
      $(function() {
        $('#map').vectorMap({
          map: 'world_mill_en',
          series: {
            regions: [{
              values: gdpData,
              scale: ['#000', '#000'],
              normalizeFunction: 'polynomial'
            }]
          },
          backgroundColor: '#eef3f7',
          onLabelShow: function(e, el, code) {
            el.html(el.html() + ' (GDP - ' + gdpData[code] + ')');
          }
        });
      });
    </script>

 <!-- container section end -->
  <!-- javascripts -->
  <script src="js/jquery.js"></script>
  <script src="js/bootstrap.min.js"></script>
  <!-- dataTables -->
  <script src="js/jquery.dataTables.min.js"></script>
  <script src="js/dataTables.bootstrap.min.js"></script>
  <!-- nice scroll -->
  <script src="js/jquery.scrollTo.min.js"></script>
  <script src="js/jquery.nicescroll.js" type="text/javascript"></script>

  <!-- jquery ui -->
  <script src="js/jquery-ui-1.9.2.custom.min.js"></script>

  <!--custom checkbox & radio
  <script type="text/javascript" src="js/ga.js"></script>
  <!--custom switch-->
  <script src="js/bootstrap-switch.js"></script>
  <!--custom tagsinput-->
  <script src="js/jquery.tagsinput.js"></script>
  -->
  <!-- colorpicker -->

  <!-- bootstrap-daterangepicker --> 
  <script src="js/daterangepicker.js"></script>
  <script src="js/bootstrap-datepicker.js"></script>
  <script type="text/javascript">
            $(document).ready(function () {
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
            $(document).ready(function () {
                $('#dp2').datepicker({
                 //merubah format tanggal datepicker ke dd-mm-yyyy
                    format: "yyyy-mm-dd",
					//format: "dd-mm-yyyy",
                    //aktifkan kode dibawah untuk melihat perbedaanya, disable baris perintah diatasa
                    //format: "dd-mm-yyyy",
                    autoclose: true
                });
            });
  </script>
 
  <script>
  $(function () { 
    $('#contoh').DataTable({
      'paging'      : true,
      'lengthChange': true,
      'searching'   : true,
      'ordering'    : true,
      'info'        : true,
      'autoWidth'   : false 
    })
  })
</script>
</body>

</html>
