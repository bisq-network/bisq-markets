<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/trades.class.php' );
require_once( __DIR__ . '/btcutil.class.php' );

class summarize_trades {
    private $ts_multiplier = 1;  // use seconds
    
    private function summarize( $trades ) {
    }

    public function get_trade_summaries_minutes( $criteria ) {
        
        // align to start of minute
        $criteria['interval'] = 'minute';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_10_minutes( $criteria ) {
        
        // align to start of 10 minutes
        $criteria['interval'] = '10_minute';
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_half_hours( $criteria ) {
        
        // align to start of half hour
        $criteria['interval'] = 'half_hour';
        
        return $this->get_trade_summaries( $criteria );
    }
    
    public function get_trade_summaries_hours( $criteria ) {
        
        // align to start of hour
        $criteria['interval'] = 'hour';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_half_days( $criteria ) {
        
        // align to start of day
        $criteria['interval'] = 'half_day';
        
        return $this->get_trade_summaries( $criteria );
    }    
    
    public function get_trade_summaries_days( $criteria ) {
        
        // align to start of day
        $criteria['interval'] = 'day';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_weeks( $criteria ) {
        
        // align to start of first day of week.
        $criteria['interval'] = 'week';
        
        return $this->get_trade_summaries( $criteria );
    }

    public function get_trade_summaries_months( $criteria ) {
        
        // align to start of first day of month.
        $criteria['interval'] = 'month';
        
        return $this->get_trade_summaries( $criteria );
    }
    
    public function get_trade_summaries_years( $criteria ) {
        
        // align to start of first day of year.
        $criteria['interval'] = 'year';
        
        return $this->get_trade_summaries( $criteria );
    }
    
    
    /**
     * criteria keys:
     *  + market: eg 'dash_btc', or 'all'. required.
     *  + interval.  required. in seconds.
     *  + datetime_from: timestamp utc. required.
     *  + datetime_to: timestamp utc.  required.
     *  + direction: buy, sell
     *  + integeramounts: bool.  default = true.
     *  + fillgaps: bool.  default = false.
     *  + fields: array -- fields to return.
     *      available:  "period_start", "open", "close",
     *                  "high", "low", "avg", "volume"
     */
    public function get_trade_summaries( $criteria ) {
        extract( $criteria );
        $tradesobj = new trades();
        unset( $criteria['fields'] );
        unset( $criteria['limit'] );
        $criteria['sort'] = 'asc';
        $criteria['integeramounts'] = true;
        $integeramounts = isset($integeramounts) ? $integeramounts : true;

        $trades = $tradesobj->get_trades( $criteria );
        
        $intervals = [];
        
        foreach( $trades as $trade ) {
            $traded_at = $trade['tradeDate'] / 1000;
            $interval_start = $this->interval_start($traded_at, $interval)*$this->ts_multiplier;

            if( !isset($intervals[$interval_start]) ) {
                $intervals[$interval_start] = ['open' => 0,
                                               'close' => 0,
                                               'high' => 0,
                                               'low' => 0,
                                               'avg' => 0,
                                               'volume_right' => 0,
                                               'volume_left' => 0,
                                             ];
                $intervals_prices[$interval_start] = [];
            }
            $period =& $intervals[$interval_start];
            $price = $trade['tradePrice'];
            $direction = $trade['direction'];
            $intervals_prices[$interval_start]['leftvol'][] = $trade['tradeAmount'];
            $intervals_prices[$interval_start]['rightvol'][] = $trade['tradeVolume'];
            
            if( $price ) {
                $plow = $period['low'];
                $period['period_start'] = $interval_start;
                $period['open'] = @$period['open'] ?: $price;
                $period['close'] = $price;
                $period['high'] = $price > $period['high'] ? $price : $period['high'];
                $period['low'] = ($plow && $price > $plow) ? $period['low'] : $price;
//                print_r($intervals_prices[$interval_start]);
//                var_dump( array_sum($intervals_prices[$interval_start]['volume_left']), array_sum($intervals_prices[$interval_start]['price']) ); echo "\n----\n";
                $period['avg'] = array_sum($intervals_prices[$interval_start]['rightvol']) / array_sum($intervals_prices[$interval_start]['leftvol']) * 100000000;
                $period['volume_left'] += $trade['tradeAmount'];
                $period['volume_right'] += $trade['tradeVolume'];
            }            
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
                $interval_start = $this->interval_start($next, $interval)*$this->ts_multiplier;

                $cur = @$intervals[$interval_start];
                if( !$cur ) {
                    if( $fillgaps === 'random' ) {
                        $open = @$prev_close ?: 50;
                        $close = rand( $open - $open / 2, $open + $open / 2);
                        $high = rand( $open, $open + $open / 3);
                        $low = rand( $open - $open / 3, $open );
                        $avg = rand( $low, $high );
                        $volume = rand( 1, 100 );
                        $amount = rand( 1, 100 );
                        $prev_close = $close;
                    }
                    $cur = ['period_start' => $interval_start,
                            'open' => @$open,
                            'close' => @$close,
                            'high' => @$high,
                            'low' => @$low,
                            'avg' => @$avg,
                            'volume_right' => @$volume,
                            'volume_left' => @$amount,
                          ];
                    $intervals[$interval_start] = $cur;
                }
                else {
                    $prev_close = $cur['close'];
                }
                $next += $secs + 1;
            }
            ksort( $intervals );
        }
        
        
        if( @$fields || !@$integeramounts ) {
            foreach( $intervals as $k => &$period ) {
                
                if(!@$integeramounts ) {
                    $period['open'] = btcutil::int_to_btc( $period['open'] );
                    $period['close'] = btcutil::int_to_btc( $period['close'] );
                    $period['high'] = btcutil::int_to_btc( $period['high'] );
                    $period['low'] = btcutil::int_to_btc( $period['low'] );
                    $period['avg'] = btcutil::int_to_btc( $period['avg'] );
                    $period['volume_right'] = btcutil::int_to_btc( $period['volume_right'] );
                    $period['volume_left'] = btcutil::int_to_btc( $period['volume_left'] );
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
    
    private function interval_start( $ts, $interval ) {
        switch( $interval ) {
            case 'minute':
                return (int)($ts - ($ts % 60));
            case '10_minute':
                return (int)($ts - ($ts % 600));
            case 'half_hour':
                return (int)($ts - ($ts % 1800));
            case 'hour':
                return (int)($ts - ($ts % 3600));
            case 'half_day':
                return (int)($ts - ($ts % (3600*12)));
            case 'day':
                return strtotime( 'midnight today', $ts);
            case 'week':
                return strtotime( "midnight sunday last week", $ts );
            case 'month':
                return strtotime( "midnight first day of this month", $ts );
            case 'year':
                return strtotime( "midnight first day of january", $ts );
            default:
                throw new exception( "Unsupported interval" );
        }
    }

    private function interval_end( $ts, $interval ) {
        switch( $interval ) {
            case '10_minute':
                return ($this->interval_start($ts, $interval) + 600 -1);
            case 'half_day':
                return ($this->interval_start($ts, $interval) + 86400/2 -1);
            case 'half_hour':
                return ($this->interval_start($ts, $interval) + 3600/2 -1);
            default:
                return (strtotime("+1 $interval", $ts) -1);
        }
    }
    
    public function interval_secs( $interval ) {
        $start = $this->interval_start( time(), $interval );
        return $this->interval_end( $start, $interval ) - $start;
    }
    
    
}
