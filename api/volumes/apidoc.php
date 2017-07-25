<?php

class api_volumes {
    
    static function get_description() {
        return "Provides periodic volume data in terms of base currency for one or all markets.";
    }
    
    static function get_params() {
        return [            
                 ['param' => 'basecurrency', 'desc' => 'base currency identifier.', 'optional' => true, 'values' => null, 'default' => null],
                 ['param' => 'market', 'desc' => 'market identifier.', 'optional' => true, 'values' => null, 'default' => null],
                 ['param' => 'interval', 'desc' => 'length of time blocks to summarize. auto will pick appropriate interval based on total time range', 'required' => false, 'values' => 'minute | half_hour | hour | half_day | day | week | month | year | auto', 'default' => 'auto'],
                 ['param' => 'timestamp_from', 'desc' => 'start time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => '2016-01-01'],
                 ['param' => 'timestamp_to', 'desc' => 'end time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => 'now'],
                 ['param' => 'format', 'desc' => 'format of return data. csv provides the most compact format.', 'required' => false, 'values' => 'csv | json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/volumes?basecurrency=BTC',
                          'response' => <<< END
[
    {
        "period_start": 1451606400,
        "volume": "1128.38570000",
        "num_trades": 2009
    },
    {
        "period_start": 1483228800,
        "volume": "1322.77960000",
        "num_trades": 2376
    }
]                          
END
                        ];
                        
        return $examples;
    }
    
    static function get_notes() {
        return ['A Base currency is a blockchain (eg Bitcoin) that is traded against other currencies utilizing the base currency\'s multisig capability.  eg XMR/BTC, LTC/BTC, BTC/USD, and BTC/EUR all use the base currency BTC.  In contrast, XMR/LTC, BTC/LTC, LTC/EUR, LTC/USD all use the base currency LTC.',
                'Either basecurrency or market param must be specified.',
                'basecurrency must be supported by Bisq app.',
                'As of 2017-07-24, supported basecurrency are BTC, DOGE, LTC, DASH',
                ];
    }
    
    static function get_seealso() {
        return [];
    }
}