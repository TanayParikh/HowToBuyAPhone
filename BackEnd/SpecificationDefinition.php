<?php
    include_once("Configuration.php");

    class SpecificationDefinition
    {
        public static $specificationDefinitions;

        public $id;
        public $name;
        public $level;
        public $units;
        public $api_key;

        // Gets specification definition assoc array
        public static function init() {
            // Only configures definitions if not already set
            if (isset(self::$specificationDefinitions)) return null;

            $db = Configuration::getConnection();
            $stmt = $db->prepare("SELECT id, name, level, units, api_key FROM htbap.specification_definition");

            $stmt->execute();

            // Loads result into SpecificationDefinition class objects
            $definitions = $stmt->fetchAll(PDO::FETCH_CLASS, "SpecificationDefinition");

            // Creates new assoc array with key of Specification name and value as the SpecificationDefinition object
            self::$specificationDefinitions = array();

            foreach ($definitions as $definition) {
                self::$specificationDefinitions[$definition->name] = $definition;
            }

            return self::$specificationDefinitions;
        }
    }