<?php

require_once( __DIR__ . '/../../lib/summarize_trades.class.php');

$market = @$_GET['market'];
$format = @$_GET['format'];

function bail($code, $msg) {
    header($_SERVER["SERVER_PROTOCOL"]." $code $msg", true, $code);
    die( $msg );
}

if( !$market ) {
    bail( 404, "Not found" );
}

$criteria = [
    'market' => $market,
    'datetime_from' => @$_GET['timestamp_from'],
    'datetime_to' => @$_GET['timestamp_to'],
    'direction' => @$_GET['direction'],
    'limit' => @$_GET['limit'],
    'sort' => @$_GET['sort'],
    'integeramounts' => @$_GET['integeramounts'],
    'fields' => @$_GET['fields'] ? explode(',', $_GET['fields'] ) : null
];

$trades = new trades();
$results = $trades->get_trades($criteria);

if( $format == 'csv' ) {
    // serve response to client.
    $fh = fopen( 'php://output', 'w');
    if( count( $results ) ) {
        fputcsv($fh, array_keys( $results[0] ) );
    }

    foreach( $rows as $k => $row ) {
        fputcsv( $fh, $row );
    }
    fclose( $fh );
}
else  {
    echo json_encode( $results, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
}

