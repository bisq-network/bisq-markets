<?php

$json_file = '/home/danda/.local/share/Bitsquare/mainnet/db/trade_statistics.json';
mb_internal_encoding('UTF-8');

$market = @$_GET['market'];

function bail($code, $msg) {
    header($_SERVER["SERVER_PROTOCOL"]." $code $msg", true, $code);
    die( $msg );
}

list($left) = @explode( '_', $market );  // BTC will always be right.  ( for now? )
$left = 'DASH';
if( !$market || !$left ) {
    bail( 404, "Not found" );
}
$left = strtoupper($left);

// remove some garbage data at beginning of file, if present.
$fh = fopen( $json_file, 'r' );

// we use advisory locking.  hopefully bitsquare does too?
if( !$fh || !flock( $fh, LOCK_SH ) ) {
    bail( 500, "Internal Server Error" );
}
$buf = stream_get_contents( $fh );
fclose( $fh );

$start = strpos( $buf, '[');
$data = json_decode( substr($buf, $start), true );

$matches = [];
foreach( $data as $trade ) {
    if( $trade['currency'] == $left) {
        $matches[] = $trade;
    }
}

// serve JSON to client.
echo json_encode($matches, JSON_PRETTY_PRINT);