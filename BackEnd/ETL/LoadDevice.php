<?php
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/Configuration.php");
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");

    class LoadDevice
    {

        public static function exportDeviceToFile($device)
        {
            // TODO: Account for files of the same name
            $filePath = "{$device->DeviceName}.txt";
            $filePath = self::sanitizeFileName($filePath);
            $filePath = $_SERVER['DOCUMENT_ROOT'] . "/BackEnd/RawDevices/" . $filePath;

            // Encodes device object
            $encodedObject = json_encode($device, JSON_PRETTY_PRINT);

            // Exports to file
            file_put_contents($filePath, $encodedObject);
        }

        public static function loadTransformedDevice($transformedDevice)
        {
            $id = self::insertDevice($transformedDevice);
            $transformedDevice = (array) $transformedDevice;

            foreach ($transformedDevice as $objectKey => $value) {
                self::insertSpecification($id, $objectKey, $value);
                //break;
            }
        }

        private static function insertSpecification($deviceID, $objectKey, $value) {
            $db = Configuration::getConnection();

            $specificationID = SpecificationDefinition::getIDFromObjectKey($objectKey);

            $stmt = $db->prepare("INSERT INTO htbap.device_specification (device_id, specification_id, value) VALUES(:deviceID, :specificationID, :value);");

            $stmt->bindParam(':deviceID', $deviceID, PDO::PARAM_INT);
            $stmt->bindParam(':specificationID', $specificationID, PDO::PARAM_INT);
            $stmt->bindParam(':value', $value, PDO::PARAM_STR);

            try {
                // Executes query & returns whether entries matching the description are already in db
                if ($stmt->execute()) {
                    return true;
                } else {
                    echo '<br>' . "query failed" . '<br>';
                    return false;
                }
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }

        private static function insertDevice($transformedDevice) {
            $db = Configuration::getConnection();

            $fieldsToInsert = "name, brand, release_date,";
            $values = ":name, :brand, :date,";
            if (isset($transformedDevice->DeviceIMG)) {
                $fieldsToInsert .= "img_url,";
                $values .= ":img,";
            }

            if (isset($transformedDevice->Source_URL)) {
                $fieldsToInsert .= "source_url,";
                $values .= ":source,";
            }

            $fieldsToInsert = removeTrailingComma($fieldsToInsert);
            $values = removeTrailingComma($values);

            $stmt = $db->prepare("INSERT INTO htbap.device ($fieldsToInsert) VALUES($values) RETURNING id;");

            $stmt->bindParam(':name', $transformedDevice->DeviceName, PDO::PARAM_STR);
            $stmt->bindParam(':brand', $transformedDevice->Brand, PDO::PARAM_STR);
            $stmt->bindParam(':date', $transformedDevice->announced, PDO::PARAM_STR);
            if (isset($transformedDevice->DeviceIMG)) $stmt->bindParam(':img', $transformedDevice->DeviceIMG, PDO::PARAM_STR);
            if (isset($transformedDevice->Source_URL)) $stmt->bindParam(':source', $transformedDevice->Source_URL, PDO::PARAM_STR);

            try {
                // Executes query & returns whether entries matching the description are already in db
                if ($stmt->execute()) {
                    unset($transformedDevice->DeviceName);
                    unset($transformedDevice->Brand);
                    unset($transformedDevice->announced);
                    unset($transformedDevice->status);
                    unset($transformedDevice->DeviceIMG);
                    unset($transformedDevice->Source_URL);

                    // Returns device id
                    return $stmt->fetchColumn(0);
                } else {
                    echo '<br>' . "query failed" . '<br>';
                    return false;
                }
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }

        private static function sanitizeFileName($filePath)
        {
            // Remove anything which isn't a word, whitespace, number
            // or any of the following characters -_~,;[]().
            // If you don't need to handle multi-byte characters
            // you can use preg_replace rather than mb_ereg_replace
            $filePath = mb_ereg_replace('([^\w\s\d\-_~,;\[\]\(\).])', '', $filePath);

            // Remove any runs of periods (thanks falstro!)
            $filePath = mb_ereg_replace('([\.]{2,})', '', $filePath);
            return $filePath;
        }
    }
