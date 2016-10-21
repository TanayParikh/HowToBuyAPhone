<?php
    ini_set('display_errors',1); 
    error_reporting(E_ALL);
    include_once("ETL/DeviceExtraction.php");
?>

<html>
 <head>
  <title>PHP Test</title>
 </head>
 <body>
 <?php
     DeviceExtraction::startProcessing();
 ?>
 </body>
</html>
