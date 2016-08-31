<?php

require_once( __DIR__ . '/../../lib/markets.class.php');

$format = @$_GET['format'] ?: 'jsonpretty';  // jsonpretty, or json.
$timestamp = (bool)@$_GET['timestamp'] != 'no';

$markets = new markets();
$results = $markets->get_markets();

echo json_encode( $results, $format == 'json' ? 0 : JSON_PRETTY_PRINT );