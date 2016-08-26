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
            
            if( $symbol == 'BTC' ) {
                continue;
            }
            
            // here we make fiat markets always primary, eg BTC/USD.
            // and BTC always primary against other crypto, eg XMR/BTC.
            // This is a kludge.  should be getting this info from bitsquare json.
            
            $is_fiat = $c['type'] == 'fiat';            
            
            $lsymbol = $is_fiat ? 'BTC' : $symbol;
            $rsymbol = $is_fiat ? $symbol : 'BTC';
            $lname = $is_fiat ? 'Bitcoin' : $c['name'];
            $rname = $is_fiat ? $c['name']: 'Bitcoin';
            $lprecision = 8;
            $rprecision = $is_fiat ? 2 : 8;
            
            $pair = sprintf( '%s_%s', strtolower($lsymbol), strtolower($rsymbol) );
            $type = $is_fiat ? 'crypto/fiat' : 'crypto/crypto';
            
            $market = ['pair' => $pair,
                       'lname' => $lname,
                       'lsymbol' => $lsymbol,
                       'rname' => $rname,
                       'rsymbol' => $rsymbol,
                       'lprecision' => $lprecision,
                       'rprecision' => $rprecision,
                       'type' => $type,
                       'name' => sprintf( '%s/%s', $lname, $rname ),
                      ];
            // maybe add more attributes later.
            $markets[$pair] = $market;
        }

        ksort( $markets );        

        return $markets;
    }
    
    public function get_markets_with_trades() {
        $t = new trades();
        $all = $t->get_all_trades();
        
        $markets = $this->get_markets();
        $traded = [];
        foreach( $all as $trade ) {
            $pair = $trade['market'];
            $market = @$markets[$pair];
            if( $market ) {
                $traded[$pair] = $markets[$pair];
            }
        }

        return $traded;
    }
    
    public function get_market_name( $pair ) {
        $m = $markets['pair'];
        
    }
}
