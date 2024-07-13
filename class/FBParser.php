<?php

require("./lib/PHPQuery/phpQuery.php");

class FBParser
{
    private $config;
    private $referrerURL;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function getEventsList($searchName)
    {
        $eventsList = [];

        $url = str_replace('{search}', $searchName, $this->config['fb_events_page_url']);
        $eventsPageData = $this->makeRequest($url);

        $pq = phpQuery::newDocumentHTML($eventsPageData);

        preg_match('/\{"pageID"\:"(\d+)"/', $pq->find('script')->html(), $pageIdInfo);

        $pageId = isset($pageIdInfo[1])? $pageIdInfo[1] : false;

        if(!$pageId) {
            throw new Exception('Error while getting events page data');
        }

        $this->referrerURL = 'https://www.facebook.com/'.$pageId.'/events';

        $upcomingEventsList = $this->getUpcomingEventsList($pageId);
        $recurringEventsList = $this->getRecurringEventsList($pageId);

        // return array_merge($recurringEventsList, $upcomingEventsList);
        return ['upcoming' => $upcomingEventsList, 'recurring' => $recurringEventsList];
    }
    
    private function getRecurringEventsList($pageId)
    {
        $url = $this->config['fb_events_script_url'];

        $eventsList = [];

        $data = [
            'variables' => '{"pageID":"'.$pageId.'","count":10}',
            'doc_id' => $this->config['fb_recurring_events_doc_id']
        ];
        
        do {
            $response = json_decode($this->makeRequest($url, $data));
            $eventsData = isset($response->data->page->upcomingRecurringEvents)? $response->data->page->upcomingRecurringEvents : false;

            if(!$eventsData) {
                throw new Exception('Error while getting recurring events data');
            }

            $eventsList = array_merge($eventsList, $eventsData->edges);

            if($eventsData->page_info->has_next_page) {

                $data = [
                    'variables' => '{"pageID":"'.$pageId.'","count":10,"cursor":"'.$eventsData->page_info->end_cursor.'"}',
                    'doc_id' => $this->config['fb_recurring_events_doc_id']
                ];
            }

        } while(!empty($eventsData->page_info->has_next_page));
        

        return $eventsList;
    }
    
    private function getUpcomingEventsList($pageId)
    {
        $url = $this->config['fb_events_script_url'];

        $eventsList = [];

        $data = [
            'variables' => '{"pageID":"'.$pageId.'"}',
            'doc_id' => $this->config['fb_upcoming_events_doc_id']
        ];

        do {
            $response = json_decode($this->makeRequest($url, $data));
            $eventsData = isset($response->data->page->upcoming_events)? $response->data->page->upcoming_events : false;

            if(!$eventsData) {
                throw new Exception('Error while getting upcoming events data');
            }

            $eventsList = array_merge($eventsList, $eventsData->edges);

            if($eventsData->page_info->has_next_page) {

                $data = [
                    'variables' => '{"pageID":"'.$pageId.'","count":9,"cursor":"'.$eventsData->page_info->end_cursor.'"}',
                    'doc_id' => $this->config['fb_upcoming_events_doc_id_next']
                ];
            }

        } while(!empty($eventsData->page_info->has_next_page));

        return $eventsList;
    }
    
    public function getRecurringEventsListData($event) {
           $eventsList = [];
        
    	// foreach($recurringEventsList as $event) {
            $eventDetailInfo = $this->getDetailRecurringEventInfo($event->node->eventID);
            
            $eventDetailInfo['title'] = $event->node->name;
            $eventDetailInfo['ticket_uri'] = $event->node->event_buy_ticket_url;
            $eventDetailInfo['cover'] = $event->node->coverPhoto->photo->image->uri;
            $eventDetailInfo['description'] = $event->node->description->text;
            
            foreach($eventDetailInfo['event_time_data'] as $eventTime) {
                $eventData = $eventDetailInfo;
                unset($eventData['event_time_data']);
                
                $eventsList[] = array_merge($eventData, $eventTime);
            }
        // }
        
        return $eventsList;
    }

