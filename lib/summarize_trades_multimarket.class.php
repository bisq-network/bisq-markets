<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/trades.class.php' );
require_once( __DIR__ . '/btcutil.class.php' );
require_once( __DIR__ . '/summarize_trades.class.php' );
require_once( __DIR__ . '/currencies.class.php' );

class summarize_trades_multimarket extends summarize_trades {
    
    /**
     * Gets trade summaries (volume) across all markets
     * for a particular base currency (network)
     *
     * criteria keys:
     *  + interval.  required. in seconds.
     *  + datetime_from: timestamp utc. required.
     *  + datetime_to: timestamp utc.  required.
     *  + one_period: if true, create only a single summary period.
     *  + direction: buy, sell
     *  + integeramounts: bool.  default = true.
     *  + fillgaps: bool.  default = false.
     *  + fields: array -- fields to return.
     *      available:  "period_start", "open", "close",
     *                  "high", "low", "avg", "volume"
     */
    public function get_trade_summaries( $criteria ) {
        extract( $criteria );
        $tradesobj = new trades($this->network);
        unset( $criteria['fields'] );
        unset( $criteria['limit'] );
        $criteria['sort'] = 'asc';
        $criteria['integeramounts'] = true;
        $integeramounts = isset($integeramounts) ? $integeramounts : true;
        
        $trades = $tradesobj->get_trades( $criteria );
        
        $intervals = [];
        
        $currencies = new currencies($this->network);
        $fiat = $currencies->get_all_fiat();
        
        foreach( $trades as $trade ) {
            $traded_at = $trade['tradeDate'] / 1000;
            $interval_start = @$one_period ? $datetime_from : $this->interval_start($traded_at, $interval);

            if( !isset($intervals[$interval_start]) ) {
                $intervals[$interval_start] = ['volume' => 0,
                                               'num_trades' => 0,
                                              ];
                $intervals_prices[$interval_start] = [];
            }
            
            $is_fiat = isset($fiat[$trade['currency']]);
            
            $period =& $intervals[$interval_start];
            $period['period_start'] = $interval_start;
            $period['volume'] += $is_fiat ? $trade['tradeAmount'] : $trade['tradeVolume'];
            $period['num_trades'] ++;
        }
        
        // generate intervals in gaps.
        // note:  this is a slow operation.  best not to use this option if possible.
        if( @$fillgaps ) {
            $secs = $this->interval_secs( $interval );
            if( $secs < 0 ) {
                throw new Exception( "invalid interval seconds $secs for interval $interval" );
            }

            $next = $datetime_from;
            $cnt = 0;
            $max = 50000;   // avoid breaking server.  ;-)
            while( $next < $datetime_to && $cnt++ < $max ) {
                $interval_start = $this->interval_start($next, $interval);

                $cur = @$intervals[$interval_start];
                if( !$cur ) {
                    if( $fillgaps === 'random' ) {
                        $volume = rand( 1, 100 );
                        $num_trades = rand( 0, 30 );
                    }
                    $cur = ['period_start' => $interval_start,
                            'volume' => @$volume ?: 0,
                            'num_trades' => @$num_trades ?: 0,
                          ];
                    $intervals[$interval_start] = $cur;
                }
                $next += $secs + 1;
            }
            ksort( $intervals );
        }
        
        
        if( @$fields || !@$integeramounts ) {
            foreach( $intervals as $k => &$period ) {
                
                if(!@$integeramounts ) {
                    $period['volume'] = btcutil::int_to_btc( $period['volume'] );
                }
                
                // convert to user specified field order list, if present.
                if( @$fields ) {
                    $p = [];
                    $t = $period;
                    foreach( $fields as $id =>  $f ) {
                        $old = is_string($id) ? $id : $f;
                        $new = $f;
                        $p[$new] = $period[$old];
                    }
                    $period = $p;
                }
            }
        }

        return array_values( $intervals );
    }
}
