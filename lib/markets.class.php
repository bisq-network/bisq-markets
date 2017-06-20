<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/trades.class.php' );

class markets {
    
    private $network;
    
    public function __construct($network) {
        $this->network = $network;
    }
    
    public function get_markets() {
        list($pmarket) = explode('_', $this->network);
        $pmarket = strtoupper($pmarket);
        
        $currencies = new currencies($this->network);
        $clist = $currencies->get_all_currencies();
        
        $markets = [];
        foreach( $clist as $symbol => $c ) {
            
            if( $symbol == $pmarket ) {
                continue;
            }
            
            // here we make fiat markets always primary, eg BTC/USD.
            // and BTC always primary against other crypto, eg XMR/BTC.
            // This is a kludge.  should be getting this info from bitsquare json.
            
            $is_fiat = $c['type'] == 'fiat';
            $pmarketname = $clist[$pmarket]['name'];
            
            $lsymbol = $is_fiat ? $pmarket : $symbol;
            $rsymbol = $is_fiat ? $symbol : $pmarket;
            $lname = $is_fiat ? $pmarketname : $c['name'];
            $rname = $is_fiat ? $c['name']: $pmarketname;
            $ltype = $is_fiat ? 'crypto' : $c['type'];
            $rtype = $is_fiat ? 'fiat' : 'crypto';
            $lprecision = 8;
            $rprecision = $is_fiat ? 2 : 8;
            
            $pair = sprintf( '%s_%s', strtolower($lsymbol), strtolower($rsymbol) );
            $type = $is_fiat ? 'crypto/fiat' : 'crypto/crypto';
            
            $market = ['pair' => $pair,
                       'lname' => $lname,
                       'rname' => $rname,
                       'lsymbol' => $lsymbol,
                       'rsymbol' => $rsymbol,
                       'lprecision' => $lprecision,
                       'rprecision' => $rprecision,
                       'ltype' => $ltype,
                       'rtype' => $rtype,
                       'name' => sprintf( '%s/%s', $lname, $rname ),
                      ];
            // maybe add more attributes later.
            $markets[$pair] = $market;
        }

        ksort( $markets );        
        return $markets;
    }
    
    public function get_markets_with_trades() {
        $t = new trades($this->network);
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
    
    public function validate_market($market) {
        $markets = $this->get_markets();
        $match = @$markets[$market];
        
        if( !$match ) {
            throw new Exception("Unknown market $market", 2);
        }
    }
}
