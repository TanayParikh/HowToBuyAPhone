<?php
	include_once("fonoAPI.php");
	include_once("configuration.php");

	class dataRetrieval {
        private static $numericPattern = '(\d*[.]\d*|\d*)';
        
		public static function fetchDevices() {

			$fonoapi = fonoApi::init(configuration::$apiKey);

			try {
				$devices = $fonoapi::getLatest(null, 50);

				foreach ($devices as $device) {
					self::scanDevice($device);
				}

			} catch (Exception $e) {
				echo "ERROR : " . $e->getMessage();
			}
		}

		public static function scanDevice($device) {

			if (self::devicePreviouslyScanned($device)) //return;
			{
				echo '<br>' . $device->DeviceName . " was not processed as it was either scanned previously or is just rumoured in status." . '<br>'. '<br>';
			} else {
			    $output = '<br>' . self::setDimensions($device->dimensions) . '<br>';
				$output .= self::setWeight($device->weight) . '<br>';
                $output .= self::setScreenSize($device->size) . '<br>';
                $output .= self::setScreenResolution($device->resolution) . '<br>';
                $output .=  '<br>';
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

			if (!empty($device->DeviceName))    echo "Device : ". $device->DeviceName . "<br>";
			if (!empty($device->announced))         echo "announced : ". $device->announced . "<br>";
			if (!empty($device->status))         echo "status : ". $device->status . "<br>";

			$date = self::getDateAnnounced($device->announced);
            if ($date == null) return true;

            // Builds dynamic query
			$sql = self::build_query([
					[               "SELECT COUNT(*) FROM htbap.devices_scanned WHERE device_name = :name"],
					[$date         ,' AND ', 'date_announced=:date_announced'],
					[$device->status      ,' AND ',   'status=:status']
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
			return !(/*strpos($status, "Coming soon") !== false) || */ self::stringContains($status, "Rumored"));
		}

		static function stringContains($haystack, $needle) {
			return (strpos($haystack, $needle) !== false);
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

                    // Display dates
                    echo 'Parsed Date: ' . date_format($date, 'Y-m-d');

					return date_format($date, 'Y-m-d');
				}
			} catch (Exception $ex) {
				echo 'Could not convert ' . $rawDate . '\t' . $ex->getMessage() . '<br>';
				return null;
			}
		}

		static function setDimensions($dimensions) {
		    // Example: 142.8 x 69.6 x 8.1 mm (5.62 x 2.74 x 0.32 in)

		    if ($dimensions == "-") return;

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
            if (preg_match_all("/".'(\d*)( x )(\d*)( pixels)(.*~)(\d*)( ppi)(.*)'."/", $rawResolution, $matches)) {
                $resolution = $matches[1][0] . 'x' . $matches[3][0];
                $ppi = $matches[6][0];
                return "Resolution: " . $resolution . "\tPPI: " . $ppi;
            }
        }
    }
?>
