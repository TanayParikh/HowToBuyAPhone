<?php

    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");

    class TransformDevice
    {
        static $saveToDatabase;
        static $displayDevice;
        static $specificationDefinitions;

        private static $numericPattern = '(\d*[.]\d*|\d*)';
        private static $exchangeRates;

        private $rawDevice = null;
        private $transformedDevice;

        function __construct($device) {
            $this->rawDevice = $device;
            $this->transformDevice();
        }

        function transformDevice() {
            $rawDevice = $this->rawDevice;
            $output = $this->setDeviceIdentifiers();

            $output .= $this->setDimensions($rawDevice->dimensions) . '<br>';
            $output .= $this->setWeight($rawDevice->weight) . '<br>';
            $output .= $this->setScreenSize($rawDevice->size) . '<br>';
            $output .= $this->setScreenResolution($rawDevice->resolution) . '<br>';
            $output .= $this->setExpandableStorage($rawDevice) . '<br>';
            $output .= $this->setBluetoothVersion($rawDevice) . '<br>';
            $output .= ($this->setRemovableBattery($rawDevice) ? "Removable" : "Non-removable") . ' Battery <br>';
            $output .= $this->setBatteryCapacity($rawDevice) . '<br>';
            $output .= $this->setCPU($rawDevice) . '<br>';
            $output .= $this->setInternalStorage($rawDevice) . '<br>';
            $output .= $this->setOS($rawDevice) . '<br>';
            $output .= $this->setCamera($rawDevice) . '<br>';
            $output .= $this->setTalkTime($rawDevice) . '<br>';
            $output .= ($this->setHeadphoneJack($rawDevice) ? "3mm Jack Present" : "No 3mm Jack") . '<br>';
            $output .= $this->setDevicePrice($rawDevice) . '<br>';

            $output .=  '<br> <br> <br>';
            echo $output;
        }

        function setDeviceIdentifiers() {
            $output = null;

            if (!isNullOrEmpty($this->rawDevice->DeviceName)) {
                $output .= "<h1>" . $this->rawDevice->DeviceName . "</h1>";
                $this->transformedDevice->DeviceName = $this->rawDevice->DeviceName;
            }

            if (!isNullOrEmpty($this->rawDevice->Brand)) {
                $output .= "<h2>". $this->rawDevice->Brand . "</h2>";
                $this->transformedDevice->Brand = $this->rawDevice->Brand;
            }

            if (!isNullOrEmpty($this->rawDevice->announced)) {
                $output .= "Date Announced: ". $this->rawDevice->announced . "<br>";
                $this->transformedDevice->announced = $this->rawDevice->announced;
            }

            if (!isNullOrEmpty($this->rawDevice->status)) {
                $output .= "Status: ". $this->rawDevice->status . "<br>";
                $this->transformedDevice->status = $this->rawDevice->status;
            }

            if (!isNullOrEmpty($this->rawDevice->DeviceIMG)) {
                $output .=  $this->displayDeviceImage($this->rawDevice) . '<br>';
                $this->transformedDevice->DeviceIMG = $this->rawDevice->DeviceIMG;
            }

            return $output;
        }

        public static function init($saveToDatabase = false, $displayDevice = false) {
            self::$saveToDatabase = $saveToDatabase;
            self::$displayDevice = $displayDevice;
            self::getExchangeRates();
            //SpecificationDefinition::init();
        }

        private static function getExchangeRates () {
            $jsonExchangeRates = file_get_contents('http://api.fixer.io/latest?base=USD');
            self::$exchangeRates = json_decode($jsonExchangeRates);
            self::$exchangeRates->USD = 1;
        }

        private function setDimensions($dimensions) {
            // Example: 142.8 x 69.6 x 8.1 mm (5.62 x 2.74 x 0.32 in)

            if ($dimensions == "-") return null;

            $dimensionSeparatorPattern = '( x )?';

            if (preg_match_all("/".self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.'( mm)?(.*)'."/", $dimensions, $matches))
            {
                $length=$matches[1][0];
                $width=$matches[3][0];
                $thickness=$matches[5][0];

                if (stringContains($dimensions, "thickness")) {
                    $thickness = $matches[1][0];
                    return "Thickness: " . $thickness;
                } else {
                    return "Length: " . $length . "\tWidth: " . $width . " Thickness: " . $thickness;
                }
            }
        }

        // Gets a single double/int from a string with an optional suffix
        private static function getNumericFromString($rawData, $suffix = null) {
            if (preg_match_all("/".self::$numericPattern.'(' . $suffix . ')?(.*)'."/", $rawData, $matches)) {
                return $matches[1][0];
            }
        }

        private function setWeight($rawWeight) {
            // Example: 142 g (5.01 oz)
            $weight = self::getNumericFromString($rawWeight, ' g');
            if (!empty($weight)) return "Weight: " . $weight;
        }

        private function setScreenSize($rawScreenSize) {
            // Example: 7.0 inches (~68.1% screen-to-body ratio)
            $screenSize = self::getNumericFromString($rawScreenSize, ' inches');
            if (!empty($screenSize)) return "Screen Size: " . $screenSize;
        }

        private function setScreenResolution($rawResolution)
        {
            // Example: 480 x 854 pixels (~196 ppi pixel density)
            if (preg_match_all("/".'(\d*)( x )(\d*)( pixels)(.*~)(\d*)( ppi)(.*)'."/", $rawResolution, $matches)) {
                $resolution = $matches[1][0] . 'x' . $matches[3][0];
                $ppi = $matches[6][0];
                return "Resolution: " . $resolution . "\tPPI: " . $ppi;
            }
        }

        private function setExpandableStorage($device) {
            // Example: microSD, up to 64 GB
            if ($device->card_slot == "No") return null;

            if (preg_match_all('/(microSD, up to )(\d*)( GB)/', $device->card_slot, $matches)) {
                $cardStorageAmount = $matches[2][0];
                return "Card Storage Amount: " . $cardStorageAmount;
            }

            // A value of true -> 1 indicates expandable storage, of unknown max capacity
            return stringContains($device->card_slot, "microSD");
        }

        private function setBluetoothVersion($device) {
            // Example: v4.1, A2DP, LE
            if ($device->bluetooth == "No") return null;
            if ($device->bluetooth == "Yes") return 0;

            if (preg_match_all('/(v?)'.self::$numericPattern.'(.*)/', $device->bluetooth, $matches)) {
                $cardStorageAmount = $matches[2][0];
                return "Bluetooth Version: " . $cardStorageAmount;
            }

            self::logDevice($device, "Could not determine bluetooth version.");
        }

        private function setRemovableBattery($device) {
            // Note case sensitive (Non-removable is other type)
            return (stringContains($device->battery_c, "Removable"));
        }

        private function setBatteryCapacity($device) {
            if (preg_match_all('/([\d]+) mAh/', $device->battery_c, $matches)) {
                $batteryCapacity = $matches[1][0];
                return "Battery Capacity: " . $batteryCapacity;
            }

            self::logDevice($device, "Could not determine battery capacity.");
        }

        private static function getCoreCountFromWord($device) {
            // Example: Quad-core
            if (stringContains($device->cpu, "Single")) return 1;
            else if (stringContains($device->cpu, "Dual")) return 2;
            else if (stringContains($device->cpu, "Quad")) return 4;
            else if (stringContains($device->cpu, "Hexa")) return 6;
            else if (stringContains($device->cpu, "Octa")) return 8;
            else if (stringContains($device->cpu, "Deca")) return 10;
            else return null;
        }

        private function setCPU($device) {
            // Example: Quad-core (2x2.35 GHz Kryo & 2x2.0 GHz Kryo)
            if (!isset($device->cpu)) return null;

            $totProcessing = 0.0;
            $totActualCoreCount = self::getCoreCountFromWord($device);

            if (preg_match_all('/(\dx)?(\d*[.]\d*|\d*) GHz/', $device->cpu, $matches)) {
                $numCoresIndex = 1;
                $processingPowerIndex = 2;

                for ($groupIndex = 0; $groupIndex < 2; ++$groupIndex) {
                    if (isset($matches[$numCoresIndex][$groupIndex]) &&
                        isset($matches[$processingPowerIndex][$groupIndex])) {

                        $totProcessing += (double)($matches[$processingPowerIndex][$groupIndex]) *
                            (double)(substr($matches[$numCoresIndex][$groupIndex], 0, 1));
                    }
                }
            }

            // When total processing can't be determined (all cores same power)
            // Example: Quad-core 1.25 GHz Cortex-A53
            if ($totProcessing == 0)  {
                $totProcessing = $totActualCoreCount * self::getSingleProcessingValue($device->cpu);
            }

            return "Total Cores: " . $totActualCoreCount . "\tTotal Processing Power: " . $totProcessing;
        }

        private static function getSingleProcessingValue($cpu) {
            if (preg_match_all('/[^0-9]*(\d*[.]\d*)/', $cpu, $matches)) {
                return $matches[1][0];
            }
        }

        private function setInternalStorage($device)
        {
            if (!isset($device->internal)) return null;

            // Example: 64 GB, 4 GB RAM
            if (preg_match_all('/(\d*) (G|M)B, (\d*) (G|M)B RAM/', $device->internal, $matches)) {
                $storage = $matches[1][0];
                $ram = $matches[3][0];
                return "Storage: " . $storage . "\tRAM: " . $ram;
            }
        }

        private function setOS($device)
        {
            if (!isset($device->os)) return null;

            $os = self::getOSFromText($device);
            $osVersion = null;

            // Example: Android OS, v6.0.1 (Marshmallow)
            if (preg_match_all('/[^0-9]*(\d*[.]\d*[.]\d*|\d*[.]\d*|\d*)/', $device->os, $matches)) {
                $osVersion = $matches[1][0];
            }

            if (isNullOrEmpty($os) || isNullOrEmpty($osVersion)) self::logDevice($device, "Could not determine OS/Version.");
            return "OS: " . $os . "\tVersion: " . $osVersion;
        }

        private static function getOSFromText($device) {
            $osTypes = array("Android Wear", "Android", "iOS", "watchOS", "Tizen", "BlackBerry");

            foreach ($osTypes as $os) {
                if (stringContains($device->os, $os)) return $os;
            }

            return null;
        }

        private function setCamera($device) {
            $primary = (isset($device->primary)) ? self::getCameraMP($device->primary) : null;
            $secondary = (isset($device->secondary)) ? self::getCameraMP($device->secondary) : null;
            $video = (isset($device->video)) ? self::getVideoResolution($device->video) : null;

            return "Camera - Primary: " . $primary . "\tSecondary: " . $secondary . "\tVideo: " . $video . "p";
        }

        private static function getCameraMP($rawFeatureData)
        {
            // Example: 20 MP, f/2.2, 28mm, laser autofocus, dual-LED (dual tone) flash
            if (preg_match_all('/([^0-9]*)(\d*) MP/', $rawFeatureData, $matches)) {
                return $matches[2][0];
            }

            return null;
        }

        private static function getVideoResolution($rawFeatureData)
        {
            // Example: 1080p@30fps
            if (preg_match_all('/([^0-9]*)(\d*)/', $rawFeatureData, $matches)) {
                return $matches[0][0];
            }

            return null;
        }

        private function setTalkTime($device)
        {
            if (!isset($device->talk_time)) return null;

            // Example: Up to 22 h (2G) / Up to 13 h 30 min (3G)
            if (preg_match_all('/[^0-9]*'. self::$numericPattern . ' ?(h|H|HR|hr)/', $device->talk_time, $matches)) {
                $talkTime = $matches[1][0];

                return "Talk Time: " . $talkTime;
            }
        }

        private function setHeadphoneJack($device)
        {
            // Example: Yes
            return stringContains($device->_3_5mm_jack_, "Yes");
        }

        private function setDevicePrice($device) {
            // Gets the USD price of device based on current exchange rates
            if (!isset($device->price_group)) return null;

            if (preg_match_all('/'. self::$numericPattern . ' (EUR|USD|AUD|CAD|CZK|DKK|GBP|HKD|IDR|MXN|INR|JPY|NOK|NZD|RUB)/', $device->price_group, $matches)) {
                $deviceLocalPrice = $matches[1][0];
                $currency = $matches[2][0];

                //echo "Local Price: ${$deviceLocalPrice} in {$currency} <br>";

                if (isset($deviceLocalPrice) && isset($currency)) {
                    $device->Price = self::getUSDPrice($deviceLocalPrice, $currency);
                }
            }

            if (isset($device->Price) && !is_null($device->Price) && ($device->Price != 0)) {
                return "Price: $" . $device->Price;
            }
        }

        private static function getUSDPrice($devicePrice, $currency) {
            return round(($devicePrice / self::$exchangeRates->rates->$currency), 2);
        }

        private static function logDevice($device, $errorMessage)
        {
            // TODO: Log externally
            echo '<pre>';
            echo var_dump($device);
            echo '</pre>';
            echo $errorMessage;
        }

        private static function displayDeviceImage($device) {
            if (isset($device->DeviceIMG)) {
                return '<img src="' . $device->DeviceIMG . '" alt="' . $device->DeviceName . '">';
            }
        }
    }