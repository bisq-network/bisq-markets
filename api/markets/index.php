<?php

require_once( __DIR__ . '/../../lib/markets.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
$timestamp = (bool)@$_GET['timestamp'] != 'no';

$markets = new markets();


$pmarkets = primary_market::get_primary_market_list();

$results = [];
foreach( $pmarkets as $pmarket ) {
    primary_market::init_primary_market_path_setting_by_symbol($pmarket);
    $results = array_merge($results, $markets->get_markets($pmarket));
}

echo json_encode( $results, $format == 'json' ? 0 : JSON_PRETTY_PRINT );