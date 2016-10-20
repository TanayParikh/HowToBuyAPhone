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
         $obj_merged = null;

         foreach ($deviceDetail->data as $grouping) {
             $obj_merged = (object) array_merge((array) $obj_merged, (array) $grouping);
         }

         echo var_dump((array) $obj_merged);
         $obj_merged->technology_p = $obj_merged->cpu;
         echo $obj_merged->technology_p;
     }
 ?>
 </body>
</html>
