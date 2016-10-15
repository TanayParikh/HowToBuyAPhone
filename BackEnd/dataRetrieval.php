<?php
	include_once("fonoAPI.php");
	include_once("configuration.php");

	class dataRetrieval {
		public static function fetchDevices() {
			
			$fonoapi = fonoApi::init(configuration::$apiKey);

			try {
				$devices = $fonoapi::getLatest();

				foreach ($devices as $device) {
					self::scanDevice($device);
				}

			} catch (Exception $e) {
				echo "ERROR : " . $e->getMessage();
			}
		}

		public static function scanDevice($device) {
			if (!empty($device->DeviceName))    echo "Device : ". $device->DeviceName . "<br>";
			if (!empty($device->announced))         echo "announced : ". $device->announced . "<br>";
			if (!empty($device->status))         echo "status : ". $device->status . "<br>";

			if (self::devicePreviouslyScanned($device)) //return;
			{ echo "scanned previously"; } else { echo "not scanned"; }
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
			//$date = '10, 30, 2016';
			$date = null;

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



			//$query = "SELECT device_id FROM devices_scanned WHERE device_name IS NULL";
			/*$result = $db->prepare($query);
			//$result ->bindParam(':p', $q, PDO::PARAM_INT);
			$result->execute();
			$rows = $result->rowCount();*/
			$res = $device_count_stmt->execute();

			// Executes query
			if ($res) {
				// Ensures specification of that search level exist
				//echo '<br>' . $device_count_stmt->fetchColumn() . '<br>';
				echo 'success: '. $device_count_stmt->fetchColumn(). '   ';
				return (($device_count_stmt->fetchColumn()) > 0);
			} else {

				echo '<br>' . "query failed" . '<br>';
			}
		}
	}
?>