    public function getUpcomingEventsListData($event) {
//    	foreach($upcomingEventsList as $event) {
        //$eventDetailInfo = $this->getDetailEventInfo($event->node->eventID);
        $eventDetailInfo = $this->getDetailEventInfo($event->node->eventID);
        $eventDetailInfo['title'] = $event->node->name;
        $eventDetailInfo['ticket_uri'] = $event->node->event_buy_ticket_url;
        //$eventsList[] = $eventDetailInfo;
        //      }
//      return $eventsList;
        return $eventDetailInfo;

    }

    private function getEventDescription($eventId)
    {
        $url = $this->config['fb_events_script_url'];

        $data = [
            'variables' => '{"eventID":"'.$eventId.'"}',
            'doc_id' => $this->config['fb_event_description_doc_id']
        ];

        $response = json_decode($this->makeRequest($url, $data));

        return isset($response->data->event->details->text)? $response->data->event->details->text : null;
    }

    private function getDetailEventInfo($eventId)
    {

        $eventURL = 'https://www.facebook.com/events/'.$eventId;

        $detailEventPageData = $this->makeRequest($eventURL);
        $detailEventPageData = str_replace(['<!--', '-->'], '', $detailEventPageData);

        $pq = phpQuery::newDocumentHTML($detailEventPageData);

        $eventTimeData = $this->getEventTimeData($pq->find('div._2ycp')->attr('content'));
        $placeCoordinates = $this->getPlaceCoordinates($pq->find('img._a3f')->attr('src'));

        $this->referrerURL = $eventURL;

        return [
            'event_id'	=> $eventId,
            'event_times' => $pq->find('div._2ycp')->text(),
            'description' => $this->getEventDescription($eventId),
            'start_time' => $eventTimeData['start_time'],
            'end_time' => $eventTimeData['end_time'],
            'place' => $pq->find('a._5xhk')->text(),
            'address' => $pq->find('a._5xhk')->_next('div')->text()? : $pq->find('span._5xhk:eq(0)')->text(),
            'cover' => $pq->find('div.uiScaledImageContainer img')->attr('src'),
            'place_lng' => $placeCoordinates['place_lng'],
            'place_lat' => $placeCoordinates['place_lat'],
        ];
    }

    public function parseEventHTML($html){
        $html = str_replace(['<!--', '-->'], '', $html);

        $pq = phpQuery::newDocumentHTML($html);

        $eventTimeData = $this->getEventTimeData($pq->find('div._2ycp')->attr('content'));
        $placeCoordinates = $this->getPlaceCoordinates($pq->find('img._a3f')->attr('src'));

        $x = preg_match("/\d+/", $pq->find('a._3m1v._468f')->attr('href'), $matches);

        return [
            'event_id'	=> $matches[0],
            'ticket_uri' => $pq->find('#event_summary a._36hm')->attr('href'),
            'title' => $pq->find('h1._5gmx')->text(),
            'event_times' => $pq->find('div._2ycp')->text(),
            'description' => $pq->find('#reaction_units ._63ew span')->html(),
            'start_time' => $eventTimeData['start_time'],
            'end_time' => $eventTimeData['end_time'],
            'place' => $pq->find('a._5xhk')->text(),
            'address' => $pq->find('a._5xhk')->_next('div')->text()? : $pq->find('span._5xhk:eq(0)')->text(),
            'cover' => $pq->find('div.uiScaledImageContainer img.scaledImageFitWidth')->attr('src'),
            'place_lng' => $placeCoordinates['place_lng'],
            'place_lat' => $placeCoordinates['place_lat'],
        ];
    }

    public function parseEventListHTML($html){
        $html = str_replace(['<!--', '-->'], '', $html);
        $pq = phpQuery::newDocumentHTML($html);

        $titles = $pq->find('#upcoming_events_card ._24er ._4dmk a');
        $events = [];

        foreach($titles as $title){
            $id = $title->getAttribute('data-hovercard');
            $id = explode( '?id=', $id)[1];
            $events[] = $id;
        }

        return $events;

    }
    
