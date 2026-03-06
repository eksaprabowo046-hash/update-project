<?php 
require "global.php";

?> 
 
<body>
  <!-- container section start -->
  <section id="container" class="">  
    <!--main content start-->
    <section id="main-content">
      <section class="wrapper">
        <!--overview start-->
  
       <div class="row">
          <div class="col-lg-12">
            <section class="panel">
              <header class="panel-heading">
                <form  class="form-inline" method="post" target="_self"> 
				 
				</form> 
				&nbsp;
              </header>
			  <section class="content">
              <div class="box-body">
                <table id="tabelku" class="table table-bordered table-striped table-hover">
                  <thead>
                    <tr class="height: 5px;">
                      <th>#</th>
                      <th>Supplier</th>
                      <th>No PO</th>
                      <th>Tgl PO</th> 
					  <th>Tgl Kirim</th> 
                    </tr>
                  </thead>
                  <tbody>
				  <?php
				     if (isset($_POST['tombol'])){
				       try { 
							// set error mode
							$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
							// jalankan query
							$sql = $conn->prepare("select a.nopo, a.kodsupplier, b.nmsupplier, a.tglpo, a.tglkirim 
														from tmstpo a inner join rsupplier b on a.kodsupplier=b.kodsupplier 
														where a.idusaha='$idusaha' and (a.tglpo>='$tglmli'  and a.tglpo<='$tglsd') 
														order by a.tglpo");
							$sql->execute();	 				
							// tampilkan
							$no=1;
							while($rs = $sql->fetch()) { 
							   echo "   <tr>
								  <td>".$no."</td>
								  <td>".$rs['nmsupplier']."</td>
								  <td>".$rs['nopo']."</td>
								  <td>".$rs['tglpo']."</td>
								  <td>".$rs['tglkirim']."</td> 
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


</body>

</html>
