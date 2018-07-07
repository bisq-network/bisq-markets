<?php

require_once( __DIR__ . '/../../lib/currencies.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
$basecurrency = @$_GET['basecurrency'] ?: 'BTC';  // default to BTC.
$type = @$_GET['type'] ?: 'all';

try {
    $network = primary_market::get_network($basecurrency);
    $currencies = new currencies($network);
    
    switch($type) {
        case 'fiat':   $results = $currencies->get_all_fiat(); break;
        case 'crypto': $results = $currencies->get_all_crypto(); break;
        case 'all':    $results = $currencies->get_all_currencies(); break;
        default: bail("Invalid value for type parameter"); break;
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