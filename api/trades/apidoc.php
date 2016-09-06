<?php

class api_trades {
    
    static function get_description() {
        return "Provides list of completed trades for a single market.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => true, 'values' => '<market pair> | all', 'default' => null],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
                 ['param' => 'timestamp_from', 'desc' => 'start time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => '2016-01-01'],
                 ['param' => 'timestamp_to', 'desc' => 'end time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => 'now'],
                 ['param' => 'direction', 'desc' => 'trade direction: buy or sell. omit or leave null for both.', 'required' => false, 'values' => 'buy | sell', 'default' => null],
                 ['param' => 'limit', 'desc' => 'maximum trades to return.  max is 2000.', 'required' => false, 'values' => null, 'default' => 100],
                 ['param' => 'sort', 'desc' => 'Sort by date', 'required' => false, 'values' => 'asc | desc', 'default' => 'desc'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/trades?market=btc_eur&limit=2',
                          'response' => <<< END
[
    {
        "direction": "SELL",
        "price": "562.51610000",
        "amount": "0.30000000",
        "trade_id": "df7bd928-2940-4524-90cf-8dc5717fcad8",
        "trade_date": 1472947568822
    },
    {
        "direction": "SELL",
        "price": "552.48340000",
        "amount": "0.20000000",
        "trade_id": "e9b4f424-61b1-494a-bd1e-9142ce65b1d4",
        "trade_date": 1472937060362
    }
]
END
                        ];
                        
        return $examples;
    }
    
    static function get_notes() {
        return ["trade_date in response is provided in milliseconds since 1970, not seconds.  To get seconds, divide by 1000",
                'if the market parameter is "all" then up to limit trades will be returned, date sorted, across all markets. Also a "market" field is added to each trade result.'
                ];
    }
    
    static function get_seealso() {
        return [];
    }
}