<?php

class api_trades {
    
    static function get_description() {
        return "Provides list of completed trades for a single market.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => true, 'values' => null, 'default' => null],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
                 ['param' => 'timestamp_from', 'desc' => 'start time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => '2016-01-01'],
                 ['param' => 'timestamp_to', 'desc' => 'end time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => 'now'],
                 ['param' => 'direction', 'desc' => 'trade direction: buy or sell. omit or leave null for both.', 'required' => false, 'values' => 'buy | sell', 'default' => null],
                 ['param' => 'limit', 'desc' => 'maximum trades to return.  max is 2000.', 'required' => false, 'values' => null, 'default' => 100],
                 ['param' => 'sort', 'desc' => 'Sort by date', 'required' => false, 'values' => ['asc', 'desc'], 'default' => 'desc'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/trades?market=btc_eur&limit=2',
                          'response' => <<< END
[
    {
        "direction": "BUY",
        "price": 51762850000,
        "amount": 10000000,
        "trade_id": "d4ec1973-0105-4898-b7b7-fb5a5e802545",
        "trade_date": 1472550676861
    },
    {
        "direction": "SELL",
        "price": 52800000000,
        "amount": 99000000,
        "trade_id": "43f11983-3d93-4a42-ac3d-c199ac816883",
        "trade_date": 1472540631366
    }
]
END
                        ];
                        
        return $examples;
    }
    
    static function get_notes() {
        return ["trade_date in response is provided in milliseconds since 1970, not seconds.  To get seconds, divide by 1000"];
    }
    
    static function get_seealso() {
        return [];
    }
}