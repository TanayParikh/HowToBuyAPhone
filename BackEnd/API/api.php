<?php
    /**
     * Website: https://github.com/bachors/GSM-Arena-API
     *          http://ibacor.com/labs/gsm-arena-api
     * Original class by Ican Bachors 2016.
     *
     * Contributor: Tanay Parikh
     **/

    error_reporting(0);

    class API
    {

        function __construct()
        {
            // Include library simple html dom
            require("simple_html_dom.php");

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

        function search($q = "")
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
                            ($tr->find('td', 0) == "&nbsp;" ? $ttl = "empty" : $ttl = $tr->find('td', 0));

                            // Sanitizes specification title
                            $search  = array(".", ",", "&", "-", " ");
                            $replace = array("", "", "", "_", "_");
                            $ttl = strtolower(str_replace($search, $replace, $ttl));

                            // Gets specification info
                            $nfo = $tr->find('td', 1);

                            // Adds spec to data array
                            $result["data"][strtolower($th->innertext)][] = array(
                                strip_tags($ttl) => strip_tags($nfo)
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

    }

?>
