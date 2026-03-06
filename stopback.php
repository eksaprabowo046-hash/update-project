<html>
<head>
<title>Untitled Document</title>
<meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
</head>

<body>
<?php 
header ("Ex-pires:Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past 
header ("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); 
// al-ways modified 
header ("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1 
header ("Pragma: no-cache"); // HTTP/1.0 
?> 
</body>
</html>