    private function getDetailRecurringEventInfo($eventId)
    {

        $eventURL = 'https://www.facebook.com/events/'.$eventId;

        $detailEventPageData = $this->makeRequest($eventURL);
        $detailEventPageData = str_replace(['<!--', '-->'], '', $detailEventPageData);

        $pq = phpQuery::newDocumentHTML($detailEventPageData);

        $eventTimeData = $this->getRecurringEventTimeData($eventId, $pq->find('a._2mg9'));
        $placeCoordinates = $this->getPlaceCoordinates($pq->find('img._a3f')->attr('src'));

        $this->referrerURL = $eventURL;

        return [
            'event_id'	=> $eventId,
            'event_time_data' => $eventTimeData,
            'place' => $pq->find('a._5xhk')->text(),
            'address' => $pq->find('a._5xhk')->_next('div')->text()? : $pq->find('span._5xhk:eq(0)')->text(),
            'place_lng' => $placeCoordinates['place_lng'],
            'place_lat' => $placeCoordinates['place_lat'],
        ];
    }

    private function getPlaceCoordinates($mapUrl)
    {
        preg_match('/markers=(\d+\.\d+)%2C(\d+\.\d+)/', $mapUrl, $coordinates);

        return [
            'place_lat' => isset($coordinates[1])? $coordinates[1] : null,
            'place_lng' => isset($coordinates[2])? $coordinates[2] : null,
        ];
    }

    private function getEventTimeData($timeStr)
    {
        $eventTimeInfo = explode(' to ', $timeStr);

        return [
            'start_time' => isset($eventTimeInfo[0])? strtotime($eventTimeInfo[0]) : null,
            'end_time' => isset($eventTimeInfo[1])? strtotime($eventTimeInfo[1]) : null,
            // 'start_time' => isset($eventTimeInfo[0])? date('Y-m-d H:i:s', strtotime($eventTimeInfo[0])) : null,
            // 'end_time' => isset($eventTimeInfo[1])? date('Y-m-d H:i:s', strtotime($eventTimeInfo[1])) : null,
        ];
    }
    
    private function getRecurringEventTimeData($eventId, $linkData)
    {
        $eventTimeData = [];
        
        foreach($linkData as $link) {
            $pq = pq($link);
            preg_match('/event_time_id=(\d+)&/', $pq->attr('href'), $eventTimeIdData);
            
            if(isset($eventTimeIdData[1])) {
                $detailTimeData = $this->getDetailEventTimeData($eventId, $eventTimeIdData[1]);
                $eventTimeData[] = $detailTimeData;
            }
            
        }
        
        return $eventTimeData;
    }
    
    private function getDetailEventTimeData($eventId, $eventTimeId)
    {
        $eventURL = "https://www.facebook.com/events/$eventId/?event_time_id=$eventTimeId";

        $detailEventPageData = $this->makeRequest($eventURL);
        $detailEventPageData = str_replace(['<!--', '-->'], '', $detailEventPageData);

        $pq = phpQuery::newDocumentHTML($detailEventPageData);

        $eventTimeData = $this->getEventTimeData($pq->find('div._2ycp')->attr('content'));

        $this->referrerURL = $eventURL;

        return [
            'event_time_id' => $eventTimeId,
            'event_times' => $pq->find('div._2ycp')->text(),
            'start_time' => $eventTimeData['start_time'],
            'end_time' => $eventTimeData['end_time'],
        ];
    }

    private function makeRequest($url, $data = [])
    {
        $ch = curl_init($url);

        $proxy = 'proxy.crawlera.com:8010';
        $proxy_auth = $this->config['crawlera_api_key'];

        curl_setopt($ch, CURLOPT_URL, $url);
        //  curl_setopt($ch, CURLOPT_PROXY, $proxy);
        //  curl_setopt($ch, CURLOPT_PROXYUSERPWD, $proxy_auth);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->config['parser_useragent']);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //required for HTTPS
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        if($this->referrerURL) {
            curl_setopt($ch, CURLOPT_REFERER, $this->referrerURL);
        }

        if($data) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);

        if($response === false) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }
}


