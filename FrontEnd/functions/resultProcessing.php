<?php
	// Setting the cookie
	setcookie( "TestCookie", $value, strtotime( '+30 days' ) );

	function start_processing() {
		//$selected_options = get_selected_options();
		//$search_level = get_search_level();

		// Temp structure for testing
		$selectedOptions = array(
		    "brand" => "Samsung",
		    "release_date" => "10/10/2016",
		);

		$db = get_connection();

		try {
			$devices = get_device_ids(selectedOptions["brand"], selectedOptions["release_date"]);

			foreach($devices as $device) {
				echo "Device ID: " . $device;
			}

			unset($device);
		} catch {
			echo "Failed to start processing";
		}
	}

	function get_search_level() {
		if (isset($_COOKIE['search_level']) && valid_search_level($_COOKIE['search_level'])) {
			return $_COOKIE['search_level'];
		} else {
			return "regular";
		}
	}

	function valid_search_level($level) {
		// Creates a PDO statement and binds the appropriate parameters
		$search_level_count_stmt = $db->prepare("SELECT COUNT(*) FROM specification_definition WHERE level = :level");
		$search_level_count_stmt->bindParam(':level', $level);

		// Executes query
		$search_level_count_stmt->execute();

		// Ensures specification of that search level exist
		return (($search_level_count_stmt->fetchColumn()) > 0);
	}

	function get_selected_options() {
		/*
		// set the cookies
		setcookie("cookie[three]", "cookiethree");
		setcookie("cookie[two]", "cookietwo");
		setcookie("cookie[one]", "cookieone");
		*/

		// Determine assoc naming convention and apply here
		// Verify all necessary values are there
		if (isset($_COOKIE['cookie'])) {
			foreach ($_COOKIE['cookie'] as $name => $value) {
				$name = htmlspecialchars($name);
				$value = htmlspecialchars($value);
				echo "$name : $value <br />\n";
			}

			return $_COOKIE['cookie'];
		}

		return null;
	}

	function get_device_ids($brands, $release_date) {
		$get_devices_query = "SELECT device_id FROM devices WHERE release_date >= :release_date"
		$get_devices_query .= " AND ((:brands IS NULL) OR (brand = ANY(:brands::TEXT[])))"

		$brands = ($brands == "All") ? null : explode(',', $brands);

		// Creates a PDO statement and binds the appropriate parameters
		$get_devices_stmt = $db->prepare($get_devices_query);
		$get_devices_stmt->bindParam(':release_date', $release_date);
		$get_devices_stmt->bindParam(':brands', $brands);

		// Executes query
		//$get_devices_stmt->setFetchMode(PDO::FETCH_ASSOC);
		$get_devices_stmt->execute();
		return $get_devices_stmt->fetchAll(PDO::FETCH_COLUMN);
	}
?>
