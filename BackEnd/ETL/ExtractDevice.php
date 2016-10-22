<?php
    include_once("DataSources/FonoAPI.php");
    include_once("DataSources/GsmAPI.php");
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/Configuration.php");
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/Functions/Functions.php");

    class ExtractDevice {
        const DATA_SOURCE = "customApi";

        public static function getDevices() {
            try {
                $devices = null;

                if (self::DATA_SOURCE == "fonoApi") {
                    $fonoapi = FonoApi::init(configuration::$apiKey);
                    $devices = $fonoapi::getLatest(null, 20);
                } else if (self::DATA_SOURCE == "customApi") {
                    $devices = GsmAPI::getLatest(null, 2);
                }

                $devices = self::validateDevices($devices);

                return $devices;
            } catch (Exception $e) {
                echo "ERROR : " . $e->getMessage();
            }
        }

        private static function validateDevices($allDevices) {
            $validDevices = array();

            foreach ($allDevices as $device) {
                // Filters out watches
                if (isset($device->os) && (stringContains($device->os, "Android Wear") || stringContains($device->os, "watchOS"))) {
                    echo '<br>' . $device->DeviceName . " was not processed as it is a watch device." . '<br>'. '<br>';

                // Filters out tablets
                } else if (isset($device->DeviceName) && (stringContains($device->DeviceName, "Tab") || stringContains($device->DeviceName, "Pad"))) {
                    echo '<br>' . $device->DeviceName . " was not processed as it is a tablet device." . '<br>' . '<br>';

                } else if (isset($device->status) && stringContains($device->status, "Rumored")) {
                    echo '<br>' . $device->DeviceName . " was not processed as it was just rumoured in status." . '<br>' . '<br>';

                // Filters out devices previously scanned
                } else if (self::deviceExists($device))  {
                    echo '<br>' . $device->DeviceName . " was not processed as it was scanned previously." . '<br>'. '<br>';
                } else {
                    $validDevices[] = $device;
                }
            }

            return $validDevices;
        }

        public static function deviceExists($device) {
            // Creates a PDO statement and binds the appropriate parameters
            $db = Configuration::getConnection();

            // Sets date
            $date = self::getDateAnnounced($device->announced);
            if ($date == null) return true;
            $device->announced = $date;

            $sql = "SELECT COUNT(*) FROM htbap.device WHERE name = :name AND release_date=:date_announced";

            $device_count_stmt = $db->prepare($sql);
            $device_count_stmt->bindParam(':name', $device->DeviceName, PDO::PARAM_INT);
            $device_count_stmt->bindValue(':date_announced', $date, PDO::PARAM_STR);
            // Executes query & returns whether entries matching the description are already in db
            try {
                if ($device_count_stmt->execute()) {
                    $count = ($device_count_stmt->fetchColumn());
                    return ($count != 0);
                } else {
                    echo '<br>' . "query failed" . '<br>';
                    return true;
                }
            }catch (Exception $ex) {
                echo 'error: ' . $ex->getMessage();
            }
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

        // $_SERVER['REMOTE_ADDR']
    }
?>
