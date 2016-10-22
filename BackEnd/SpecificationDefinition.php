<?php
    include_once("Configuration.php");

    class SpecificationDefinition
    {
        public static $specificationReference;

        public $id;
        public $name;
        public $level;
        public $units;
        public $api_key;
        public $object_key;

        // Gets specification definition assoc array
        public static function init() {
            // Only configures definitions if not already set
            if (isset(self::$specificationReference)) return null;

            $db = Configuration::getConnection();
            $stmt = $db->prepare("SELECT id, name, level, units, api_key, object_key FROM htbap.specification_definition");

            $stmt->execute();

            // Loads result into SpecificationDefinition class objects
            $definitions = $stmt->fetchAll(PDO::FETCH_CLASS, "SpecificationDefinition");

            // Creates new assoc array with key of Specification name and value as the SpecificationDefinition object
            self::$specificationReference = array();

            foreach ($definitions as $definition) {
                if (isset($definition->object_key)) {
                    //self::$specificationReference[$definition->name] = $definition;
                    self::$specificationReference[$definition->object_key] = $definition;
                }
            }

            return self::$specificationReference;
        }

        public static function getIDFromObjectKey($objectKey) {
            return self::$specificationReference[$objectKey]->id;
        }
    }