<?php
if(isset($_POST) == true){
    //menghasilkan nama file yang unik, akan ada angka acak didepannya
    $namaFile = time().'_'.basename($_FILES["file"]["name"]);
    
    echo $namaFile; 
}