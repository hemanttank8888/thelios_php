<?php
require_once 'DatabaseHandler.php';

class TheliosDataSpider
{
    private $data_list = [];
   

    public function getHeader($cookies_str){
        $headers_data = [
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
            'Accept-Language' => 'en-IN,en-GB;q=0.9,en-US;q=0.8,en;q=0.7,gu;q=0.6,hi;q=0.5',
            'Upgrade-Insecure-Requests' => '1',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'Cookie' => $cookies_str,
        ];
        return $headers_data;
    }

    public function removeWhiteSpace($str){
        $cleaned_key = str_replace(array("\r", "\n", " "), "", $str);
        $decoded_key = json_decode('"' . $cleaned_key . '"');
        $transliterated_key = iconv('UTF-8', 'ASCII//TRANSLIT', $decoded_key);
        $cleaned_key = str_replace(array(" "), "", $transliterated_key);
        return $cleaned_key;
    }

    public function removeSpaceColourname($color_name){
        $cleaned_key = str_replace(array("\r", "\n", " "), "", $color_name);
        $decoded_key = json_decode('"' . $cleaned_key . '"');
        $transliterated_key = iconv('UTF-8', 'ASCII//TRANSLIT', $decoded_key);
        $remove_space_colour_name = str_replace(array(" "), ",", $transliterated_key);  
        return $remove_space_colour_name;
    }
    
    
    public function getCh($url){
        $session = curl_init($url);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($session, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($session, CURLOPT_COOKIEJAR, "cookies.txt");
        curl_setopt($session, CURLOPT_COOKIEFILE, "cookies.txt");
        curl_setopt($session, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3");
        curl_setopt($session, CURLOPT_COOKIEFILE, ""); // Start with an empty cookie file
        curl_setopt($session, CURLOPT_SSL_VERIFYPEER, false);
        return $session;
    }
    public function get_cookie()
    {
        $loginUrl = "https://my.thelios.com/us/en/j_spring_security_check";

        $session = $this->getCh($loginUrl);
        $response = curl_exec($session);

        $dom = new DOMDocument;
        @$dom->loadHTML($response);
        $xpath = new DOMXPath($dom);

        $form = $xpath->query('//form[@id="loginForm"]')->item(0);
        $loginData = array();

        foreach ($form->getElementsByTagName('input') as $input) {
            $name = $input->getAttribute('name');
            $value = $input->getAttribute('value');
            if ($name && $value) {
                $loginData[$name] = $value;
            }
        }

        // Add your login credentials
        $loginData["j_username"] = "Themonseyoptical@gmail.com";
        $loginData["j_password"] = "Envision@75";

        curl_setopt($session, CURLOPT_URL, $loginUrl);
        curl_setopt($session, CURLOPT_POST, true);
        curl_setopt($session, CURLOPT_POSTFIELDS, http_build_query($loginData));
        curl_setopt($session, CURLOPT_RETURNTRANSFER, true);


        $response = curl_exec($session);


        $cookies = curl_getinfo($session, CURLINFO_COOKIELIST);

        curl_close($session);
        return $cookies;
    }

    public function startRequests()
    {
        $new_cookies = $this->get_cookie();
        $url = 'https://my.thelios.com/us/en/Maison/c/00?sort=relevance&q=%3Acode-asc%3Atype%3ASunglasses%3Apurchasable%3Apurchasable%3AimShip%3Afalse';
        $cookies = array();

        foreach ($new_cookies as $cookie) {
            $cookieParts = explode("\t", $cookie);

            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];

            $cookies[$cookieName] = $cookieValue;
        }

        $cookies_str = implode("; ", array_map(function ($cookie) {
            $cookieParts = explode("\t", $cookie);
            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];
            return "$cookieName=$cookieValue";
        }, $new_cookies));
        $cookie = implode("", $cookies);

        $headers = $this->getHeader($cookies_str);

        $ch = $this->getCh($url);

        foreach ($new_cookies as $cookie) {
            $cookieParts = explode("\t", $cookie);

            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];
            $cookies = $cookieName . '=' . $cookieValue;
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);

