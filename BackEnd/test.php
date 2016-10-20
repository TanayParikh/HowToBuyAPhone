<?php
    ini_set('display_errors',1); 
    error_reporting(E_ALL);
    include_once("dataRetrieval.php");
    include_once("API/api.php");
?>

<html>
 <head>
  <title>PHP Test</title>
 </head>
 <body>
 <?php
     //dataRetrieval::fetchDevices();

     $devices = new API();
     $firstDevice = $devices->search()["data"][0]["slug"];
     $deviceDetail = $devices->detail($firstDevice);

     if ($deviceDetail->status == "success") {
         echo $deviceDetail->DeviceName . '<br>';
         echo '<IMG SRC="'. $deviceDetail->DeviceIMG .  '" ALT="some text"> <br>';
         echo '<pre>' . var_dump($deviceDetail->data) . '</pre>';
     }


 ?>
 </body>
</html>
