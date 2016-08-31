<?php

class api_depth {
    
    static function get_description() {
        return "Provides list of open offers for a single orderbook.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => true, 'values' => null, 'default' => null],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/depth?market=xmr_btc',
                          'response' => <<< END
{
    "xmr_btc": {
        "buys": [
            "0.01432700",
            "0.01403166",
            "0.01403018",
        ],
        "sells": [
            "0.01523659",
            "0.01523956",
        ]
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