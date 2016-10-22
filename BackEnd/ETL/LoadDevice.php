<?php
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/Configuration.php");
    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/SpecificationDefinition.php");

    class LoadDevice
    {

        public static function loadRawDevice($rawDevice)
        {

        }

        public static function loadTransformedDevice($transformedDevice)
        {
            $id = self::createDevice($transformedDevice);
            $transformedDevice = (array) $transformedDevice;



            foreach ($transformedDevice as $objectKey => $value) {

            }
        }

        private static function createDevice($transformedDevice)
        {
            // Creates a PDO statement and binds the appropriate parameters
            $db = Configuration::getConnection();

            $id =  self::insertIntoTblDevice($transformedDevice, $db);
        }

        private static function insertIntoTblDevice($transformedDevice, $db)
        {
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

            $stmt = $db->prepare("INSERT INTO htbap.device ($fieldsToInsert) VALUES($values);");

            $stmt->bindParam(':name', $transformedDevice->DeviceName, PDO::PARAM_STR);
            $stmt->bindParam(':brand', $transformedDevice->Brand, PDO::PARAM_STR);
            $stmt->bindParam(':date', $transformedDevice->announced, PDO::PARAM_STR);
            if (isset($transformedDevice->DeviceIMG)) $stmt->bindParam(':img', $transformedDevice->DeviceIMG, PDO::PARAM_STR);
            if (isset($transformedDevice->Source_URL)) $stmt->bindParam(':source', $transformedDevice->Source_URL, PDO::PARAM_STR);

            try {
                // Executes query & returns whether entries matching the description are already in db
                if ($stmt->execute()) {
                    echo 'sa';
                    return (($stmt->fetchColumn()) > 0);
                } else {
                    echo '<br>' . "query failed" . '<br>';
                    return false;
                }
            } catch (Exception $ex) {
                echo $ex->getMessage();
            }
        }
    }
    