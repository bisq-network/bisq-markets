<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );

class trades {
    function __construct() {
    }
    
    /**
     * criteria keys:
     *  + market: eg 'dash_btc', or 'all'
     *  + datetime_from: timestamp utc
     *  + datetime_to: timestamp utc
     *  + direction: 'buy', 'sell'
     *  + limit: max trades
     *  + sort: asc | desc.  default = asc
     *  + fields: array -- fields to return.
     *      available:  "currency", "direction", "tradePrice", "tradeAmount",
     *                  "tradeDate", "paymentMethod", "offerDate",
     *                  "useMarketBasedPrice", "marketPriceMargin",
     *                  "offerAmount", "offerMinAmount", "offerId",
     *                  "depositTxId"
     */
    public function get_trades( $criteria ) {
        
        $trades = $this->get_all_trades();
        extract( $criteria );  // puts keys in local namespace.

        $sort = @$sort ?: 'desc';
        $dtfrom_milli = @$datetime_from * 1000;
        $dtto_milli = @$datetime_to * 1000;
        $limit = @$limit ?: PHP_INT_MAX;
        
        $matches = [];
        foreach( $trades as $trade ) {
            if( @$market && $market != $trade['market']) {
                continue;
            }
            if( $dtfrom_milli && $dtfrom_milli > $trade['tradeDate']) {
                continue;
            }
            if( $dtto_milli && $dtto_milli < $trade['tradeDate']) {
                continue;
            }
            if( @$direction && $direction != $trade['direction'] ) {
                continue;
            }

            // convert to user specified field order list, if present.
            if( @$fields ) {
                $t = [];
                foreach( $fields as $f ) {
                    $t[$f] = @$trade[$f] ?: null;
                    $trade = $t;
                }
            }
            
            $matches[] = $trade;
            
            if( count($matches) >= $limit ) {
                break;
            }
        }

        if( $sort == 'asc') {
            $matches = array_reverse( $matches );
        }
        
        return $matches;
    }
    
    public function get_all_trades() {
        $json_file = '/home/danda/.local/share/Bitsquare/mainnet/db/trade_statistics.json';
        
        if( !function_exists( 'apcu_fetch' ) ) {
            // cache in mem for present request.
            static $result = null;
            if( $result ) {
                return $result;
            }
            $result = $this->get_all_trades_worker($json_file);
            return $result;
        }
        
        $result_key = 'all_trades_result';
        $ts_key = 'all_trades_timestamp';
        
        $cached_ts = apcu_fetch( $ts_key );
        $cached_result = apcu_fetch( $result_key );
        if( $cached_result && $cached_ts && filemtime( $json_file ) < $cached_ts ) {
            $result = $cached_result;
        }
        else {
            $result = $this->get_all_trades_worker($json_file);
            apcu_store( $ts_key, time() );
            apcu_store( $result_key, $result );
        }
        return $result;
    }
        
    private function get_all_trades_worker($json_file) {
        
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

        // add market key        
        foreach( $data as &$trade ) {
            $trade['market'] = sprintf( '%s_btc', strtolower($trade['currency']) );
            $trade['total'] = $trade['tradePrice'] * $trade['tradeAmount'];
        }
        
        return $data;
    }
}
