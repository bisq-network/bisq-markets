<?php

class api_currencies {
    
    static function get_description() {
        return "Provides list of available currencies for a given base currency.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'basecurrency', 'desc' => 'base currency identifier', 'required' => false, 'values' => null, 'default' => 'BTC'],
                 ['param' => 'type', 'desc' => 'type of currencies to include in results', 'required' => false, 'values' => 'crypto | fiat | all', 'default' => 'all'],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/currencies',
                          'response' => <<< END
{
    "AED": {
        "code": "AED",
        "name": "United Arab Emirates Dirham",
        "precision": 8,
        "type": "fiat"
    },
    "AIB": {
        "code": "AIB",
        "name": "Advanced Internet Blocks",
        "precision": 8,
        "type": "crypto"
    },
    ...
}
END
                        ];
                        
        return $examples;
    }
    
    static function get_notes() {
        return ['In practice, the same currencies are available for every basecurrency so this API can be called just once, omitting the basecurrency parameter.'];
    }
    
    static function get_seealso() {
        return [];
    }
}