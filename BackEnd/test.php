<?php
    ini_set('display_errors',1); 
    error_reporting(E_ALL);
    include_once("dataRetrieval.php");
?>

<html>
 <head>
  <title>PHP Test</title>
 </head>
 <body>
 <?php dataRetrieval::fetchDevices(); ?>
 </body>
</html>
