<?php

set_time_limit(0);
ini_set("memory_limit","1024M");

require 'config.php';
require 'class/FBParser.php';

date_default_timezone_set($config['timezone']);

try {
    $searchName = 'Zukunft.Ostkreuz';
    $eventsFbParser = new FBParser($config);
    $eventsList = $eventsFbParser->getEventsList($searchName);

    $upcomingEvents = [];
    foreach($eventsList['upcoming'] as $event) {
        $upcomingEvents[] = $eventsFbParser->getUpcomingEventsListData($event);
    }

    $recurringEvents = [];
    foreach($eventsList['recurring'] as $event) {
        $recurringEvents = array_merge($recurringEvents, $eventsFbParser->getRecurringEventsListData($event));
    }

    $allEvents = array_merge($upcomingEvents, $recurringEvents);

    $my_file = 'events-json.txt';
    $handle = fopen($my_file, 'w') or die('Cannot open file:  '.$my_file);

    // use https://codebeautify.org/jsonviewer to read JSON output
    fwrite($handle, json_encode($allEvents));

} catch(Exception $e) {
    echo $e->getMessage();
}