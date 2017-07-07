<?php

require_once( __DIR__ . '/../../lib/ticker.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

try {
    $market = @$_GET['market'];
    $start = strtotime('-24 hour') ;
    $end = time();
    $format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
    
    // set some utility variables
    $range = $end - $start;
    
    $criteria = ['datetime_from' => $start,
                 'datetime_to' => $end,
                 'interval' => 'day',
                 'integeramounts' => false,
                ];
    
    $result = null;
    if( $market ) {
        $network = primary_market::determine_network_from_market($market);
        $ticker = new ticker($network);
        $result = [$ticker->get_market_ticker($market, $criteria)];
    }
    else {
        $result = ticker::get_all_markets_ticker($criteria);
    }
            
    echo json_encode( $result, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e );
    }
    bail($e->getCode() == 2 ? $e->getMessage() : "An unexpected error occurred.");
    
}

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}
