<?php

require_once( __DIR__ . '/../../lib/summarize_trades.class.php');
require_once( __DIR__ . '/../../lib/primary_market.class.php');

$market = @$_GET['market'];
$interval = @$_GET['interval'];
$start = @$_GET['timestamp_from'] ?: strtotime('2016-01-01') ;
$end = @$_GET['timestamp_to'] ?: time();
$format = @$_GET['format'] ?: 'jsonpretty';  // csv, jsonpretty, or json.
$endcaps = @$_GET['endcaps'];
$fillgaps = @$_GET['fillgaps'];
$timestamp = (bool)@$_GET['timestamp'] != 'no';
$callback = @$_GET['callback'];  // a weird thing needed by highcharts.
$milliseconds = @$_GET['milliseconds'];

if( $milliseconds ) {
    $start = @$_GET['timestamp_from'] ? (int)($_GET['timestamp_from'] / 1000) : $start;
    $end = @$_GET['timestamp_from'] ? (int)($_GET['timestamp_to'] / 1000) : $end;
}

function bail($msg) {
    $result = ["success" => 0, "error" => $msg ];
    die( json_encode( $result, $GLOBALS['format'] == 'json' ? 0 : JSON_PRETTY_PRINT ) );
}

if( !$market ) {
    bail( "market parameter missing." );
}

// make it harder to inject random things into the output.
if( $callback && (substr($callback, 0, 6) != 'jQuery' ||
                  strlen($callback) > 50)) {
	bail( "Invalid callback parameter." );
}


try {

	$network = primary_market::determine_network_from_market($market);
	
	if ($start && !preg_match('/^[0-9]+$/', $start)) {
		bail("Invalid start parameter: $start");
	}
	if ($end && !preg_match('/^[0-9]+$/', $end)) {
		bail("Invalid end parameter: $end");
	}
	if( $interval && !in_array( $interval, ['minute', 'half_hour', 'hour', 'half_day', 'day', 'week', 'month', 'year', 'auto'] )) {
		bail("Invalid interval parameter: $interval");
	}
	
	
	// set some utility variables
	$range = $end - $start;
	
	$summarizer = new summarize_trades($network);
	
	$criteria = ['market' => $market,
				 'datetime_from' => $start,
				 'datetime_to' => $end,
				 'integeramounts' => false,
				 'fields' => ['period_start','open','high','low','close', 'volume_left','volume_right', 'avg'],
				 'sort' => 'asc',
				 'fillgaps' => $fillgaps,
				];
	
	switch( $interval ) {
		
		case 'minute':  $rows = $summarizer->get_trade_summaries_minutes($criteria); break;
		case 'half_hour':  $rows = $summarizer->get_trade_summaries_half_hours($criteria); break;
		case 'hour':  $rows = $summarizer->get_trade_summaries_hours($criteria); break;
		case 'half_day':  $rows = $summarizer->get_trade_summaries_half_days($criteria); break;
		case 'day':  $rows = $summarizer->get_trade_summaries_days($criteria); break;
		case 'week':  $rows = $summarizer->get_trade_summaries_weeks($criteria); break;
		case 'month':  $rows = $summarizer->get_trade_summaries_months($criteria); break;
		case 'year':  $rows = $summarizer->get_trade_summaries_years($criteria); break;
		
		case 'auto':
		default:
			// find the right table
			// two days range loads minute data
			if($range <= 3600) {
				// up to one hour range loads minutely data
				$rows = $summarizer->get_trade_summaries_minutes($criteria);
			}
			else if($range <= 1 * 24 * 3600) {
				// up to one day range loads half-hourly data
				$rows = $summarizer->get_trade_summaries_half_hours($criteria);
			}
			elseif($range <= 3 * 24 * 3600) {
				// up to 3 day range loads hourly data
				$rows = $summarizer->get_trade_summaries_hours($criteria);
			}
			elseif($range <= 7 * 24 * 3600) {
				// up to 7 day range loads half-daily data
				$rows = $summarizer->get_trade_summaries_half_days($criteria);
			}
			elseif($range <= 60 * 24 * 3600) {
				// up to 2 month range loads daily data
				$rows = $summarizer->get_trade_summaries_days($criteria);
			}
			elseif($range <= 12 * 31 * 24 * 3600) {
				// up to one year range loads weekly data
				$rows = $summarizer->get_trade_summaries_weeks($criteria);
			}
			elseif($range <= 12 * 31 * 24 * 3600) {
				// up to 5 year range loads monthly data
				$rows = $summarizer->get_trade_summaries_months($criteria);
			}
			else {
				// greater range loads yearly data
				$rows = $summarizer->get_trade_summaries_years($criteria);
			}
			break;
	}
	
	if( $endcaps) {
		if( count($rows ) ) {
			$first = $rows[0];
			$last = $rows[count($rows)-1];
			array_unshift( $rows, ['period_start' => $start, $first['open'], $first['open'], $first['open'], $first['open'], 0, 0, 0] );
			array_push( $rows, ['period_start' => $end, $last['close'], $last['close'], $last['close'], $last['close'], 0, 0, 0] );
		}
		else {
			array_unshift( $rows, ['period_start' => $start, 0, 0, 0, 0, 0, 0, 0] );
			array_push( $rows, ['period_start' => $end, 0, 0, 0, 0, 0, 0, 0] );
		}
	}
	
	if( $format == 'jscallback' ) {
		echo htmlentities($callback) . "([\n";
		foreach( $rows as $k => $row ) {
			if( $milliseconds ) {
				$row['period_start'] *= 1000;
			}
			echo "[" . implode( ",", $row ) . "]" . ($k == count($rows) -1 ? "\n" : ",\n");
		}
		echo "])\n";
	}
	else if( $format == 'csv') {
		// serve response to client.
		$fh = fopen( 'php://output', 'w');
		
		fputcsv($fh, array_values( ['period_start','open','high','low','close','volume_left', 'volume_right', 'avg'] ) );
	
		foreach( $rows as $k => $row ) {
			if( !$timestamp ) {
				$row['period_start'] = date('c', $row['period_start']);
			}
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
		echo json_encode( $rows, $format == 'json' ? 0 : JSON_PRETTY_PRINT );
	}
}
catch( Exception $e ) {
//  for dev/debug.
    if( @$_GET['debug'] ) {
        _global_exception_handler( $e );
    }
    bail($e->getCode() == 2 ? $e->getMessage() : "An unexpected error occurred.");
}


