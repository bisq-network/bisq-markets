<?php

class api_ticker {
    
    static function get_description() {
        return "Provides 24 hour price ticker for single market or all markets";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => false, 'values' => null, 'default' => null],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/ticker?market=btc_eur',
                          'response' => <<< END
{
    "btc_eur": {
        "last": "524.17550000",
        "high": "529.56210000",
        "low": "510.20000000",
        "volume_left": "2.76560000",
        "volume_right": "1454.42950000",
        "buy": "513.88580000",
        "sell": "529.17120000"
    }
}                          
END
                        ];

        $examples[] = 
                        [ 'request' => '/ticker',
                          'response' => <<< END

{
    "1cr_btc": null,
    "btc_aud": {
        "last": "728.89000000",
        "high": "728.89000000",
        "low": "728.89000000",
        "volume_left": 0,
        "volume_right": 0,
        "buy": null,
        "sell": null
    },
    "btc_eur": {
        "last": "524.17550000",
        "high": "529.56210000",
        "low": "510.20000000",
        "volume_left": "2.76560000",
        "volume_right": "1454.42950000",
        "buy": "513.88580000",
        "sell": "529.17120000"
    },
    "xmr_btc": {
        "last": "0.01437401",
        "high": "0.01530723",
        "low": "0.01437401",
        "volume_left": "269.79720000",
        "volume_right": "4.00000000",
        "buy": "0.01427006",
        "sell": "0.01545144"
    }
}
END
                        ];

                        
        return $examples;
    }
    
    static function get_notes() {
        return ["the response data can indicate the following market states: never traded, traded prior to 24 hours, traded within 24 hours",
                "a null value for a market key indicates 'never traded'",
                "volume_left or volume_right is 0 indicates 'traded prior to 24 hours'",
                "volume_left or volume_right is > 0 indicates  'traded within 24 hours'",
                "buy represents the highest buy offer at present, sell represents the lowest sell offer at present",
                "either buy or sell may be null if there are not presently any respective offers"
                ];
    }
    
    static function get_seealso() {
        return [];
    }
}