<?php
    include_once("ExtractExtractDevice.php");
    include_once("TransformDevice.php");
    include_once("LoadDevice.php");

    class ETLHandler
    {
        public static function startProcessing() {
            // Sets up application data and settings
            SpecificationDefinition::init();
            $devices = ExtractDevice::getDevices();

            // Processes individual devices
            foreach ($devices as $device) {
                $transformDevice = new TransformDevice($device);
                // TODO: Load device
            }
        }
    }