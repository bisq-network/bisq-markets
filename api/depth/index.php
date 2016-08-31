<?php

require_once( __DIR__ . '/../../lib/offers.class.php');

$market = @$_GET['market'];
$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.

if( !$market ) {
    bail( "market parameter missing" );
}

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}

$offers = new offers();

$criteria = ['market' => $market,
             'direction' => 'BUY',
             'integeramounts' => false,
             'sort' => 'desc'
            ];
$buys = $offers->get_offers( $criteria );

$criteria['direction'] = 'SELL';
$criteria['sort'] = 'asc';
$sells = $offers->get_offers( $criteria );

$results = [];
foreach( ['buys' => $buys, 'sells' => $sells] as $dir => $list ) {
    foreach( $list as $offer ) {
        $results[$dir][] = $offer['price'];
    }
}

$result = [ $market => $results ];    
echo json_encode( $result, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
