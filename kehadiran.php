<?php
session_start();
$iduser=$_SESSION['DEFAULT_IDUSER'];
require('dbase.php');
require('bacaip.php');
$tanggal =  date("Y-m-d");
$jam ='';
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$sql = $conn->prepare("select CURRENT_TIME as jam");
$sql->execute();  
if ($sql->rowCount() > 0){ 
$rs = $sql->fetch();
$jam = $rs['jam'];
}     

//delete dulu 
if (isset($_POST['submit'])) {
    if ($_POST['submit']=="HADIR"){
        $sqldel = "delete tkehadiran where iduser='$iduser' and tanggal='$tanggal'"; 
        try { 
            //insert
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $qdel = $conn->prepare($sqldel);
            $qdel->execute();   
        }//try
            catch (PDOException $e)	{
                
        }//catch

        //insert 
        $pulang   = '';
        $sqlins = "insert into  tkehadiran (iduser,tanggal,hadir,pulang) ";
        $sqlins = $sqlins . "values ('$iduser','$tanggal','$jam','$pulang')"; 
        try { 
            //insert
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $qins = $conn->prepare($sqlins);
            $qins->execute();  
            $_SESSION['DEFAULT_PESAN'] ="<font color=black>Sukses hadir ". $jam ." .</font>" ;
            Header("location: index.php");; 
        }//try
            catch (PDOException $e)	{
                
                //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
                $pesan =  "<font color=red>Error insert. Make sure data is correct.</font>" ; 
        }//catch
    }else if ($_POST['submit']=="PULANG"){
        $pulang   = date("h:i:sa");
        $sqlupd = "update tkehadiran set pulang='$jam' where iduser='$iduser' and tanggal='$tanggal'"; 
        try { 
            //insert
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $qupd = $conn->prepare($sqlupd);
            $qupd->execute();  
            $_SESSION['DEFAULT_PESAN'] ="<font color=black>Sukses pulang ". $jam ." .</font>" ;
            Header("location: index.php");; 
        }//try
            catch (PDOException $e)	{
                
                //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
                $pesan =  "<font color=red>Error insert. Make sure data is correct.</font>" ; 
        }//catch
    }
    else if ($_POST['submit']=="REFRESH_IP"){
        $ip = ipnya();
        $nmkantor = 'DSI1';
        $sqlupd = "update tipkantor set noip='$ip' where nmkantor='$nmkantor'"; 
        try { 
            //insert
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $qupd = $conn->prepare($sqlupd);
            $qupd->execute();  
            $_SESSION['DEFAULT_PESAN'] ="<font color=black>Sukses refresh IP ". $ip ." .</font>" ;
            Header("location: index.php");; 
        }//try
            catch (PDOException $e)	{
                
                //echo "<font color=red>Error insert. Make sure kode barang is correct.</font>" ;
                $pesan =  "<font color=red>Error insert. Make sure data is correct.</font>" ; 
        }//catch
    }
}


?>