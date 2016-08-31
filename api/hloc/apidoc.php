<?php

class api_hloc {
    
    static function get_description() {
        return "Provides hi/low/open/close data for a given market.  This can be used to generate a candlestick chart.";
    }
    
    static function get_params() {
        return [
                 ['param' => 'market', 'desc' => 'market identifier', 'required' => true, 'values' => null, 'default' => null],
                 ['param' => 'interval', 'desc' => 'length of time blocks to summarize. auto will pick appropriate interval based on total time range', 'required' => false, 'values' => 'minute | half_hour | hour | half_day | day | week | month | year | auto', 'default' => 'auto'],
                 ['param' => 'timestamp_from', 'desc' => 'start time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => '2016-01-01'],
                 ['param' => 'timestamp_to', 'desc' => 'end time, in seconds since 1970', 'required' => false, 'values' => null, 'default' => 'now'],
                 ['param' => 'format', 'desc' => 'format of return data. csv provides the most compact format.', 'required' => false, 'values' => 'csv | json | jsonpretty', 'default' => 'jsonpretty'],
               ];
    }

    static function get_examples() {
        $examples = [];
        $examples[] = 
                        [ 'request' => '/hloc?market=xmr_btc',
                          'response' => <<< END
[
    {
        "period_start": 1463875200,
        "open": "0.00198039",
        "high": "0.00198039",
        "low": "0.00180809",
        "close": "0.00180809",
        "volume_left": "528.73200000",
        "volume_right": "1.04300000",
        "avg": "0.00197264"
    },
    ...
    {
        "period_start": 1472342400,
        "open": "0.00982318",
        "high": "0.01911520",
        "low": "0.00982318",
        "close": "0.01530002",
        "volume_left": "3412.86880000",
        "volume_right": "47.21000000",
        "avg": "0.01383294"
    }
]                          
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