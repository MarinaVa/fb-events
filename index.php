<?php

set_time_limit(0);
ini_set("memory_limit","1024M");

require 'config.php';
require 'class/FBParser.php';

if(empty($_POST['search_name'])) { ?>
    <form method="post">
        <input type="text" name="search_name" />
        <input type="submit" value="Search" />
    </form>
    
<?php 

    exit();
}

date_default_timezone_set($config['timezone']);

try {
    $searchName = trim($_POST['search_name']);
    $eventsFbParser = new FBParser($config);
    $eventsList = $eventsFbParser->getEventsList($searchName);
    echo '<pre>';
    var_dump($eventsList);
    echo '</pre>';
} catch(Exception $e) {
    echo $e->getMessage();
}