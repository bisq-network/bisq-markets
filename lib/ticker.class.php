<?php

require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/summarize_trades.class.php' );
require_once( __DIR__ . '/markets.class.php' );
require_once( __DIR__ . '/offers.class.php' );
require_once( __DIR__ . '/trades.class.php' );

class ticker {
    
    public function get_market_ticker( $market, $criteria ) {
        
        $criteria['market'] = $market;
        $criteria['fields'] = ['close' => 'last', 'high','low','volume_left','volume_right'];

        $summarizer = new summarize_trades();
        
        // ensure we will only have a single period result row from summarize_trades.
        $timerange = $criteria['datetime_to'] - $criteria['datetime_from'];
        $interval_secs = $summarizer->interval_secs( $criteria['interval'] ) + 1;
        if( $timerange > $interval_secs ) {
            throw new Exception( "time range ($timerange) is greater than interval period ($interval_secs)" );
        }
        $periods = $summarizer->get_trade_summaries($criteria);

        $ticker = @$periods[0];
        
        if( !$ticker ) {
            $last = $this->get_market_last( $market );
            if( !$last ) {
                return null;
            }
            if( !@$criteria['integeramounts'] ) {
                $last = btcutil::int_to_btc( $last );
            }
            
            $ticker = ['last' => $last,
                       'high' => $last,
                       'low' => $last,
                       'volume_left' => 0,
                       'volume_right' => 0,
                      ];
        }

        $offers = new offers();
        
        $criteria = ['market' => $market,
                     'datetime_from' => $criteria['datetime_from'],
                     'datetime_to' => $criteria['datetime_to'],
                     'integeramounts' => $criteria['integeramounts'],
                     'direction' => 'BUY',
                     'sort' => 'asc',
                     'limit' => 1,
                    ];
        $buys = $offers->get_offers( $criteria );

        $criteria['direction'] = 'SELL';
        $criteria['sort'] = 'asc';
        $sells = $offers->get_offers( $criteria );
        
        $ticker['buy'] = @$buys[0]['price'];
        $ticker['sell'] = @$sells[0]['price'];

        return $ticker;
    }
    
    public function get_market_last( $market ) {
        $trades = new trades();
        $trade = $trades->get_last_trade_by_market( $market );
        return @$trade['tradePrice'];
    }
    
    public function get_all_markets_ticker( $criteria ) {
        $markets = new markets();

        $result = [];
        foreach( $markets->get_markets() as $market ) {
            $pair = $market['pair'];
            $result[$pair] = $this->get_market_ticker( $pair, $criteria);
        }
        
        return $result;
    }

}