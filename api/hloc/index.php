<?php

require_once( __DIR__ . '/../../lib/summarize_trades.class.php');

$market = @$_GET['market'];
$start = @$_GET['start'] ?: strtotime('2016-01-01') * 1000;
$end = @$_GET['end'];
$format = @$_GET['format'] ?: 'json';  // csv or json.

function bail($code, $msg) {
    header($_SERVER["SERVER_PROTOCOL"]." $code $msg", true, $code);
    die( $msg );
}

if( !$market ) {
    bail( 404, "Not found" );
}

if ($start && !preg_match('/^[0-9]+$/', $start)) {
	bail(500, "Invalid start parameter: $start");
}
if ($end && !preg_match('/^[0-9]+$/', $end)) {
	bail(500, "Invalid end parameter: $end");
}
if (!$end) {
    $end = time() * 1000;
}

// set some utility variables
$range = $end - $start;

$summarizer = new summarize_trades();

$criteria = ['market' => $market,
             'datetime_from' => $start / 1000,
             'datetime_to' => $end / 1000,
             'integeramounts' => false,
             'fields' => ['period_start','open','high','low','close','volume','avg'],
             'sort' => 'asc',
            ];

// find the right table
// two days range loads minute data
if ($range < 2 * 24 * 3600 * 1000) {
    $rows = $summarizer->get_trade_summaries_minutes($criteria);
}
elseif ($range < 31 * 24 * 3600 * 1000) {
// one month range loads hourly data
    $rows = $summarizer->get_trade_summaries_hours($criteria);
}
elseif ($range < 15 * 31 * 24 * 3600 * 1000) {
// one year range loads daily data
    $rows = $summarizer->get_trade_summaries_days($criteria);
} else {
// greater range loads monthly data
    $rows = $summarizer->get_trade_summaries_months($criteria);
}

if( $format == 'csv' ) {
    // serve response to client.
    $fh = fopen( 'php://output', 'w');
    fputcsv($fh, array_values( ['date','open','high','low','close','volume','value'] ) );
    foreach( $rows as $k => $row ) {
        $row['period_start'] = date('c', $row['period_start']/1000);
        fputcsv( $fh, $row );
    }
    fclose( $fh );
}
else {
    echo json_encode( $rows );
}


