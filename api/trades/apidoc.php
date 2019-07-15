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
                 ['param' => 'trade_id_from', 'desc' => 'identifies first trade to include', 'required' => false, 'values' => null, 'default' => null],
                 ['param' => 'trade_id_to', 'desc' => 'identifies last trade to include', 'required' => false, 'values' => null, 'default' => null],
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
        "price": "2375.72500000",
        "amount": "0.05000000",
        "volume": "118.78620000",
        "payment_method": "SEPA",
        "trade_id": "BLBJHGL-b644851d-f822-418a-8035-955f7a02eff9-051",
        "trade_date": 1501095828824
    },
    {
        "direction": "SELL",
        "price": "2148.50230000",
        "amount": "0.20000000",
        "volume": "429.70040000",
        "payment_method": "SEPA",
        "trade_id": "dcwyx9-1a89dece-5039-4ff7-89c9-7ed52b84bc88-051",
        "trade_date": 1501088841011
    }
]
END
                        ];
                        
        return $examples;
    }
    
    static function get_notes() {
        return ["trade_date in response is provided in milliseconds since 1970, not seconds.  To get seconds, divide by 1000",
                'if the market parameter is "all" then up to limit trades will be returned, date sorted, across all markets. Also a "market" field is added to each trade result.',
                'this api will return a maximum of 2000 trades per call',
                'if trade_id_from or trade_id_to is used and a matching trade is not found for each, then no results are returned',
                'The Bisq app does not presently use a trade_id internally.  The trade_id in this api is actually the bisq offerId.',
                'Possible values for payment_method can change over time.  For most current, see [PaymentMethod.java](https://github.com/bisq-network/bisq/core/src/main/java/bisq/core/payment/payload/PaymentMethod.java)',
                ];
    }
    
    static function get_seealso() {
        return [];
    }
}
