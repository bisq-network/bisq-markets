<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/trades.class.php' );

class markets {
    
    public function get_markets() {
        $currencies = new currencies();
        $clist = $currencies->get_all_currencies();
    
        $markets = [];
        foreach( $clist as $symbol => $c ) {
            $pair = strtolower($symbol) . '_' . 'btc';
            $market = ['pair' => $pair,
                       'lname' => $c['name'],
                       'lsymbol' => $symbol,
                       'rname' => 'Bitcoin',
                       'rsymbol' => 'BTC',
                       'name' => sprintf( "%s / %s", $c['name'], 'Bitcoin' )
                      ];
            // maybe add more attributes later.
            $markets[$pair] = $market;
        }
        return $markets;
    }
    
    public function get_markets_with_trades() {
        $t = new trades();
        $all = $t->get_all_trades();
        
        $markets = $this->get_markets();
        $traded = [];
        foreach( $all as $trade ) {
            $pair = strtolower($trade['currency']) . '_btc';
            $market = @$markets[$pair];
            if( $market ) {
                $traded[$pair] = $markets[$pair];
            }
        }
        
        ksort( $traded );
        return $traded;
    }
    
    public function get_market_name( $pair ) {
        $m = $markets['pair'];
        
    }
}
