
<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
<script language="JavaScript" type="text/JavaScript">
<!--

function MM_preloadImages() { //v3.0
  var d=document; if(d.images){ if(!d.MM_p) d.MM_p=new Array();
    var i,j=d.MM_p.length,a=MM_preloadImages.arguments; for(i=0; i<a.length; i++)
    if (a[i].indexOf("#")!=0){ d.MM_p[j]=new Image; d.MM_p[j++].src=a[i];}}
}
//-->
</script>
<script>
    function checknumber(form) {
	  if ((form.tg.value <1) || (form.tg.value >31))  {
		alert( "Tanggal harus antara 1-31." );
		form.tg.focus();
		return false ;
	  }
	  if ((form.bl.value <1) || (form.bl.value >12)) {
		alert( "Bulan harus antara 1-12." );
		form.bl.focus();
		return false ;
	  }
	  if (form.th.value < "2011" ) {
		alert( "Tahun Harus 2011 ke atas" );
		form.th.value = "2012";
		form.th.focus();
		return false ;
	  }
    }
</script>
</head>

<body>
<table width="100%" border="0" cellspacing="0"  >
  <tr> 
    <td bgcolor="#FFFF66"><img src="index_files/jdl_home.jpg" width="606" height="23"></td>
  </tr>
  <tr> 
    <td height="250" valign="top" class="clsText"><br> 
	<table width="100%" border="0" cellspacing="0"  class="clsText"> 
        <tr> 
          <td>&nbsp;<strong></strong></td>
        </tr>
        <tr> 
          <td><div align="right"><font size="2" face="Verdana, Arial, Helvetica, sans-serif"><a href="javascript:history.back()">Back</a>&nbsp;</font></div></td>
        </tr>
        <tr> 
          <td> <table width="100%"  class="clsText"  >
              <tr> 
                <td>&nbsp;</td>
                <td colspan="2" valign="top" bgcolor="#99CCCC"><strong><font size="+1">&nbsp;&nbsp;Maaf, 
                  </font></strong></td>
                <td>&nbsp;</td>
              </tr>
              <tr bordercolor="#CCCCFF"> 
                <td width="12%">&nbsp;</td>
                <td width="37%" colspan="2" rowspan="4" valign="top" bgcolor="#CCCCFF"> 
                  <div align="right"></div>
                  <div align="left"> 
                    <p>&nbsp;&nbsp;Masih dalam pengembangan.</p>
                    </div>
                  <div align="right"></div></td>
                <td width="23%">&nbsp;</td>
              </tr>
              <tr> 
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr> 
                <td>&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
              <tr> 
                <td height="21">&nbsp;</td>
                <td>&nbsp;</td>
              </tr>
            </table></td>
        </tr>
      </table>
      <div align="center"></div></td>
  </tr>
  <tr> 
    <td></td>
  </tr>
  <tr> 
    <td height="29" valign="top" class="clsText">&nbsp;</td>
  </tr>
</table>
</body>
</html>
