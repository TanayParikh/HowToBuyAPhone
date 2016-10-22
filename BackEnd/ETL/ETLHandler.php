<?php
    include_once("ExtractDevice.php");
    include_once("TransformDevice.php");
    include_once("LoadDevice.php");

    class ETLHandler
    {
        public static function startProcessing() {
            // Sets up application data and settings
            SpecificationDefinition::init();
            TransformDevice::init(true);
            $devices = ExtractDevice::getDevices();

            // Processes individual devices
            foreach ($devices as $rawDevice) {
                $transformedDevice = (new TransformDevice($rawDevice))->transformDevice();
                LoadDevice::loadRawDevice($rawDevice);
                LoadDevice::loadTransformedDevice($transformedDevice);
            }
        }
    }