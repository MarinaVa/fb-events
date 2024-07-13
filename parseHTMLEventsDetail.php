<?php

set_time_limit(0);
ini_set("memory_limit","1024M");

require 'config.php';
require 'class/FBParser.php';

date_default_timezone_set($config['timezone']);

$directory = './html-events/';
$fname = 'quasimodoberlin.html';
$html =file_get_contents($directory.$fname);

$eventsFbParser = new FBParser($config);

$list = $eventsFbParser->parseEventListHTML($html);

$events = [];

for ($i=0;$i<count($list);$i++){
    $fname = $directory . $list[$i] . ".html";

    if (file_exists($fname)) {
        $eventHTML = file_get_contents($fname);
        $events[] = $eventsFbParser->parseEventHTML($eventHTML);
    } else {
        $events[] = $list[$i];
    }

}

$my_file = 'events-json.txt';
$handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file);

// use https://codebeautify.org/jsonviewer to read JSON output
fwrite($handle, json_encode($events));

var_dump($events);