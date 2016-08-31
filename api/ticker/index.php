<?php

require_once( __DIR__ . '/../../lib/ticker.class.php');

$market = @$_GET['market'];
$start = strtotime('-24 hour') ;
$end = time();
$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.

// set some utility variables
$range = $end - $start;

$ticker = new ticker();

$criteria = ['datetime_from' => $start,
             'datetime_to' => $end,
             'interval' => 'day',
             'integeramounts' => false,
            ];
    
$result = $market ? [$market => $ticker->get_market_ticker($market, $criteria)] :
                    $ticker->get_all_markets_ticker($criteria);
        
echo json_encode( $result, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
