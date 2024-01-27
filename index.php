<?php
require_once 'TheliosSpider.php';

// Create an instance of the spider and start scraping
$spider = new TheliosDataSpider();
$spider->startRequests();
$spider->saveDataToFile();
?>