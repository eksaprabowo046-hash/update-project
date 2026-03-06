
 
<form method="POST" >
<label for="Manufacturer"> Manufacturer : </label>
  <select id="cmbMake" name="Make"     onchange="document.getElementById('selected_text').value=this.options[this.selectedIndex].text">
     <option value="0">Select Manufacturer</option>
     <option value="1">--Any--</option>
     <option value="2">Toyota</option>
     <option value="3">Nissan</option>
</select>
<input type="text" name="selected_text" id="selected_text" value="" />
<input type="submit" name="search" value="Search"/>
</form>


 <?php

if(isset($_POST['search']))
{

    $makerValue = $_POST['Make']; // make value

    $maker = mysql_real_escape_string($_POST['selected_text']); // get the selected text
    echo $maker;
}
 ?>