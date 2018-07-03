<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );

class currencies {
    
    private $network;
    
    public function __construct($network) {
        $this->network = $network;
    }

    /**
     * returns all currences, sorted by code, with type identifer.
     */
    public function get_all_currencies() {
        
        static $results = [];
        if( @$results[$this->network] ) {
            return $results[$this->network];
        }
        
        $fiat = $this->get_all_fiat();
        foreach( $fiat as &$f ) {
            $f['type'] = 'fiat';
        }
        
        $crypto = $this->get_all_crypto();
        foreach( $crypto as &$c ) {
            $c['type'] = 'crypto';
        }
        $all = array_merge( $fiat, $crypto );
        ksort( $all );
        $results[$this->network] = $all;

        return $results[$this->network];
    }

    /**
     * returns all fiat currences, sorted by code
     */
    public function get_all_fiat() {
        $json_file = sprintf( '%s/%s/db/fiat_currency_list.json', settings::get('data_dir'), $this->network);
        
        static $results = [];
        if( @$results[$this->network] ) {
            return $results[$this->network];
        }

        $buf = file_get_contents($json_file);
        $start = strpos( $buf, '[');
        $results[$this->network] = $this->add_keys( json_decode( substr($buf, $start), true ) );
        return $results[$this->network];
    }
    
    /**
     * returns all crypto currences, sorted by code
     */
    public function get_all_crypto() {
        $json_file = sprintf( '%s/%s/db/crypto_currency_list.json', settings::get('data_dir'), $this->network);

        static $results = [];
        if( @$results[$this->network] ) {
            return $results[$this->network];
        } 

        $buf = file_get_contents($json_file);
        $start = strpos( $buf, "\n")-1;
        $results[$this->network] = $this->add_keys( json_decode( substr($buf, $start), true ) );
        
        return $results[$this->network];
    }

    /**
     * turns currency list into assoc array for fast lookup by symbol.
     */
    private function add_keys( $list ) {
        $r = [];
        foreach( $list as $v ) {
            $k = $v['code'];
            $r[$k] = $v;
        }
        return $r;
    }
    
}
