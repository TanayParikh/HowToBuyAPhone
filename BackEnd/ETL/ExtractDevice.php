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
                    $devices = GsmAPI::getLatest(null, 1);
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
                } else if (stringContains($device->DeviceName, "Tab") || stringContains($device->DeviceName, "Pad")) {
                    echo '<br>' . $device->DeviceName . " was not processed as it is a tablet device." . '<br>'. '<br>';

                // Filters out devices previously scanned
                } else if (self::devicePreviouslyScanned($device))  {
                    echo '<br>' . $device->DeviceName . " was not processed as it was either scanned previously or is just rumoured in status." . '<br>'. '<br>';

                } else {
                    $validDevices[] = $device;
                }
            }

            return $validDevices;
        }

        public static function devicePreviouslyScanned($device) {
            // Creates a PDO statement and binds the appropriate parameters
            $db = Configuration::getConnection();

            if (!self::deviceAvailable($device->status)) return true;

            $date = self::getDateAnnounced($device->announced);
            if ($date == null) return true;

            $device->announced = $date;

            // Builds dynamic query
            $sql = build_query([
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
            return !(stringContains($status, "Rumored"));
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
