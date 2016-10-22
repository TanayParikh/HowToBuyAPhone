<?php
    // Reference: http://www.gabordemooij.com/index.php?p=/tiniest_query_builder
    function build_query($pieces) {
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

    function stringContains($haystack, $needle) {
        return (strpos($haystack, $needle) !== false);
    }

    function isNullOrEmpty($rawText){
        return (!isset($rawText) || empty($rawText));
    }