<?php
header('Access-Control-Allow-Origin: *');

require_once( __DIR__ . '/../../lib/offers.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$market = @$_GET['market'];
$direction = @$_GET['direction'];
$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.

if( !$market ) {
    bail( "market parameter missing" );
}

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}

try {
    $network = primary_market::determine_network_from_market($market);
    
    $offers = new offers($network);
    
    $criteria = ['market' => $market,
                 'direction' => 'BUY',
                 'integeramounts' => false,
                 'sort' => 'desc',
                 'fields' => ['id' => 'offer_id', 'date' => 'offer_date', 'direction', 'minAmount' => 'min_amount', 'amount', 'price', 'volume', 'paymentMethod' => 'payment_method', 'offerFeeTxID' => 'offer_fee_txid'],
                ];
    $buys = $direction == 'SELL' ? null : $offers->get_offers( $criteria );
    
    $criteria['direction'] = 'SELL';
    $criteria['sort'] = 'asc';
    $sells = $direction == 'BUY' ? null : $offers->get_offers( $criteria );
    
    $results = ['buys' => $buys, 'sells' => $sells];
    
    $result = [ $market => $results ];    
    echo json_encode( $result, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e );
    }
    bail($e->getCode() == 2 ? $e->getMessage() : "An unexpected error occurred.");
    
}
