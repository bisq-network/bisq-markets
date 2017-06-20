<?php

require_once( __DIR__ . '/../../lib/markets.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
$timestamp = (bool)@$_GET['timestamp'] != 'no';

try {
    $networks = primary_market::get_network_list();
    
    $results = [];
    foreach( $networks as $network ) {
        $markets = new markets($network);
        $results = array_merge($results, $markets->get_markets());
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

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}