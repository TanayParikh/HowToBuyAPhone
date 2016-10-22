<?php

    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");

    class TransformDevice
    {
        static $displayDevice;
        static $specificationDefinitions;

        private static $numericPattern = '(\d*[.]\d*|\d*)';
        private static $exchangeRates;

        private $rawDevice = null;
        private $transformedDevice;

        function __construct($device) {
            $this->rawDevice = $device;
        }

        function transformDevice() {
            $output = $this->setDeviceIdentifiers();
            $output .= $this->setDimensions() . '<br>';
            $output .= $this->setWeight() . '<br>';
            $output .= $this->setScreenSize() . '<br>';
            $output .= $this->setScreenResolution() . '<br>';
            $output .= $this->setExpandableStorage() . '<br>';
            $output .= $this->setBluetoothVersion() . '<br>';
            $output .= ($this->setRemovableBattery() ? "Removable" : "Non-removable") . ' Battery <br>';
            $output .= $this->setBatteryCapacity() . '<br>';
            $output .= $this->setCPU() . '<br>';
            $output .= $this->setInternalStorage() . '<br>';
            $output .= $this->setOS() . '<br>';
            $output .= $this->setCamera() . '<br>';
            $output .= $this->setTalkTime() . '<br>';
            $output .= ($this->setHeadphoneJack() ? "3mm Jack Present" : "No 3mm Jack") . '<br>';
            $output .= $this->setDevicePrice() . '<br>';

            $output .=  '<br> <br> <br>';

            if (self::$displayDevice) echo $output;

            return $this->transformedDevice;
            //self::logDevice($this->transformedDevice);
        }

        private function setDeviceIdentifiers() {
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

            if (!isNullOrEmpty($this->rawDevice->Source_URL)) {
                $this->transformedDevice->Source_URL = $this->rawDevice->Source_URL;
            }

            return $output;
        }

        public static function init($displayDevice = false) {
            self::$displayDevice = $displayDevice;
            self::getExchangeRates();
            //SpecificationDefinition::init();
        }

        private static function getExchangeRates () {
            $jsonExchangeRates = file_get_contents('http://api.fixer.io/latest?base=USD');
            self::$exchangeRates = json_decode($jsonExchangeRates);
            self::$exchangeRates->USD = 1;
        }

        private function setDimensions() {
            // Example: 142.8 x 69.6 x 8.1 mm (5.62 x 2.74 x 0.32 in)
            if (!isset($this->rawDevice->dimensions) || ($this->rawDevice->dimensions == "-")) return null;

            $dimensionSeparatorPattern = '( x )?';

            if (preg_match_all("/".self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.'( mm)?(.*)/', $this->rawDevice->dimensions, $matches))
            {
                $length=$matches[1][0];
                $width=$matches[3][0];
                $thickness=$matches[5][0];

                if (stringContains($this->rawDevice->dimensions, "thickness")) {
                    $thickness = $matches[1][0];
                    $this->transformedDevice->$thickness = $thickness;
                    return "Thickness: " . $thickness;
                } else {
                    $this->transformedDevice->length = $length;
                    $this->transformedDevice->width = $width;
                    $this->transformedDevice->thickness = $thickness;
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

        private function setWeight() {
            // Example: 142 g (5.01 oz)
            if (isNullOrEmpty($this->rawDevice->weight)) return null;

            $weight = self::getNumericFromString($this->rawDevice->weight, ' g');
            $this->transformedDevice->weight = $weight;
            return "Weight: " . $weight;
        }

        private function setScreenSize() {
            // Example: 7.0 inches (~68.1% screen-to-body ratio)
            if (isNullOrEmpty($this->rawDevice->size)) return null;

            $screenSize = self::getNumericFromString($this->rawDevice->size, ' inches');
            $this->transformedDevice->screen_size = $screenSize;
            if (!empty($screenSize)) return "Screen Size: " . $screenSize;
        }

        private function setScreenResolution() {
            // Example: 480 x 854 pixels (~196 ppi pixel density)
            if (isNullOrEmpty($this->rawDevice->resolution)) return null;

            if (preg_match_all("/".'(\d*)( x )(\d*)( pixels)(.*~)(\d*)( ppi)(.*)'."/", $this->rawDevice->resolution, $matches)) {
                $resolution = $matches[1][0] . 'x' . $matches[3][0];
                $ppi = $matches[6][0];

                $this->transformedDevice->resolution = $resolution;
                $this->transformedDevice->ppi = $ppi;

                return "Resolution: " . $resolution . "\tPPI: " . $ppi;
            }
        }

        private function setExpandableStorage() {
            // Example: microSD, up to 64 GB
            if (stringContains($this->rawDevice->card_slot, "No")) return null;
            $cardStorageAmount = null;

            if (preg_match_all('/(microSD, up to )(\d*)( GB)/', $this->rawDevice->card_slot, $matches)) {
                $cardStorageAmount = $matches[2][0];
                $this->transformedDevice->expandable_capacity = $cardStorageAmount;
                return "Card Storage Amount: " . $cardStorageAmount;
            }

            // Indicates expandable storage, of unknown max capacity
            if (stringContains($this->rawDevice->card_slot, "microSD")) {
                // Defaults to 32 GB Max
                $this->transformedDevice->expandable_capacity = 32;
                return "Card Storage Amount: Unknown";
            }
        }

        private function setBluetoothVersion() {
            // Example: v4.1, A2DP, LE
            if ($this->rawDevice->bluetooth == "No") return null;
            if ($this->rawDevice->bluetooth == "Yes") {
                // Assumes 2.1 default bluetooth version
                $this->transformedDevice->bluetooth = 2.1;
                return "Bluetooth Version: " . 2.1;
            }

            if (preg_match_all('/(v?)'.self::$numericPattern.'(.*)/', $this->rawDevice->bluetooth, $matches)) {
                $bluetooth = $matches[2][0];
                $this->transformedDevice->bluetooth = $bluetooth;
                return "Bluetooth Version: " . $bluetooth;
            }

            self::logDevice($this->rawDevice, "Could not determine bluetooth version.");
        }

        private function setRemovableBattery() {
            // Note case sensitive (Non-removable is other type)
            $removableBattery = stringContains($this->rawDevice->battery_c, "Removable");
            $this->transformedDevice->removable_battery = $removableBattery;
            return $removableBattery;
        }

        private function setBatteryCapacity() {
            if (preg_match_all('/([\d]+) mAh/', $this->rawDevice->battery_c, $matches)) {
                $batteryCapacity = $matches[1][0];
                $this->transformedDevice->battery_capacity = $batteryCapacity;
                return "Battery Capacity: " . $batteryCapacity;
            }

            self::logDevice($this->rawDevice, "Could not determine battery capacity.");
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

        private function setCPU() {
            // Example: Quad-core (2x2.35 GHz Kryo & 2x2.0 GHz Kryo)
            if (!isset($this->rawDevice->cpu)) return null;

            $totProcessing = 0.0;
            $totActualCoreCount = self::getCoreCountFromWord($this->rawDevice);

            if (preg_match_all('/(\dx)?(\d*[.]\d*|\d*) GHz/', $this->rawDevice->cpu, $matches)) {
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
                $totProcessing = $totActualCoreCount * self::getSingleProcessingValue($this->rawDevice->cpu);
            }

            $this->transformedDevice->num_cores = $totActualCoreCount;
            $this->transformedDevice->processing_power = $totProcessing;
            return "Total Cores: " . $totActualCoreCount . "\tTotal Processing Power: " . $totProcessing;
        }

        private static function getSingleProcessingValue($cpu) {
            if (preg_match_all('/[^0-9]*(\d*[.]\d*)/', $cpu, $matches)) {
                return $matches[1][0];
            }
        }

        private function setInternalStorage() {
            if (!isset($this->rawDevice->internal)) return null;

            // Example: 64 GB, 4 GB RAM
            if (preg_match_all('/(\d*) (G|M)B, (\d*) (G|M)B RAM/', $this->rawDevice->internal, $matches)) {
                $storage = $matches[1][0];
                $ram = $matches[3][0];

                $this->transformedDevice->storage = $storage;
                $this->transformedDevice->ram = $ram;
                return "Storage: " . $storage . "\tRAM: " . $ram;
            }
        }

        private function setOS() {
            if (!isset($this->rawDevice->os)) return null;

            $os = self::getOSFromText($this->rawDevice);
            $osVersion = null;

            // Example: Android OS, v6.0.1 (Marshmallow)
            if (preg_match_all('/[^0-9]*(\d*[.]\d*[.]\d*|\d*[.]\d*|\d*)/', $this->rawDevice->os, $matches)) {
                $osVersion = $matches[1][0];
            }

            if (isNullOrEmpty($os) || isNullOrEmpty($osVersion)) self::logDevice($this->rawDevice, "Could not determine OS/Version.");
            $this->transformedDevice->os = $os;
            $this->transformedDevice->os_version = $osVersion;
            return "OS: " . $os . "\tVersion: " . $osVersion;
        }

        private static function getOSFromText($device) {
            $osTypes = array("Android Wear", "Android", "iOS", "watchOS", "Tizen", "BlackBerry");

            foreach ($osTypes as $os) {
                if (stringContains($device->os, $os)) return $os;
            }
        }

        private function setCamera() {
            $primary = (isset($this->rawDevice->primary)) ? self::getCameraMP($this->rawDevice->primary) : null;
            $secondary = (isset($this->rawDevice->secondary)) ? self::getCameraMP($this->rawDevice->secondary) : null;
            $video = (isset($this->rawDevice->video)) ? self::getVideoResolution($this->rawDevice->video) : null;

            $this->transformedDevice->primary = $primary;
            $this->transformedDevice->secondary = $secondary;
            $this->transformedDevice->video = $video;
            return "Camera - Primary: " . $primary . "\tSecondary: " . $secondary . "\tVideo: " . $video . "p";
        }

        private static function getCameraMP($rawFeatureData)
        {
            // Example: 20 MP, f/2.2, 28mm, laser autofocus, dual-LED (dual tone) flash
            if (preg_match_all('/([^0-9]*)(\d*) MP/', $rawFeatureData, $matches)) {
                return $matches[2][0];
            }
        }

        private static function getVideoResolution($rawFeatureData)
        {
            // Example: 1080p@30fps
            if (preg_match_all('/([^0-9]*)(\d*)/', $rawFeatureData, $matches)) {
                return $matches[0][0];
            }
        }

        private function setTalkTime()
        {
            if (!isset($this->rawDevice->talk_time)) return null;

            // Example: Up to 22 h (2G) / Up to 13 h 30 min (3G)
            if (preg_match_all('/[^0-9]*'. self::$numericPattern . ' ?(h|H|HR|hr)/', $this->rawDevice->talk_time, $matches)) {
                $talkTime = $matches[1][0];

                $this->transformedDevice->talk_time = $talkTime;
                return "Talk Time: " . $talkTime;
            }
        }

        private function setHeadphoneJack()
        {
            // Example: Yes
            $jackPresent = stringContains($this->rawDevice->_3_5mm_jack_, "Yes");
            $this->transformedDevice->_3_5mm_jack_ = $jackPresent;
            return $jackPresent;
        }

        private function setDevicePrice() {
            // Gets the USD price of device based on current exchange rates
            if (!isset($this->rawDevice->price_group)) return null;
            $price = null;

            if (preg_match_all('/'. self::$numericPattern . ' (EUR|USD|AUD|CAD|CZK|DKK|GBP|HKD|IDR|MXN|INR|JPY|NOK|NZD|RUB)/', $this->rawDevice->price_group, $matches)) {
                $deviceLocalPrice = $matches[1][0];
                $currency = $matches[2][0];

                //echo "Local Price: ${$deviceLocalPrice} in {$currency} <br>";

                if (isset($deviceLocalPrice) && isset($currency)) {
                    $price = self::getUSDPrice($deviceLocalPrice, $currency);
                }
            }

            if (!isNullOrEmpty($price) && ($price != 0)) {
                $this->transformedDevice->price = $price;
                return "Price: $" . $price;
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