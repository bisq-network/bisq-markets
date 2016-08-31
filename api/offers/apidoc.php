<?php

class api_offers {
    
    static function get_description() {
        return "Provides list of open offer details for a single market.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => true, 'values' => null, 'default' => null],
                 ['param' => 'direction', 'desc' => 'offer direction, omit or set null for both', 'required' => false, 'values' => 'BUY | SELL', 'default' => 'null'],
                 ['param' => 'format', 'desc' => 'format of return data', 'required' => false, 'values' => 'json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/offers?market=xmr_btc',
                          'response' => <<< END

{
    "xmr_btc": {
        "buys": [
            {
                "offer_id": "b2ab53d4-8ffd-4138-aa8a-c7143d7fb123_0.4.9.4",
                "offer_date": 1472239477119,
                "direction": "BUY",
                "min_amount": "0.10000000",
                "amount": "6.77840000",
                "price": "0.01475266",
                "volume": "0.10000000",
                "payment_method": "BLOCK_CHAINS",
                "offer_fee_txid": "64ae7b5863c509ed0cb5ef9ec3ec4a197b4f77705fc7b2d3b82fb96bc3f3e872"
            },
            ...
        ],
        "sells": [
            {
                "offer_id": "6a31771b-3c1b-49f4-b403-12f8749646fa_0.4.9.4",
                "offer_date": 1472570540023,
                "direction": "SELL",
                "min_amount": "0.40000000",
                "amount": "25.54280000",
                "price": "0.01565994",
                "volume": "0.40000000",
                "payment_method": "BLOCK_CHAINS",
                "offer_fee_txid": "ee0bd1d55ba37bf14de59fd6a42263200b6485d2d5aff493ffeb82513c0b2637"
            },
            ...
        ]
    }
}
END
                        ];


        $examples[] = 
                        [ 'request' => '/offers?market=xmr_btc&direction=BUY',
                          'response' => <<< END
{
    "xmr_btc": {
        "buys": [
            {
                "offer_id": "b2ab53d4-8ffd-4138-aa8a-c7143d7fb123_0.4.9.4",
                "offer_date": 1472239477119,
                "direction": "BUY",
                "min_amount": "0.10000000",
                "amount": "6.68520000",
                "price": "0.01495824",
                "volume": "0.10000000",
                "payment_method": "BLOCK_CHAINS"
                "offer_fee_txid": "64ae7b5863c509ed0cb5ef9ec3ec4a197b4f77705fc7b2d3b82fb96bc3f3e872"
            },
            ...
        ],
        "sells": null
    }
}                          
END
                        ];

                        
        return $examples;
    }
    
    static function get_notes() {
       // Note: these are found at:
       //  https://github.com/bitsquare/bitsquare/blob/master/core/src/main/java/io/bitsquare/payment/PaymentMethod.java
       $payment_methods = [ 
                            "OK_PAY",
                            "PERFECT_MONEY",
                            "SEPA",
                            "NATIONAL_BANK",
                            "SAME_BANK",
                            "SPECIFIC_BANKS",
                            "SWISH",
                            "ALI_PAY",
                            "CLEAR_X_CHANGE",
                            "US_POSTAL_MONEY_ORDER",
                            "CASH_DEPOSIT",
                            "BLOCK_CHAINS",
                         ];
        
        return ['payment_method in response will be one of: ' . implode( ', ', $payment_methods ),
                'payment_method values are defined in this file: https://github.com/bitsquare/bitsquare/blob/master/core/src/main/java/io/bitsquare/payment/PaymentMethod.java',
                'offer_fee_txid in response is the offer fee transaction ID in the bitcoin block chain',
                'offer_date in response is provided in milliseconds since 1970, not seconds. To get seconds, divide by 1000',
               ];
    }
    
    static function get_seealso() {
        return ['depth'];
    }
}