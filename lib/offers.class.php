<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/currencies.class.php' );
require_once( __DIR__ . '/filecache.class.php' );
require_once( __DIR__ . '/btcutil.class.php' );

class offers {
    function __construct() {
    }
    
    /**
     * criteria keys:
     *  + market: eg 'dash_btc', or 'all'
     *  + datetime_from: timestamp utc
     *  + datetime_to: timestamp utc
     *  + direction: 'buy', 'sell'
     *  + limit: max offers
     *  + sort: asc | desc   default = asc  ( by price )
     *  + integeramounts: bool.  default = true.
     *  + fields: array -- fields to return. optionally use key/val to map fields to another name.
     *      available:  "currency", "direction", "price", "amount",
     *                  "date",
     *                  "useMarketBasedPrice", "marketPriceMargin",
     *                  "amount", "minAmount", "id",
     *                  "offerFeeTxId"
     */
    public function get_offers( $criteria ) {

        $offers = $this->get_all_offers();
        extract( $criteria );  // puts keys in local namespace.

        $sort = @$sort ?: 'desc';
        $dtfrom_milli = @$datetime_from * 1000;
        $dtto_milli = @$datetime_to * 1000;
        $limit = @$limit ?: PHP_INT_MAX;
        $integeramounts = isset($integeramounts) ? $integeramounts : true;
        
        $matches = [];
        foreach( $offers as $offer ) {
            if( @$market && $market != $offer['market']) {
                continue;
            }
            if( $dtfrom_milli && $dtfrom_milli > $offer['date']) {
                continue;
            }
            if( $dtto_milli && $dtto_milli < $offer['date']) {
                continue;
            }
            if( @$direction && $direction != $offer['direction'] ) {
                continue;
            }

            if( !@$integeramounts ) {
                $offer['price'] = btcutil::int_to_btc( $offer['price'] );
                $offer['amount'] = btcutil::int_to_btc( $offer['amount'] );
                $offer['volume'] = btcutil::int_to_btc( $offer['volume'] );
                $offer['minAmount'] = btcutil::int_to_btc( $offer['minAmount'] );
            }
            
            // convert to user specified field order list, if present.
            if( @$fields ) {
                $t = [];
                foreach( $fields as $k => $f ) {
                    $old = is_string($k) ? $k : $f;
                    $new = $f;
                    $t[$new] = @$offer[$old];
                }
                $matches[] = $t;
            }
            else {
                $matches[] = $offer;
            }
            
            
            if( count($matches) >= $limit ) {
                break;
            }
        }
        

        if( $sort == 'asc') {
            $matches = array_reverse( $matches );
        }
        
        return $matches;
    }
    
    public function get_all_offers() {
        $json_file = settings::get('data_dir') . '/offers_statistics.json';
        return filecache::get( $json_file, 'all_offers_result', [$this, 'get_all_offers_worker'], [$json_file] );
    }
    
    /* This is only public so it can be used as callback for filecache class
     */
    public function get_all_offers_worker($json_file) {
        
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
        foreach( $data as $idx => &$offer ) {
            
            // change currencyCode to currency, to match trades class.
            $curr = $offer['currency'] = $offer['currencyCode'];
            unset( $offer['currencyCode'] );

            list($left, $right) = explode('/', $offer['currencyPair'] );
            $cleft = @$currlist[$left];
            $cright = @$currlist[$right];
            if( !$cleft || !$cright ) {
                unset( $data[$idx]);
                continue;
            }

            // Here we normalize integers to 8 units of precision. calling code depends on this.
            // note: all currencies are presently specified with 8 units of precision in json files
            // but this has not always been the case and could change in the future.
            $offer['price'] = $offer['primaryMarketPrice'] * pow( 10, 8 - $cright['precision'] );
            $offer['amount'] = $offer['primaryMarketAmount'] * pow( 10, 8 - $cleft['precision'] );
            $offer['volume'] = $offer['primaryMarketVolume'] * pow( 10, 8 - $cright['precision'] );
            $offer['market'] = strtolower( str_replace( '/', '_', $offer['currencyPair'] ) );
            
            // trade direction is given to us Bitcoin-centric.  Here we make it refer to the left side of the market pair.
            $offer['direction'] = $offer['primaryMarketDirection'];            
        }

        // sort desc by price.
        usort( $data, function( $a, $b ) {
            $a = $a['price'];
            $b = $b['price'];
            
            if( $a == $b ) {
                return 0;
            }
            return $a > $b ? -1 : 1;
        });
        
        return $data;
    }
}
