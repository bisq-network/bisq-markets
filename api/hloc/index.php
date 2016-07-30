<?php

require_once( __DIR__ . '/../../lib/summarize_trades.class.php');

$market = @$_GET['market'];
$interval = @$_GET['interval'];
$start = @$_GET['timestamp_from'] ?: strtotime('2016-01-01') ;
$end = @$_GET['timestamp_to'] ?: time();
$format = @$_GET['format'] ?: 'json';  // csv or json.
$endcaps = @$_GET['endcaps'];
$fillgaps = (bool)@$_GET['fillgaps'];
$prettyjson = (bool)@$_GET['prettyjson'];
$timestamp = (bool)@$_GET['timestamp'] != 'no';

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
if( $interval && !in_array( $interval, ['minute', 'hour', 'day', 'month'] )) {
	bail(500, "Invalid interval parameter: $interval");
}


// set some utility variables
$range = $end - $start;

$summarizer = new summarize_trades();

$criteria = ['market' => $market,
             'datetime_from' => $start,
             'datetime_to' => $end,
             'integeramounts' => false,
             'fields' => ['period_start','open','high','low','close','volume','avg'],
             'sort' => 'asc',
             'fillgaps' => $fillgaps,
            ];
switch( $interval ) {
    
    case 'minute':  $rows = $summarizer->get_trade_summaries_minutes($criteria); break;
    case 'hour':  $rows = $summarizer->get_trade_summaries_hours($criteria); break;
    case 'day':  $rows = $summarizer->get_trade_summaries_days($criteria); break;
    case 'month':  $rows = $summarizer->get_trade_summaries_months($criteria); break;
        
    default:
        // find the right table
        // two days range loads minute data
        if($range < 2 * 24 * 3600) {
            $rows = $summarizer->get_trade_summaries_minutes($criteria);
        }
        elseif($range < 31 * 24 * 3600) {
        // one month range loads hourly data
            $rows = $summarizer->get_trade_summaries_hours($criteria);
        }
        elseif($range < 15 * 31 * 24 * 3600) {
        // one year range loads daily data
            $rows = $summarizer->get_trade_summaries_days($criteria);
        }
        else {
        // greater range loads monthly data
            $rows = $summarizer->get_trade_summaries_months($criteria);
        }
        break;
}

if( $endcaps) {
    if( count($rows ) ) {
        $first = $rows[0];
        $last = $rows[count($rows)-1];
        array_unshift( $rows, ['period_start' => $start, $first['open'], $first['open'], $first['open'], $first['open'], 0, 0] );
        array_push( $rows, ['period_start' => $end, $last['close'], $last['close'], $last['close'], $last['close'], 0, 0] );
    }
    else {
        array_unshift( $rows, ['period_start' => $start, 0, 0, 0, 0, 0, 0] );
        array_push( $rows, ['period_start' => $end, 0, 0, 0, 0, 0, 0] );
    }
}

if( $format == 'csv' ) {
    // serve response to client.
    $fh = fopen( 'php://output', 'w');
    fputcsv($fh, array_values( ['period_start','open','high','low','close','volume','avg'] ) );

    foreach( $rows as $k => $row ) {
        if( !$timestamp ) {
            $row['period_start'] = date('c', $row['period_start']);
        }
        fwrite( $fh, implode( ",", $row ) . "\n" );
        fputcsv( $fh, $row );
    }
    fclose( $fh );
}
else {
    if( !$timestamp ) {
        foreach( $rows as $k => &$row ) {
            $row['period_start'] = date('c', $row['period_start']);
            
        }
    }
    echo json_encode( $rows, $prettyjson ? JSON_PRETTY_PRINT : 0 );
}


