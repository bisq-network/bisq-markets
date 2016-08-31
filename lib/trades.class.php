<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/filecache.class.php' );

class trades {
    
    private $json_file;
    
    function __construct() {
        $this->json_file = settings::get('data_dir') . '/trade_statistics.json';
    }
    
    /**
     * criteria keys:
     *  + market: eg 'dash_btc', or 'all'
     *  + datetime_from: timestamp utc
     *  + datetime_to: timestamp utc
     *  + direction: 'buy', 'sell'
     *  + limit: max trades
     *  + sort: asc | desc.  default = asc
     *  + integeramounts: bool.  default = true.
     *  + fields: array -- fields to return. optionally use key/val to map fields to another name.
     *      available:  "currency", "direction", "tradePrice", "tradeAmount",
     *                  "tradeDate", "paymentMethod", "offerDate",
     *                  "useMarketBasedPrice", "marketPriceMargin",
     *                  "offerAmount", "offerMinAmount", "offerId",
     *                  "depositTxId"
     */
    public function get_trades( $criteria ) {
        
        extract( $criteria );  // puts keys in local namespace.

        $trades = @$market ? $this->get_trades_by_market( $market ) :
                             $this->get_all_trades();
                            
        $sort = @$sort ?: 'desc';
        $dtfrom_milli = @$datetime_from * 1000;
        $dtto_milli = @$datetime_to * 1000;
        $limit = @$limit ?: PHP_INT_MAX;
        $direction = @$direction ? strtoupper( $direction ) : null;
        $integeramounts = isset($integeramounts) ? $integeramounts : true;
        
        $matches = [];
        foreach( $trades as $trade ) {
            if( $market && $market != $trade['market']) {
                continue;
            }
            if( $dtfrom_milli && $dtfrom_milli > $trade['tradeDate']) {
                continue;
            }
            if( $dtto_milli && $dtto_milli < $trade['tradeDate']) {
                continue;
            }
            if( $direction && $direction != $trade['direction'] ) {
                continue;
            }

            if( !@$integeramounts ) {
                $trade['tradePrice'] = btcutil::int_to_btc( $trade['tradePrice'] );
                $trade['tradeAmount'] = btcutil::int_to_btc( $trade['tradeAmount'] );
                $trade['offerAmount'] = btcutil::int_to_btc( $trade['offerAmount'] );
                $trade['offerMinAmount'] = btcutil::int_to_btc( $trade['offerMinAmount'] );
            }
            
            // convert to user specified field order list, if present.
            if( @$fields ) {
                $t = [];
                $t2 = $trade;

                foreach( $fields as $k => $f ) {
                    $old = is_string($k) ? $k : $f;
                    $new = $f;
                    $t[$new] = @$t2[$old];
                }
                $trade = $t;
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

    public function get_last_trade_by_market( $market ) {
        $results = filecache::get( $this->json_file, 'market_last_result', [$this, 'get_markets_trades_worker'] );
        return @$results[$market]['last'];
    }
    
    public function get_trades_by_market( $market ) {
        $results = filecache::get( $this->json_file, 'market_trades_result', [$this, 'get_markets_trades_worker'] );
        return @$results[$market]['trades'] ?: [];
    }
    
    public function get_all_trades() {
        return filecache::get( $this->json_file, 'all_trades_result', [$this, 'get_all_trades_worker'] );
    }

    /* This is only public so it can be used as callback for cache class
     */
    public function get_markets_trades_worker() {
        $all_trades = $this->get_all_trades();
        $results = [];
        foreach( $all_trades as $trade ) {
            $market = $trade['market'];
            $mkt =& $results[$market];
            $mkt['trades'][] = $trade;

            // trades are in descending order.  So to save the latest trade,
            // we store the first one we find for each market.
            if( !isset( $mkt['last'] )) {
                $mkt['last'] = $trade;
            }
        }
        return $results;
    }

    
    
    /* This is only public so it can be used as callback for cache class
     */
    public function get_all_trades_worker() {
        $json_file = $this->json_file;
        
        // only needed to determine if currency is fiat or not.
        $currencies = new currencies();
        $currlist = $currencies->get_all_currencies();
        
        // remove some garbage data at beginning of file, if present.
        $fh = fopen( $json_file, 'r' );
        
        // we use advisory locking.  hopefully bitsquare does too?
        if( !$fh || !flock( $fh, LOCK_SH ) ) {
            bail( 500, "Internal Server Error" );
        }
        $buf = stream_get_contents( $fh );
        fclose( $fh );
        
        $start = strpos( $buf, "\n")-1;
        $data = json_decode( substr($buf, $start), true );
        // add market key        
        foreach( $data as $idx => &$trade ) {
            
            list($left, $right) = explode('/', $trade['currencyPair'] );
            $cleft = @$currlist[$left];
            $cright = @$currlist[$right];
            if( !$cleft || !$cright ) {
                // This weeds out any trades with symbols that are not defined in the currency*.json files.
                unset( $data[$idx]);
                continue;
            }
            
            // Here we normalize integers to 8 units of precision. calling code depends on this.
            // note: all currencies are presently specified with 8 units of precision in json files
            // but this has not always been the case and could change in the future.
            $trade['tradePrice'] = $trade['primaryMarketTradePrice'] * pow( 10, 8 - $cright['precision'] );
            $trade['tradeAmount'] = $trade['primaryMarketTradeAmount'] * pow( 10, 8 - $cleft['precision'] );
            $trade['tradeVolume'] = $trade['primaryMarketTradeVolume'] * pow( 10, 8 - $cright['precision'] );
            $trade['market'] = strtolower( str_replace( '/', '_', $trade['currencyPair'] ) );

            // trade direction is given to us Bitcoin-centric.  Here we make it refer to the left side of the market pair.
            $trade['direction'] = $trade['primaryMarketDirection'];
        }
        return $data;
    }
    
}
