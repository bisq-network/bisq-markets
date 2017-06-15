<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );

/**
 * Contains logic related to determining the primary market
 */
class primary_market {

    /* returns a list of primary markets.
     * 
     * primary markets are defined as those that exist
     * in Bitsquare appdata, eg:
     *    ~/.local/share/Bitsquare/btc_mainnet
     *    ~/.local/share/Bitsquare/ltc_mainnet
     * and also match the network setting for each symbol.
     */
    static public function get_primary_market_list() {
        $data_dir = settings::get( 'data_dir' );
        $dirs = glob( $data_dir . '/*_*/db', GLOB_ONLYDIR);
        
        $list = [];
        foreach( $dirs as $dir ) {
            list($symbol, $network) = explode( '_', basename(dirname($dir)));
            
            // filter out any that do not match the network setting for this symbol.
            if( self::get_primary_market_network($symbol) == $network ) {
                $list[] = self::get_normalized_primary_market_symbol($symbol);
            }
        }
        return $list;
    }
    
    static public function get_normalized_primary_market_symbol($symbol) {
        // normalize and default to btc.
        return @strtoupper($symbol) ?: 'BTC';
    }
    
    static public function get_primary_market_network($symbol) {
        $symbol = strtolower($symbol);
        return settings::get( $symbol . '_' . 'network', $missing_ok = true) ?: 'mainnet';
    }
    
    static public function get_primary_market_path($symbol) {
        $primary_market_network = self::get_primary_market_network($symbol);

        return sprintf( '%s/%s_%s/db/',
                        settings::get('data_dir'),
                        strtolower(self::get_normalized_primary_market_symbol($symbol)),
                        self::get_primary_market_network($symbol) ); 
    }
    
    /**
     * This function will determine the primary market data path and store it in the
     * settings for later use by classes that deal with accessing .json data files.
     */
    static public function init_primary_market_path_setting_by_symbol($symbol) {
        settings::set('primary_market_data_path', self::get_primary_market_path($symbol) );
    }

    static public function determine_primary_market_symbol_from_market($market) {
        list($left, $right) = @explode('_', $market);
        if( !$left || !$right) {
            throw new Exception( "Invalid market identifier" );
        }
        
        if(is_dir(self::get_primary_market_path($right))) {
            return self::get_normalized_primary_market_symbol($right);
        }

        if(is_dir(self::get_primary_market_path($left))) {
            return self::get_normalized_primary_market_symbol($left);
        }
        
        throw new Exception("Could not determine primary market from market id");
    }
    
    static public function determine_primary_market_path_from_market($market) {
        $symbol = self::determine_primary_market_symbol_from_market($market);
        return self::get_primary_market_path($symbol);
    }
    
    /**
     * Given a market id, eg "btc_usd" or "xmr_btc" or "xmr_ltc", this function
     * will determine the primary market data path and store it in the
     * settings for later use by classes that deal with accessing .json data files.
     *
     * btc_usd --> btc_mainnet
     * xmr_btc --> btc_mainnet
     * xmr_ltc --> ltc_mainnet
     * btc_ltc --> ltc_mainnet
     */
    static public function init_primary_market_path_setting($market) {
        settings::set('primary_market_data_path', self::determine_primary_market_path_from_market($market) );
    }
};