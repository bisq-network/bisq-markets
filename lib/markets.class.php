<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/trades.class.php' );
require_once( __DIR__ . '/btcutil.class.php' );

class markets {
    
    public function get_markets() {
        $tradesobj = new trades();
        $trades = $tradesobj->get_all_trades();
    
        $markets = [];
        foreach( $trades as $trade ) {
            $pair = strtolower($trade['currency']) . '_' . 'btc';
            $market = ['pair' => $pair];
            // maybe add more attributes later.
            $markets[$pair] = $market;
        }
        ksort( $markets );
        return $markets;
    }
}
