<?php
    /**
     * Website: https://github.com/bachors/GSM-Arena-API
     *          http://ibacor.com/labs/gsm-arena-api
     * Original class by Ican Bachors 2016.
     *
     * Contributor: Tanay Parikh
     **/

    include_once($_SERVER['DOCUMENT_ROOT'] . "/BackEnd/Resources/simple_html_dom.php");

    error_reporting(0);

    class GsmAPI
    {

        function __construct()
        {
            // Fix bug slug symbol
            $this->symbol = array("&", "+");
            $this->word = array("_and_", "_plus_");
        }

        ####################### NGE cURL ##########################
        private function mycurl($url)
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_USERAGENT, "Googlebot/2.1 (http://www.googlebot.com/bot.html)");
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_URL, $url);

            if(!$site = curl_exec($ch)){
                return 'offline';
            }
            else{
                return $site;
            }
        }
        ####################### END cURL ##########################

        function search($brand = null)
        {
            $result = array();

            // Run cURL
            $url  = 'http://www.gsmarena.com/results.php3?nYearMin=' . date('Y') . '&sAvailabilities=1,2';

            $ngecurl = $this->mycurl($url);

            // Returns error message if site is unavailable
            if($ngecurl == 'offline'){
                $result["status"] = "error";
                $result["data"] = array();
            }else{

                $html  = str_get_html($ngecurl);

                // Navigate to div containing all devices
                $devices = $html->find('div[class=makers]', 0);

                // Ensures devices can be found
                if($devices->find('li', 0)) {
                    $result["status"] = "success";

                    // Iterates through each device
                    foreach ($devices->find('li') as $device) {
                        // Finds name and link of device
                        $grid = $device->find('a', 0);
                        $title = $grid->find('span', 0);

                        // Stores the link without the extension
                        $slug = str_replace(".php", "", $grid->href);

                        // Creates new data array, filters title and link
                        $result["data"][] = array(
                            "title" => str_replace('<br>', ' ', $title->innertext),
                            "slug" => str_replace($this->symbol, $this->word, $slug)
                        );
                    }
                } else {
                    $result["status"] = "error";
                    $result["data"] = array();
                }
            }

            return $result;
        }

        function detail($slug = "")
        {
            $result = array();

            // Run cURL
            $url  = 'http://www.gsmarena.com/'.str_replace($this->word, $this->symbol, $slug).'.php';
            $ngecurl = $this->mycurl($url);

            // Returns error message if unable to connect
            if($ngecurl == 'offline'){
                $result["status"] = "error";
                $result["data"] = array();
            }else{
                // Gets HTML content and ensures valid device page has been returned
                $html  = str_get_html($ngecurl);

                if($html->find('title', 0)->innertext == '404 Not Found'){
                    $result["status"] = "error";
                    $result["data"] = array();
                } else {
                    $result["status"] = "success";

                    // Gets device name
                    $result["DeviceName"] = $html->find('h1[class=specs-phone-name-title]', 0)->innertext;

                    // Gets device image
                    $imgDiv = $html->find('div[class=specs-photo-main]', 0);
                    $result["DeviceIMG"] = $imgDiv->find('img', 0)->src;

                    // Gets contents of specs-list div
                    $specsList = $html->find('div[id=specs-list]', 0);

                    foreach ($specsList->find('table') as $table) {
                        // Gets spec group
                        $th = $table->find('th', 0);

                        // Runs through each spec in group (table row)
                        foreach ($table->find('tr') as $tr) {
                            // Gets the spec title

                            $ttl = ($tr->find('td', 0)->innertext == "&nbsp;") ? (strtolower($th) . '_c') : $tr->find('td', 0);

                            // Gets specification info
                            $nfo = $tr->find('td', 1);

                            // Sanitizes specification title/value
                            $ttl = self::sanitizeSpecTitle($ttl);
                            $nfo = self::sanitizeSpecValue($nfo);

                            // Adds spec to data array
                            $result["data"][strtolower($th->innertext)][] = array(
                                $ttl => $nfo
                            );
                        }
                    }

                    $search  = array("},{", "[", "]", '","nbsp;":"', "nbsp;", " - ");
                    $replace = array(",", "", "", "<br>", "", "<br>- ");
                    $newjson = str_replace($search, $replace, json_encode($result));
                    $result = json_decode($newjson);
                }
            }

            return $result;
        }

        private static function sanitizeSpecValue($nfo) {
            return strip_tags($nfo);
        }

        private static function sanitizeSpecTitle($ttl) {
            // Sanitizes specification title
            $search  = array(".", ",", "&", "-", " ");
            $replace = array("_", "_", "", "_", "_");
            $ttl = strtolower(str_replace($search, $replace, $ttl));
            $ttl = strip_tags($ttl);

            // Adds underscore to start if first char is numeric
            if (is_numeric(substr($ttl, 0, 1))) {
                $ttl = '_' . $ttl;
            }

            //echo "TTL2: " . strip_tags($ttl) . '<br>';

            return strip_tags($ttl);
        }

        // TODO: Implement brand filtering
        public static function getLatest($brand = null, $limit = 100) {
            $deviceAPI = new GsmAPI();
            $rawDevices = $deviceAPI->search($brand);
            $parsedDevices = array();

            // Indicates devices were found
            if ($rawDevices["status"] == "success") {
                foreach ($rawDevices["data"] as $device) {
                    $deviceDetail = $deviceAPI->detail($device["slug"]);

                    if ($deviceDetail->status == "success") {
                        //echo $deviceDetail->DeviceName . '<br>';
                        //echo '<IMG SRC="'. $deviceDetail->DeviceIMG .  '" ALT="some text"> <br>';
                        $mergedFieldsDevice = null;

                        // Merges together specifications from different groupings (Platform, Memory, Camera etc)
                        foreach ($deviceDetail->data as $grouping) {
                            $mergedFieldsDevice = (object) array_merge((array) $mergedFieldsDevice, (array) $grouping);
                        }

                        // Adds device name and image from main device detail object, to merged device object
                        $mergedFieldsDevice->DeviceName = $deviceDetail->DeviceName;
                        $mergedFieldsDevice->DeviceIMG = $deviceDetail->DeviceIMG;
                        $mergedFieldsDevice->Source_URL = strip_tags($device["slug"]);

                        // Sets device brand
                        $mergedFieldsDevice = self::addBrand($mergedFieldsDevice);

                        //echo var_dump((array) $mergedFieldsDevice);
                        $parsedDevices[] = $mergedFieldsDevice;
                    }

                    // Returns only one device
                    if (count($parsedDevices) == $limit) {
                        break;
                    }
                }
            }

            return $parsedDevices;
        }

        private static function addBrand($mergedFieldsDevice) {
            // Sets brand to be first word of device name
            $mergedFieldsDevice->Brand = explode(' ',trim($mergedFieldsDevice->DeviceName))[0];
            return $mergedFieldsDevice;
        }
    }

?>
