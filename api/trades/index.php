<?php

require_once( __DIR__ . '/../../lib/summarize_trades.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$market = @$_GET['market'];
$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
$timestamp = (bool)@$_GET['timestamp'] != 'no';

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}

if( !$market ) {
    bail( "market parameter missing" );
}

try {
    $network = primary_market::determine_network_from_market($market);
    
    $fields = ['market' => 'market', 'direction', 'tradePrice' => 'price', 'tradeAmount' => 'amount', 'offerId' => 'trade_id', 'tradeDate' => 'trade_date'];
    if( $market != 'all' ) {
        unset( $fields['market'] );
    }
    
    $criteria = [
        'market' => $market == 'all' ? null : $market,
        'datetime_from' => @$_GET['timestamp_from'] ?: strtotime('2016-01-01'),
        'datetime_to' => @$_GET['timestamp_to'] ?: time(),
        'direction' => @$_GET['direction'],
        'limit' => @$_GET['limit'] ?: 100,
        'sort' => @$_GET['sort'] ?: 'desc',
        'integeramounts' => (bool)@$_GET['integeramounts'],
        'fields' => $fields
    ];
    
    $criteria['limit'] = $criteria['limit'] <= 2000 ? $criteria['limit'] : 2000;
    
    $trades = new trades($network);
    $results = $trades->get_trades($criteria);
    
    echo json_encode( $results, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e );
    }
    bail($e->getCode() == 2 ? $e->getMessage() : "An unexpected error occurred.");
    
}
