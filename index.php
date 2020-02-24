<?php
    // HTTP Bad Request Response Code
    $BAD_REQUEST = 400;

    // index of $params array where the UMBC SGApps endpoint name will be
    $ENDPOINT = 0;

    // endpoint name constants
    $EVENTS_ENDPOINT = "events";
    $LAUNDRY_ENDPOINT = "laundry";
    $PLACES_ENDPOINT = "places";
    $MENUS_ENDPOINT = "menus";
    $NEWS_ENDPOINT = "news";
    $POSTS_ENDPOINT = "posts";
    $TRANSIT_ENDPOINT = "transit";
    $OPEN_NOW_ENDPOINT = "open-now";
    $CLASS_SEARCH_ENDPOINT = "class-search";

    $COURSE_NAME = 1;

    // transit endpoint constants
    $TRANSLOC_ENDPOINT = 1;
    $AGENCY_ID = 112;
    $GET_ROUTES = "routes";
    $GET_STOPS = "stops";
    $GET_VEHICLES = "vehicles";
    $GET_SEGMENTS = "segments";
    $GET_ARRIVAL_ESTIMATES = "arrival-estimates";
    $RAPIDAPI_KEY = "";
    $TRANSLOC_ENDPOINTS = array("arrival-estimates", "routes", "segments", "stops", "vehicles");
    $TRANSLOC_SPECIFIER = 2;
    $TRANSLOC_SPECIFIERS = array("arrival-estimates" => array("routes", "stops"), "segments" => array("routes"), "vehicles" => array("routes"), "stops" => array(), "routes" => array());
    $SPECIFIER_IDS = 3;

    // events and posts endpoint constants
    $EVENTS_API_URL = "https://my.umbc.edu/api/v0/events.xml";
    $GROUP_EVENTS_API_URL = "https://my.umbc.edu/api/v0/events.xml?group_token=";
    $POSTS_API_URL = "https://my.umbc.edu/api/v0/posts.xml";
    $GROUP_POSTS_API_URL = "https://my.umbc.edu/api/v0/posts.xml?group_token=";
    $GROUP_TOKEN = 2;

    // laundry endpoint constants
    $LAUNDRYVIEW_LOCATION_BASE_URL = "https://www.laundryview.com/api/c_room?loc=";
    $LAUNDRYVIEW_LOCATION_ID = 5803;
    $LAUNDRYVIEW_ROOM_BASE_URL = "https://www.laundryview.com/api/currentRoomData?school_desc_key=5803&location=";

    // news endpoint constants
    $NEWS_FEED_URL = "https://retriever.umbc.edu/feed/";
    $PAGED_NEWS_FEED_URL = "https://retriever.umbc.edu/feed/?paged=";
    $PAGE_NUMBER = 2;

    // menus endpoint constants
    $LOCATION = 1;
    $DATE = 2;
    $DINING_LOCATIONS = array("dhall", "skylight", "admin");
    $DINING_LOCATION_URLS = array("dhall" => "https://api.dineoncampus.com/v1/location/menu?site_id=5751fd3690975b60e04893e2&platform=0&location_id=5873e39e3191a200fa4e8399&date=", 
    "skylight" => "https://api.dineoncampus.com/v1/location/menu?site_id=5751fd3690975b60e04893e2&platform=0&location_id=5b97c25e1178e90d90a74099&date=", "admin" => "https://api.dineoncampus.com/v1/location/menu?site_id=5751fd3690975b60e04893e2&platform=0&location_id=586bcfa12cc8da3d267f4682&date=");

    $DINING_SCHEDULE_API_URL = "https://api.dineoncampus.com/v1/locations/open?site_id=5751fd3690975b60e04893e2&timestamp=";

    // get request URL
    $request_url = $_SERVER['REQUEST_URI'];

    // split request_url into params by "/" character
    $params = explode("/", $request_url);

    // remove all blank array elements
    $params = array_filter($params);

    // remove the first array element which will always be "api"
    array_shift($params);

    // set timezone
    date_default_timezone_set("America/New_York");

    function cache_first_fetch($file_name, $url, $filter_prop) {
        // if is cached, read from cache
        if (file_exists($file_name)) {
            return file_get_contents($file_name);
        }
        // if not cached, fetch from URL
        else {
            // get JSON data from API URL
            $raw_data = file_get_contents($url);
            $data = json_decode($raw_data, true);

            // if was fetch error on API's part, throw an error
            if (array_key_exists("status", $data) && $data["status"] == "error")
                return '{"error": "API Error."}';

            // if filter_prop is set, filter the data
            if (strlen($filter_prop) != 0)
                $data = $data[$filter_prop];
	
            // stringify the JSON
            $json_string = json_encode($data);

            // write the file contents to cache
            $file = fopen($file_name, "w");
            fwrite($file, $json_string);
            fclose($file);

            return $json_string;
        }
    }

    ini_set('max_execution_time', 300);
    function restore_revolving_cache($location) {
        global $DINING_LOCATION_URLS;

        function is_dhall($el) {
            if (strpos($el, "dhall") !== false) return true;
            else return false;
        }

        // get all dhall menus
        $files = scandir('.');
        $files = array_filter($files, "is_dhall");

        // if more than 31 files, no need to do this
        if (count($files) >= 31) return;

        // get the menu furthest in time away
        $furthest_menu = end($files);

        // get file name without extension (so just date)
        $basename = basename($furthest_menu, ".json");
        $fileDate = substr($basename, strrpos($basename, "_") + 1);

        $next_menu_date = date('Y-m-d', strtotime($fileDate . ' + 1 days'));

        $url = $DINING_LOCATION_URLS[$location] . $next_menu_date;

        // create filename for location and date to cache file
        $file_name = $location . "_" . $next_menu_date . ".json";

        // only send data from menu prop from DineOnCampus API response
        $filter_prop = "menu";

        cache_first_fetch($file_name, $url, $filter_prop);

        // restore_revolving_cache($location);
    }

    // walk directory for cache files which their days have passed
    function cache_bust() {
        foreach (scandir('.') as $file) {
            // if is a JSON file
            if (strpos($file, ".json") && strpos($file, "_")) {
                // get today's date
                $today = date("Y-m-d");

                // get file name without extension (so just date)
                $basename = basename($file, ".json");
                $fileDate = substr($basename, strrpos($basename, "_") + 1);

                // convert date from file into date object
                $fileDate = date("Y-m-d", strtotime($fileDate));

                // if file date has passed, delete from cache
                if ($today > $fileDate) {
                    unlink($file);
                }
            }
        }
    }

    // if the URL is more than just /api/
    if (count($params)) {
        // handle /class-search/
        if ($params[$ENDPOINT] == $CLASS_SEARCH_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                $SEARCH_URL = "https://catalog.umbc.edu/search_advanced.php?search_database=Search&search_db=Search&cpage=1&ecpage=1&ppage=1&spage=1&tpage=1&location=33&filter[keyword]=";

                $page = file_get_contents($SEARCH_URL . $params[$COURSE_NAME]);

                if (strpos($page, "coid=\"")) {
                    http_response_code($BAD_REQUEST);
                }
                else if (strpos($page, "preview_course_nopop")) {
                    $matchIndicatorIndex = strpos($page, "preview_course_nopop");

                    $match_url = substr($page, $matchIndicatorIndex);
                    $match_url = substr($match_url, 0, strpos($match_url, '"'));

                    $course_page = file_get_contents("https://catalog.umbc.edu/" . $match_url);

                    $dom = new DomDocument();
                    $dom->loadHTML($course_page);

                    $xpath = new DOMXpath($dom);
                    $xpathquery = "//h1[@id='course_preview_title']";

                    $elements = $xpath->query($xpathquery);

                    if (!is_null($elements)) {
                        foreach ($elements as $element) {
                            $nodes = $element->childNodes;

                            foreach ($nodes as $node) {
                                echo $node->nodeValue;
                            }
                        }
                    }
                }
            }
        }
        // handle /places/
    else if ($params[$ENDPOINT] == $PLACES_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // read the JSON db file of all locations and locations times
                $places = json_decode(file_get_contents("places-db.json"), true);

                // get current day and hour
                $current_day = date("l");
                $current_hour = date("H") + (date("i") / 60);

                // handle /places/
                if (count($params) == 1) {
                    foreach ($places as $key => $value) {
                        // get open and close times
                        $open = $value[$current_day]["open"];
                        $close = $value[$current_day]["close"];
                    
                        // if building is open
                        if ($open <= $current_hour && $close >= $current_hour) {
                            $places[$key]["open_now"] = true;
                        }
                        // if building is closed
                        else {
                            $places[$key]["open_now"] = false;
                        }
                    }

                    echo json_encode($places);
                }
                // handle /places/open 
                elseif (count($params) == 2 && $params[1] == "open") {
                    $open_places = array();

                    foreach ($places as $key => $value) {
                        $open = $value[$current_day]["open"];
                        $close = $value[$current_day]["close"];
                    
                        // if building is open
                        if ($open <= $current_hour && $close >= $current_hour) {
                            $value["open_now"] = true;

                            array_push($open_places, $value);
                        }
                    }

                    echo json_encode($open_places);
                }
                // handle /places/closed 
                elseif (count($params) == 2 && $params[1] == "closed") {
                    $closed_places = array();
                    
                    foreach ($places as $key => $value) {
                        $open = $value[$current_day]["open"];
                        $close = $value[$current_day]["close"];
                    
                        // if place is closed
                        if ($open >= $current_hour && $close <= $current_hour) {
                            $value["open_now"] = false;

                            array_push($closed_places, $value);
                        }
                    }

                    echo json_encode($closed_places);
                }
                // handle /places/:location_token
                elseif (count($params) == 2) {
                    // if is valid locations token
                    if (isset($locations[$params[1]])) {
                        echo json_encode($locations[$params[1]]);
                    }
                    // if is not valid building token
                    else {
                        http_response_code($BAD_REQUEST);
                    }
                }
                // if has wrong number of params
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /events/
        elseif ($params[$ENDPOINT] == $EVENTS_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // handle /events/
                if (count($params) == 1) {
                    $xml = file_get_contents($EVENTS_API_URL);

                    echo $xml;
                }
                // handle /events/group/:group_token
                elseif (count($params) == 3 && $params[1] == "group") {
                    $group_token = $params[$GROUP_TOKEN];

                    $xml = file_get_contents($GROUP_EVENTS_API_URL . $group_token);

                    echo $xml;
                }
                // if has too many params
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /posts/
        else if ($params[$ENDPOINT] == $POSTS_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // handle /posts/
                if (count($params) == 1) {
                    $xml = file_get_contents($POSTS_API_URL);

                    echo $xml;
                }
                // handle /posts/group/:group_token
                elseif (count($params) == 3 && $params[1] == "group") {
                    $group_token = $params[$GROUP_TOKEN];

                    $xml = file_get_contents($GROUP_POSTS_API_URL . $group_token);

                    echo $xml;
                }
                // if doesn't have proper number of params
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /news/
        else if ($params[$ENDPOINT] == $NEWS_ENDPOINT) {
            // handle /posts/
            if (count($params) == 1) {
                // get news from the Retriever
                $xml = file_get_contents($NEWS_FEED_URL);

                echo $xml;
            }
            // handle /posts/page/:id
            elseif (count($params) == 3 && $params[1] == "page") {
                $page_num = $params[$PAGE_NUMBER];

                $xml = file_get_contents($PAGED_NEWS_FEED_URL . $page_num);

                echo $xml;
            }
            // if doesn't have proper number of params
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /transit/
        elseif ($params[$ENDPOINT] == $TRANSIT_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // handle /transit/:transloc_endpoint/
                if (count($params) == 2) {
                    // get transloc endpoint
                    $endpoint = $params[$TRANSLOC_ENDPOINT];

                    // if is valid transloc endpoint
                    if (in_array($endpoint, $TRANSLOC_ENDPOINTS)) {
                        $date = date('Y-m-d', time());
                        $file_name = $endpoint . "_" . $date . ".json";

                        // if file already exists, served cached version (only for routes and stops)
                        if (file_exists($filename) && ($endpoint == "routes" || $endpoint == "stops")) {
                            echo file_get_contents($file_name);
                        }
                        else {
                            // create cURL object
                            $curl = curl_init();

                            // options to get data from TransLoc
                            curl_setopt_array($curl, array(
                                CURLOPT_URL => "https://transloc-api-1-2.p.rapidapi.com/" . $endpoint . ".json?agencies=" . $AGENCY_ID,
                                CURLOPT_RETURNTRANSFER => true,
                                CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_ENCODING => "",
                                CURLOPT_MAXREDIRS => 10,
                                CURLOPT_TIMEOUT => 30,
                                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                                CURLOPT_CUSTOMREQUEST => "GET",
                                CURLOPT_HTTPHEADER => array(
                                    "x-rapidapi-host: transloc-api-1-2.p.rapidapi.com",
                                    "x-rapidapi-key: " . $RAPIDAPI_KEY
                                ),
                            ));

                            // execute cURL request and get errors
                            $response = curl_exec($curl);
                            $err = curl_error($curl);

                            // close cURL connection
                            curl_close($curl);

                            // if cURL error, send bad request response
                            if ($err) {
                                http_response_code($BAD_REQUEST);
                            } 
                            else {
                                // get data section from TransLoc reponse
                                $data = json_decode($response, true)["data"];

                                // if has agency ID, get that section and send it
                                if (array_key_exists($AGENCY_ID, $data)) {
                                    $data = $data[$AGENCY_ID];
                                }

                                // if is /transit/routes/ or /transit/stops/, cache data
                                if ($endpoint == "routes" || $endpoint == "stops") {
                                    // write the file contents to cache
                                    file_put_contents($file_name, json_encode($data));

                                    // return the data as response
                                    echo file_get_contents($file_name);
                                }
                                // if "real-time" data, do not cache
                                else {
                                    // return data
                                    echo json_encode($data);
                                }
                            }
                        }
                    }
                    // if not calling valid transloc endpoint
                    else {
                        http_response_code($BAD_REQUEST);
                    }
                }
                // handle /transit/:transloc_endpoint/:specifer/:ids
                elseif (count($params) == 4) {
                    $endpoint = $params[$TRANSLOC_ENDPOINT];
                    $specifier = $params[$TRANSLOC_SPECIFIER];
                    $specifier_ids = $params[$SPECIFIER_IDS];

                    if (in_array($endpoint, $TRANSLOC_ENDPOINTS) && in_array($specifier, $TRANSLOC_SPECIFIERS[$endpoint])) {
                        // create cURL object
                        $curl = curl_init();

                        $api_url = "https://transloc-api-1-2.p.rapidapi.com/" . $endpoint . ".json?agencies=" . $AGENCY_ID . "&" . $specifier . "=" . $specifier_ids;

                        // options to get data from TransLoc
                        curl_setopt_array($curl, array(
                            CURLOPT_URL => $api_url,
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_ENCODING => "",
                            CURLOPT_MAXREDIRS => 10,
                            CURLOPT_TIMEOUT => 30,
                            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                            CURLOPT_CUSTOMREQUEST => "GET",
                            CURLOPT_HTTPHEADER => array(
                                "x-rapidapi-host: transloc-api-1-2.p.rapidapi.com",
                                "x-rapidapi-key: " . $RAPIDAPI_KEY
                            ),
                        ));

                        // execute cURL request and get errors
                        $response = curl_exec($curl);
                        $err = curl_error($curl);

                        // close cURL connection
                        curl_close($curl);

                        // if cURL error, send bad request response
                        if ($err) {
                            http_response_code($BAD_REQUEST);
                        } 
                        else {
                            // get data section from TransLoc reponse
                            $data = json_decode($response, true)["data"];

                            // if has agency ID, get that section and send it
                            if (array_key_exists($AGENCY_ID, $data)) {
                                $data = $data[$AGENCY_ID];
                            }

                            echo json_encode($data);
                        }
                    }
                    // invalid param specifier
                    else {
                        http_response_code($BAD_REQUEST);
                    }
                }
                // if has incorrect number of params
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /laundry/
        else if ($params[$ENDPOINT] == $LAUNDRY_ENDPOINT) {
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // handles /laundry/rooms
                if (count($params) == 2 && $params[1] == "rooms") {
                    $date = date('Y-m-d', time());

                    // create filename for location and date to cache file
                    $file_name = "laundry_" . $date . ".json";

                    // if file is cached, send cached data
                    if (file_exists($file_name)) {
                        echo file_get_contents($file_name);
                    }
                    // if the file is not cached, retrieve from DineOnCampus
                    else {
                        // generate LaundryView API URL
                        $url = $LAUNDRYVIEW_LOCATION_BASE_URL . $LAUNDRYVIEW_LOCATION_ID;

                        // put the contents of the file into a variable
                        $data = file_get_contents($url); 

                        // convert data to associative array
                        $laundry_data = json_decode($data, true)["room_data"];

                        // write the file contents to cache
                        file_put_contents($file_name, $data);

                        // return the data as response
                        echo file_get_contents($file_name);
                    }
                }
                // handles /laundry/rooms/:id
                elseif (count($params) == 3 && $params[1] == "rooms") {
                    // get room data
                    $url = $LAUNDRYVIEW_ROOM_BASE_URL . $params[2];

                    echo file_get_contents($url);
                }
                // if not valid /laundry/{something} endpoint
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        // handle /menus/
        elseif ($params[$ENDPOINT] == $MENUS_ENDPOINT) {
            // give the menu if is GET request
            if ($_SERVER["REQUEST_METHOD"] === "GET") {
                // if request is /menus/:location/:date
                if (count($params) == 3) {
                    // get location and date from 
                    $location = $params[$LOCATION];
                    $date = $params[$DATE];

                    // left pad zeros
                    $date_components = explode("-", $date);
                    if (count($date_components) == 3) {
                        if (strlen($date_components[1]) == 1) 
                            $date_components[1] = "0" . $date_components[1];

                        if (strlen($date_components[2]) == 1) 
                            $date_components[2] = "0" . $date_components[2];

                        $date = implode("-", $date_components);
                    }

                    // if location token is a valid location
                    if (in_array($location, $DINING_LOCATIONS)) {
                        // generate DineOnCampus API URL
                        $url = $DINING_LOCATION_URLS[$location] . $date;

                        // create filename for location and date to cache file
                        $file_name = $location . "_" . $date . ".json";

                        // only send data from menu prop from DineOnCampus API response
                        $filter_prop = "menu";

                        // allow php to run even after request ends
                        ignore_user_abort(true);
                        set_time_limit(0);
                       
                        // start recording an output buffer
                        ob_end_clean();
                        ob_start();

                        // do a cache-first fetch and send to user
                        echo cache_first_fetch($file_name, $url, $filter_prop);
                        
                        // close the connection with headers
                        header('Connection: close');
                        header("Content-Encoding: none");
                        header('Content-Length: ' . ob_get_length());

                        // flush the output buffer
                        ob_end_flush();
                        ob_flush();
                        flush();

                        cache_bust();

                        restore_revolving_cache($location);
                    }
                    // if location token was invalid, send bad request response
                    else {
                        http_response_code($BAD_REQUEST);
                    }
                }
                // if request is /menus/{random stuff}
                else {
                    http_response_code($BAD_REQUEST);
                }
            }
            // if is POST request
            else {
                http_response_code($BAD_REQUEST);
            }
        }
        else if ($params[$ENDPOINT] == $OPEN_NOW_ENDPOINT) {
            $utc_date = gmdate("Y-m-d\TH:i:s") . "Z";

            $url = $DINING_SCHEDULE_API_URL . $utc_date;

            $raw_data = file_get_contents($url);
            $data = json_decode($raw_data, true)["location_schedules"];
            
            echo json_encode($data);
        }
        // if specified endpoint does not exist
        else {
            http_response_code($BAD_REQUEST);
        }
    }
    // if no endpoint is specified
    else {
        http_response_code($BAD_REQUEST);
    }
?>