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
        "open": "528.00000000",
        "high": "528.00000000",
        "low": "517.62850000",
        "close": "517.62850000",
        "volume_left": "1.09000000",
        "volume_right": "574.48280000",
        "buy": null,
        "sell": null
    }
}                          
END
                        ];

        $examples[] = 
                        [ 'request' => '/ticker',
                          'response' => <<< END

{
    "1cr_btc": null,
    ...
    "sc_btc": {
        "open": "0.00000077",
        "high": "0.00000077",
        "low": "0.00000075",
        "close": "0.00000075",
        "volume_left": "423666.82980000",
        "volume_right": "0.32000000",
        "buy": null,
        "sell": "0.00000092"
    },
    ...
    "xmr_btc": {
        "open": "0.01455763",
        "high": "0.01646004",
        "low": "0.01419052",
        "close": "0.01530002",
        "volume_left": "525.70260000",
        "volume_right": "7.82000000",
        "buy": "0.01320001",
        "sell": "0.01389993"
    }
}
END
                        ];

                        
        return $examples;
    }
    
    static function get_notes() {
        return [];
    }
    
    static function get_seealso() {
        return [];
    }
}