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
    
    $fields = ['market' => 'market', 'direction', 'tradePrice' => 'price', 'tradeAmount' => 'amount', 'offerId' => 'trade_id', 'tradeDate' => 'trade_date'];
    if( $market != 'all' ) {
        unset( $fields['market'] );
    }
    
    $criteria = [
        'market' => $market == 'all' ? null : $market,
        'datetime_from' => @$_GET['timestamp_from'] ?: strtotime('2016-01-01'),
        'datetime_to' => @$_GET['timestamp_to'] ?: time(),
        'offer_id_from' => @$_GET['trade_id_from'],
        'offer_id_to' => @$_GET['trade_id_to'],
        'direction' => @$_GET['direction'],
        'limit' => @$_GET['limit'] ?: 100,
        'sort' => @$_GET['sort'] ?: 'desc',
        'integeramounts' => (bool)@$_GET['integeramounts'],
        'fields' => $fields
    ];
    
    $criteria['limit'] = $criteria['limit'] <= 2000 ? $criteria['limit'] : 2000;

    if($market == 'all') {
        $networks = primary_market::get_network_list();

        $results = [];
        foreach( $networks as $network ) {
            $trades = new trades($network);
            $results = array_merge($results, $trades->get_trades($criteria));
        }
        // now we must apply sort and limit to merged results.
        usort($results, function ($a, $b) use($criteria) {
            return $criteria['sort'] == 'asc' ? strcmp($a['trade_date'], $b['trade_date']) : 
                                                strcmp($b['trade_date'], $a['trade_date']);
        });
        if(count($results) > $criteria['limit']) {
            $results = array_slice($results, 0, $criteria['limit']);
        }
    }
    else {    
        $network = primary_market::determine_network_from_market($market);
        $trades = new trades($network);
        $results = $trades->get_trades($criteria);  
    }

    echo json_encode( $results, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e );
    }
    bail($e->getCode() == 2 ? $e->getMessage() : "An unexpected error occurred.");
    
}
