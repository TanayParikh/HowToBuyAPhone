<?php
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/DeviceSelection/SelectionAlgorithm.php");

    $data = json_decode( $_POST['json_data'] );
    $response = SelectionAlgorithm::processSelection($data);
    header('Content-type: text/json');
    print json_encode($response);