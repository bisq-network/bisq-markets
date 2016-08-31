<?php

class api_markets {
    
    static function get_description() {
        return "Provides list of available markets.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/markets',
                          'response' => <<< END

{
    "1cr_btc": {
        "pair": "1cr_btc",
        "lname": "1CRedit",
        "rname": "Bitcoin",
        "lsymbol": "1CR",
        "rsymbol": "BTC",
        "lprecision": 8,
        "rprecision": 8,
        "ltype": "crypto",
        "rtype": "crypto",
        "name": "1CRedit\/Bitcoin"
    },
    "btc_aud": {
        "pair": "btc_aud",
        "lname": "Bitcoin",
        "rname": "Australian Dollar",
        "lsymbol": "BTC",
        "rsymbol": "AUD",
        "lprecision": 8,
        "rprecision": 2,
        "ltype": "crypto",
        "rtype": "fiat",
        "name": "Bitcoin\/Australian Dollar"
    },    
    ...
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