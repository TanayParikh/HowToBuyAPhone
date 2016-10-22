<?php

    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");

    class DeviceTransform
    {
        static $saveToDatabase;
        static $displayDevice;
        static $specificationDefinitions;

        private $rawDevice;
        private $transformedDevice;

        function __construct($device) {
            $this->rawDevice = $device;
            transformDevice();
        }

        function transformDevice() {
            
        }


        public static function init($saveToDatabase = false, $displayDevice = false) {
            self::$saveToDatabase = $saveToDatabase;
            self::$displayDevice = $displayDevice;

            //SpecificationDefinition::init();
        }
    }