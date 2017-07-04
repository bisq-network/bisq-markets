<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );

/**
 * Contains logic related to determining the primary market
 */
class primary_market {

    /* returns a list of active primary market symbols, eg BTC, LTC.
     */
    static public function get_primary_market_list() {
        $networks = self::get_network_list();
        $list = [];
        
        foreach($networks as $network) {
            list($symbol) = explode( '_', $network);
            $list[] = self::get_normalized_primary_market_symbol($symbol);
        }
        return $list;
    }

    /* returns a list of active networks.
     * 
     * active networks are defined as those that exist
     * in Bitsquare appdata, eg:
     *    ~/.local/share/Bitsquare/btc_mainnet
     *    ~/.local/share/Bitsquare/ltc_mainnet
     * and also match the network setting for each symbol.
     */
    static public function get_network_list() {
        $data_dir = settings::get( 'data_dir' );
        $dirs = glob( $data_dir . '/*_*/db', GLOB_ONLYDIR);
        
        $list = [];
        foreach( $dirs as $dir ) {
             $network = basename(dirname($dir));
             list($symbol, $net) = explode('_', $network);
            
            // filter out any that do not match the network setting for this symbol.
            if( self::get_primary_market_network($symbol) == $net ) {
                $list[] = $network;
            }
        }
        return $list;
    }

    static public function get_normalized_primary_market_symbol($symbol) {
        // normalize and default to ltc.
        return @strtoupper($symbol) ?: 'BTC';
    }
    
    static public function get_primary_market_network($symbol) {
        $symbol = strtolower($symbol);
        return settings::get( $symbol . '_' . 'network', $missing_ok = true) ?: 'mainnet';
    }
    
    static public function get_network($symbol) {
        return strtolower($symbol) . '_' . self::get_primary_market_network($symbol);
    }
    
    static public function get_primary_market_path($symbol) {
        $primary_market_network = self::get_primary_market_network($symbol);

        return sprintf( '%s/%s_%s/db/',
                        settings::get('data_dir'),
                        strtolower(self::get_normalized_primary_market_symbol($symbol)),
                        self::get_primary_market_network($symbol) ); 
    }
    
    static public function determine_network_from_market($market) {
        $network = self::get_network( self::determine_primary_market_symbol_from_market($market));
        return $network;
    }
    
    static public function determine_primary_market_symbol_from_market($market) {
        list($left, $right) = @explode('_', $market);
        if( !$left || !$right) {
            throw new Exception( "Invalid market identifier", 2 );
        }
        
        if(is_dir(self::get_primary_market_path($right))) {
            return self::get_normalized_primary_market_symbol($right);
        }

        if(is_dir(self::get_primary_market_path($left))) {
            return self::get_normalized_primary_market_symbol($left);
        }
        
        throw new Exception("Invalid market identifier", 2);
    }
    
    static public function determine_primary_market_path_from_market($market) {
        $symbol = self::determine_primary_market_symbol_from_market($market);
        return self::get_primary_market_path($symbol);
    }
    
};