        if ($response === false) {
            echo "cURL Error: " . curl_error($ch);
        } else {
            curl_close($ch);
        }
        $this->parse($response, $url, $headers, $cookies, 0);
    }

    public function parse($response, $url, $headers, $cookies, $depth)
    {
        $brand_names = [];
        $doc = new DOMDocument();
        @$doc->loadHTML($response); 
        $xpath = new DOMXPath($doc);
        $links = $xpath->query("//div[@class='details details-product']/a/@href");
        if ($links) {
            foreach ($links as $link) {
                $href = $link->nodeValue;
                if (!empty($href)) {
                    $brand_names[] = "https://my.thelios.com" . $href;
                }
            }
        }
        $count_data = 0;
        foreach ($brand_names as $i) {
            if ($count_data == 0) {
                $data_dict = [];
                $ch = $this->getCh($i);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
                $response = curl_exec($ch);
                $this->checkAndRequest($response, $i, $headers, $cookies, $data_dict, $depth);
            } else{
                $new_cookies = $this->get_cookie();
                $cookies = array();
                foreach ($new_cookies as $cookie) {
                    $cookieParts = explode("\t", $cookie);

                    $cookieName = $cookieParts[5];
                    $cookieValue = $cookieParts[6];

                    $cookies[$cookieName] = $cookieValue;
                }

                $cookies_str = implode("; ", array_map(function ($cookie) {
                    $cookieParts = explode("\t", $cookie);
                    $cookieName = $cookieParts[5];
                    $cookieValue = $cookieParts[6];
                    return "$cookieName=$cookieValue";
                }, $new_cookies));
                $cookie = implode("", $cookies);

                $headers = $this->getHeader($cookies_str);

                $ch = $this->getCh($i);
                foreach ($new_cookies as $cookie) {
                    $cookieParts = explode("\t", $cookie);

                    $cookieName = $cookieParts[5];
                    $cookieValue = $cookieParts[6];
                    $cookies = $cookieName . '=' . $cookieValue;
                    curl_setopt($ch, CURLOPT_COOKIE, $cookies);
                }

                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($ch);
        
                $this->checkAndRequest($response, $i, $headers, $cookies, $data_dict, $depth);
            }
            $count_data++;
        }
    }

    public function checkAndRequest($response, $url, $headers, $cookies, $data_dict, $depth)
    {
        $this->details($response, $headers, $cookies, $data_dict);
    }

    public function details($response, $headers, $cookies, $data_dict)
    {
        $doc = new DOMDocument();
        @$doc->loadHTML($response);

        $xpath = new DOMXPath($doc);
        $image_urls = [];
        $color_variant = [];

        $product_name = trim($xpath->query('//div[contains(@class,"product-details name-product")]/text()[1]')->item(0)->nodeValue);
        $data_dict['category_name'] = $xpath->query('//div[@class="product-main-info"]/div[1]/div[1]//a/text()')->item(0)->nodeValue;
        $data_dict['product_name'] = trim($xpath->query('//div[contains(@class,"product-details name-product")]/text()[1]')->item(0)->nodeValue);
        foreach ($xpath->query('//ul[@class="section-details-list"]/li') as $li) {
            $text = $li->nodeValue; // Get the text content of the <li> element
            $parts = explode(":", $text);
            if (count($parts) === 2) {
                $variable = $this->removeWhiteSpace($parts[0]);
                $value = $this->removeWhiteSpace($parts[1]);
                $data_dict[$variable] = $value;
            }
        }
        $product_dict = [];
        $color_code = $xpath->query('//div[contains(@class,"product-details name-product")]/span/text()')->item(0)->nodeValue;
        $product_dict['color_code'] = $xpath->query('//div[contains(@class,"product-details name-product")]/span/text()')->item(0)->nodeValue;
        try {
            $product_dict['product_price'] = $this->removeWhiteSpace($xpath->query('//div[@class="product-main-info"]//div[@class="price-box"]/text()[1]')->item(0)->nodeValue);
        } catch (Exception $e) {
            $product_dict['product_price'] = null;
        }
        $product_dict['color_name'] = $this->removeSpaceColourname($xpath->query('//div[contains(@class,"landscape-pdp-space")]/div/text()')->item(0)->nodeValue);
        $image_elements = $xpath->query('//div[@class="carousel image-gallery__image js-gallery-image"]/div//img[@class="lazyOwl"]');
        $no = 0;
        foreach ($image_elements as $img_element) {
            $data_zoom_image = $img_element->getAttribute('data-zoom-image');
            $data_src = $img_element->getAttribute('data-src');

            if (!empty($data_zoom_image)) {
                $image_url = "https://my.thelios.com" . $data_zoom_image;

            } elseif (!empty($data_src)) {
                $image_url = "https://my.thelios.com" . $data_src;
            } else {
                continue;
            }
            $image_urls[] = $image_url;
            $no++;
            $this->imageResponse($image_url, $cookies, $headers, $product_name, $color_code, $no);
        }
        $image_urls[] = 
        $product_dict['images'] = $image_urls;
        $color_variant[] = $product_dict;
        $data_dict['color_variants'] = [$color_variant];
        $this->data_list[] = $data_dict;
        
        foreach ($xpath->query("//div[@class='variant-selector']/ul/li[not(contains(@class, 'active'))]//a") as $li) {
            
            $anchor = $li->getAttribute('href');
            if ($anchor) {
                $url = "https://my.thelios.com" . $anchor;
                $this->colorVariant($url, $headers, $cookies, $data_dict);
            }
        }
    }

    public function colorVariant($url, $headers, $cookies, $data_dict)
    {   $new_cookies = $this->get_cookie();
        $cookies = array();
        foreach ($new_cookies as $cookie) {
            $cookieParts = explode("\t", $cookie);

            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];

            $cookies[$cookieName] = $cookieValue;
        }

        $cookies_str = implode("; ", array_map(function ($cookie) {
            $cookieParts = explode("\t", $cookie);
            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];
            return "$cookieName=$cookieValue";
        }, $new_cookies));
        $cookie = implode("", $cookies);

        $headers = $this->getHeader($cookies_str);

        $ch = $this->getCh($url);
        foreach ($new_cookies as $cookie) {
            $cookieParts = explode("\t", $cookie);

            $cookieName = $cookieParts[5];
            $cookieValue = $cookieParts[6];
            $cookies = $cookieName . '=' . $cookieValue;
            curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        }

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $doc = new DOMDocument();
        @$doc->loadHTML($response);

        $xpath = new DOMXPath($doc);
        $image_urls = [];
        $color_variant = [];
        $product_dict = [];
        $product_name = trim($xpath->query('//div[contains(@class,"product-details name-product")]/text()[1]')->item(0)->nodeValue);

        $product_dict['color_code'] = $xpath->query('//div[contains(@class,"product-details name-product")]/span/text()')->item(0)->nodeValue;
        $color_code = $xpath->query('//div[contains(@class,"product-details name-product")]/span/text()')->item(0)->nodeValue;

        try {
            $product_dict['product_price'] = $xpath->query('//div[@class="product-main-info"]//div[@class="price-box"]/text()[1]')->item(0)->nodeValue;
            $cleaned_product = str_replace(array("\r", "\n", " "), "", $product_dict['product_price']);
            $decoded_product = json_decode('"' . $cleaned_product . '"');
            $product_dict['product_price'] = iconv('UTF-8', 'ASCII//TRANSLIT', $decoded_product);
        } catch (Exception $e) {
            $product_dict['product_price'] = null;
        }

        $product_dict['color_name'] =  $this->removeSpaceColourname($xpath->query('//div[contains(@class,"landscape-pdp-space")]/div/text()')->item(0)->nodeValue);
        $image_elements = $xpath->query('//div[@class="carousel image-gallery__image js-gallery-image"]/div//img[@class="lazyOwl"]');
        $no = 0;
        foreach ($image_elements as $img_element) {
            $data_zoom_image = $img_element->getAttribute('data-zoom-image');
            $data_src = $img_element->getAttribute('data-src');

            if (!empty($data_zoom_image)) {
                $image_url = "https://my.thelios.com" . $data_zoom_image;
            } elseif (!empty($data_src)) {
                $image_url = "https://my.thelios.com" . $data_src;
            } else {
                continue;
            }
            $image_urls[] = $image_url;
            $no++;
            $this->imageResponse($image_url, $cookies, $headers, $product_name, $color_code, $no);
        }

        $product_dict['images'] = $image_urls;
        $color_variant[] = $product_dict;

        foreach ($this->data_list as &$i) {
            if ($i['product_name'] == $product_name) {
                if (isset($i['color_variants']) && is_array($i['color_variants'])) {
                    $i['color_variants'][] = $color_variant;
                } else {
                    $i['color_variants'] = $color_variant;
                }
        
            }
        }
        $this->data_list = array_filter($this->data_list, function($item) {
            return isset($item["category_name"]) && $item["category_name"] !== null;
        });
        $databaseHandler = new DatabaseHandler("localhost", "root", "", "ezcontact_x_datacenter");
        $databaseHandler->insertData($this->data_list);
        $databaseHandler->closeConnection();
    }

    public function imageResponse($image_url, $cookies, $headers, $product_name, $color_code, $no)
    {
        $image_data = file_get_contents($image_url);
        $image_filename = "image_output/{$product_name}/{$color_code}_{$no}.jpg";
        if (!file_exists(dirname($image_filename))) {
            mkdir(dirname($image_filename), 0755, true);
        }
        file_put_contents($image_filename, $image_data);
    }

    public function saveDataToFile()
    {   
        $this->data_list = array_filter($this->data_list, function($item) {
            return isset($item["category_name"]) && $item["category_name"] !== null;
        });
        $decoded_dict = array_values($this->data_list);
        $jsonString = json_encode($decoded_dict, JSON_PRETTY_PRINT);
        file_put_contents("output_data2.json", $jsonString);
    }
}

