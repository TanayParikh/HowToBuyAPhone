<?php
    include_once("fonoAPI.php");
    include_once("configuration.php");
    include_once ("API/api.php");

    class dataRetrieval {
        private static $numericPattern = '(\d*[.]\d*|\d*)';
        const DATA_SOURCE = "customApi";

        public static function startProcessing() {
            $devices = self::fetchDevices();

            foreach ($devices as $device) {
                self::scanDevice($device);
            }
        }

        private static function fetchDevices() {
            try {
                $devices = null;

                if (self::DATA_SOURCE == "fonoApi") {
                    $fonoapi = fonoApi::init(configuration::$apiKey);
                    $devices = $fonoapi::getLatest(null, 20);
                } else if (self::DATA_SOURCE == "customApi") {
                    $devices = API::getLatest();
                }

                // Sanitizes data objects such that return from both data sources may be treated the same.
                //$devices = self::sanitizeDevices($devices);

                return $devices;
            } catch (Exception $e) {
                echo "ERROR : " . $e->getMessage();
            }
        }

        private static function sanitizeDevices($devices) {
            //if (isset())

            return $devices;
        }

        private static function scanDevice($device) {

            if (isset($device->os) && (self::stringContains($device->os, "Android Wear") || self::stringContains($device->os, "watchOS"))) {
                echo '<br>' . $device->DeviceName . " was not processed as it is a watch device." . '<br>'. '<br>';
            } else if (self::stringContains($device->DeviceName, "Tab") || self::stringContains($device->DeviceName, "Pad")) {
                echo '<br>' . $device->DeviceName . " was not processed as it is a tablet device." . '<br>'. '<br>';
            } else if (self::devicePreviouslyScanned($device))  {
                echo '<br>' . $device->DeviceName . " was not processed as it was either scanned previously or is just rumoured in status." . '<br>'. '<br>';
            } else {
                if (!empty($device->DeviceName))    		echo "Device: ". $device->DeviceName . "<br>";
                if (!empty($device->announced))         echo "announced: ". $device->announced . "<br>";
                if (!empty($device->status))         		echo "status: ". $device->status . "<br>";
                self::displayImage($device);
                $output =  self::setDimensions($device->dimensions) . '<br>';
                $output .= self::setWeight($device->weight) . '<br>';
                $output .= self::setScreenSize($device->size) . '<br>';
                $output .= self::setScreenResolution($device->resolution) . '<br>';
                $output .= self::setExpandableStorage($device) . '<br>';
                $output .= self::setBluetoothVersion($device) . '<br>';
                $output .= (self::isBatteryRemovable($device) ? "Removable" : "Non-removable") . ' Battery <br>';
                $output .= self::setBatteryCapacity($device) . '<br>';
                // $output .= self::displayFeatures($device) . '<br>';
                $output .= self::setCPU($device) . '<br>';
                $output .= self::setInternalStorage($device) . '<br>';
                $output .= self::setOS($device) . '<br>';
                $output .= self::setCamera($device) . '<br>';
                $output .= (self::setHeadphoneJack($device) ? "3mm Jack" : "No 3mm Jack") . '<br>';

                $output .=  '<br> <br> <br>';
                echo $output;
            }
            // Parse device here
        }

        // Reference: http://www.gabordemooij.com/index.php?p=/tiniest_query_builder
        private static function build_query($pieces) {
            $sql = '';
            $glue = NULL;

            foreach( $pieces as $piece ) {
                $n = count( $piece );

                switch( $n ) {
                    case 1:
                        $sql .= " {$piece[0]} ";
                        break;
                    case 2:
                        $glue = NULL;
                        if (!is_null($piece[0])) $sql .= " {$piece[1]} ";
                        break;
                    case 3:
                        $glue = ( is_null( $glue ) ) ? $piece[1] : $glue;
                        if (!is_null($piece[0])) {
                            $sql .= " {$glue} {$piece[2]} ";
                            $glue = NULL;
                        }
                        break;
                }
            }

            return $sql;
        }

        public static function devicePreviouslyScanned($device) {
            // Creates a PDO statement and binds the appropriate parameters
            $db = configuration::getConnection();

            if (!self::deviceAvailable($device->status)) return true;

            $date = self::getDateAnnounced($device->announced);
            if ($date == null) return true;

            $device->announced = $date;

            // Builds dynamic query
            $sql = self::build_query([
                [										"SELECT COUNT(*) FROM htbap.devices_scanned WHERE device_name = :name"],
                [$date         			,' AND ', 'date_announced=:date_announced'],
                [$device->status    ,' AND ',   'status=:status']
            ]);

            $device_count_stmt = $db->prepare($sql);
            $device_count_stmt->bindParam(':name', $device->DeviceName, PDO::PARAM_INT);

            // Optional bindings.
            $date &&       $device_count_stmt->bindValue(':date_announced', $date, \PDO::PARAM_STR);
            $device->status &&    $device_count_stmt->bindValue(':status', $device->status, \PDO::PARAM_STR);

            // Executes query & returns whether entries matching the description are already in db
            if ($device_count_stmt->execute()) {
                return (($device_count_stmt->fetchColumn()) > 0);
            } else {
                echo '<br>' . "query failed" . '<br>';
                return false;
            }
        }

        static function deviceAvailable($status) {
            return !(self::stringContains($status, "Rumored"));
        }

        static function stringContains($haystack, $needle) {
            return (strpos($haystack, $needle) !== false);
        }

        static function isNullOrEmpty($rawText){
            return (!isset($rawText) || empty($rawText));
        }

        static function getDateAnnounced($rawDate) {
            try {
                $generalCharacterPattern='.*?';
                $yearPattern='((?:(?:[1]{1}\\d{1}\\d{1}\\d{1})|(?:[2]{1}\\d{3})))(?![\\d])';
                $singleCharacterPattern ='(.)';
                $monthsOfYearPattern='((?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:tember)?|Sept|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?))';

                if ($c=preg_match_all("/".$generalCharacterPattern.$yearPattern.$singleCharacterPattern.$singleCharacterPattern.$monthsOfYearPattern."/is", $rawDate, $matches))
                {
                    $year=$matches[1][0];
                    $month=$matches[4][0];

                    $date = date_create_from_format('Y, F, j', $year . ", " . $month . ", " . "1");

                    return date_format($date, 'Y-m-d');
                }
            } catch (Exception $ex) {
                echo 'Could not convert ' . $rawDate . '\t' . $ex->getMessage() . '<br>';
                return null;
            }
        }

        static function setDimensions($dimensions) {
            // Example: 142.8 x 69.6 x 8.1 mm (5.62 x 2.74 x 0.32 in)

            if ($dimensions == "-") return null;

            $dimensionSeparatorPattern = '( x )?';

            if (preg_match_all("/".self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.$dimensionSeparatorPattern.self::$numericPattern.'( mm)?(.*)'."/", $dimensions, $matches))
            {
                $length=$matches[1][0];
                $width=$matches[3][0];
                $thickness=$matches[5][0];

                if (self::stringContains($dimensions, "thickness")) {
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

        private static function setWeight($rawWeight) {
            // Example: 142 g (5.01 oz)
            $weight = self::getNumericFromString($rawWeight, ' g');
            if (!empty($weight)) return "Weight: " . $weight;
        }

        private static function setScreenSize($rawScreenSize) {
            // Example: 7.0 inches (~68.1% screen-to-body ratio)
            $screenSize = self::getNumericFromString($rawScreenSize, ' inches');
            if (!empty($screenSize)) return "Screen Size: " . $screenSize;
        }

        private static function setScreenResolution($rawResolution)
        {
            // Example: 480 x 854 pixels (~196 ppi pixel density)
            if (preg_match_all("/".'(\d*)( x )(\d*)( pixels)(.*~)(\d*)( ppi)(.*)'."/", $rawResolution, $matches)) {
                $resolution = $matches[1][0] . 'x' . $matches[3][0];
                $ppi = $matches[6][0];
                return "Resolution: " . $resolution . "\tPPI: " . $ppi;
            }
        }

        private static function displayFeatures($device) {
            echo nl2br($device->features_c);
        }

        private static function setExpandableStorage($device) {
            // Example: microSD, up to 64 GB
            if ($device->card_slot == "No") return null;

            if (preg_match_all('/(microSD, up to )(\d*)( GB)/', $device->card_slot, $matches)) {
                $cardStorageAmount = $matches[2][0];
                return "Card Storage Amount: " . $cardStorageAmount;
            }

            // A value of true -> 1 indicates expandable storage, of unknown max capacity
            return self::stringContains($device->card_slot, "microSD");
        }

        private static function setBluetoothVersion($device) {
            // Example: v4.1, A2DP, LE
            if ($device->bluetooth == "No") return null;
            if ($device->bluetooth == "Yes") return 0;

            if (preg_match_all('/(v?)'.self::$numericPattern.'(.*)/', $device->bluetooth, $matches)) {
                $cardStorageAmount = $matches[2][0];
                return "Bluetooth Version: " . $cardStorageAmount;
            }

            self::logDevice($device, "Could not determine bluetooth version.");
        }

        private static function isBatteryRemovable($device) {
            // Note case sensitive (Non-removable is other type)
            return (self::stringContains($device->battery_c, "Removable"));
        }

        private static function setBatteryCapacity($device) {
            if (preg_match_all('/([\d]+) mAh/', $device->battery_c, $matches)) {
                $batteryCapacity = $matches[1][0];
                return "Battery Capacity: " . $batteryCapacity;
            }

            self::logDevice($device, "Could not determine battery capacity.");
        }

        private static function getCoreCountFromWord($device) {
            // Example: Quad-core
            if (self::stringContains($device->cpu, "Single")) return 1;
            else if (self::stringContains($device->cpu, "Dual")) return 2;
            else if (self::stringContains($device->cpu, "Quad")) return 4;
            else if (self::stringContains($device->cpu, "Hexa")) return 6;
            else if (self::stringContains($device->cpu, "Octa")) return 8;
            else if (self::stringContains($device->cpu, "Deca")) return 10;
            else return null;
        }

        private static function setCPU($device) {
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

        private static function setInternalStorage($device)
        {
            if (!isset($device->internal)) return null;

            // Example: 64 GB, 4 GB RAM
            if (preg_match_all('/(\d*) (G|M)B, (\d*) (G|M)B RAM/', $device->internal, $matches)) {
                $storage = $matches[1][0];
                $ram = $matches[3][0];
                return "Storage: " . $storage . "\tRAM: " . $ram;
            }
        }

        private static function setOS($device)
        {
            if (!isset($device->os)) return null;

            $os = self::getOSFromText($device);
            $osVersion = null;

            // Example: Android OS, v6.0.1 (Marshmallow)
            if (preg_match_all('/[^0-9]*(\d*[.]\d*[.]\d*|\d*[.]\d*|\d*)/', $device->os, $matches)) {
                $osVersion = $matches[1][0];
            }

            if (self::isNullOrEmpty($os) || self::isNullOrEmpty($osVersion)) self::logDevice($device, "Could not determine OS/Version.");
            return "OS: " . $os . "\tVersion: " . $osVersion;
        }

        private static function getOSFromText($device) {
            $osTypes = array("Android Wear", "Android", "iOS", "watchOS", "Tizen", "BlackBerry");

            foreach ($osTypes as $os) {
                if (self::stringContains($device->os, $os)) return $os;
            }

            return null;
        }

        private static function setCamera($device) {
            $primary = (isset($device->primary_)) ? self::getCameraMP($device->primary_) : null;
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

        private static function setHeadphoneJack($device)
        {
            // Example: Yes
            return self::stringContains($device->_3_5mm_jack_, "Yes");
        }

        private static function logDevice($device, $errorMessage)
        {
            // TODO: Log externally
            echo '<pre>';
            echo var_dump($device);
            echo $errorMessage;
            echo '</pre>';
        }

        // Rough prototype implementation (To fix)
        private static function displayImage($device)
        {
            $brand = str_replace(' ', '-', strtolower($device->Brand));
            $deviceName = str_replace(' ', '-', strtolower($device->DeviceName));
            $imgURL1 = 'http://cdn2.gsmarena.com/vv/pics/'.$brand.'/'.$deviceName;

            if (self::checkRemoteFile($imgURL1)) {
                self::fetchImage($imgURL1);
                return null;
            }

            if (explode(' ',trim($device->DeviceName))[0] == $device->Brand) {
                $deviceName = substr(strstr($deviceName,"-"), 1);
                $imgURL2 = 'http://cdn2.gsmarena.com/vv/pics/'.$brand.'/'.$deviceName;

                if (self::checkRemoteFile($imgURL2)) {
                    self::fetchImage($imgURL2);
                    return null;
                }


            }

            if (self::checkRemoteFile($imgURL1 . "-1")) {
                self::fetchImage($imgURL1 . "-1");
                return null;
            } else if (self::checkRemoteFile($imgURL2 . "-1")) {
                self::fetchImage($imgURL2 . "-1");
                return null;
            }
        }

        private static function fetchImage($imgURL) {
            echo '<IMG SRC="'. $imgURL .'.jpg' .  '" ALT="some text">';
        }

        // Reference: http://stackoverflow.com/questions/1363925/check-whether-image-exists-on-remote-url
        private static function checkRemoteFile($url)
        {
            $url .= '.jpg';
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL,$url);
            // don't download content
            curl_setopt($ch, CURLOPT_NOBODY, 1);
            curl_setopt($ch, CURLOPT_FAILONERROR, 1);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            return (curl_exec($ch)!==FALSE);
        }

        // $_SERVER['REMOTE_ADDR']
    }
?>
