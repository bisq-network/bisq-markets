<?php

// a utility class for btc and fiat conversions.
class btcutil {

    // converts btc decimal amount to integer amount.
    static public function btc_to_int( $val ) {
        return ((int)(($val * 100000000)*10))/10;
    }

    // converts btc integer amount to decimal amount with full precision.
    static public function int_to_btc( $val ) {
        return $val / 100000000;
    }

    // formats btc integer amount for display as decimal amount (rounded)   
    static public function btc_display( $val, $omit_zero = false ) {
        if( !$val && $omit_zero ) {
            return null;
        }
        return number_format( round($val / SATOSHI,8), 8, '.', '');
    }

    // formats usd integer amount for display as decimal amount (rounded)
    static public function fiat_display( $val, $omit_zero = false ) {
        if( !$val && $omit_zero ) {
            return null;
        }
        return number_format( round($val / 100,3), 2, '.', '');
    }

    // converts fiat decimal amount to integer amount.
    static public function fiat_to_int( $val ) {
        return ((int)(($val * 100)*10))/10;
    }

    // converts btc integer amount to decimal amount with full precision.
    static public function btcint_to_fiatint( $val ) {
        return round($val / 100000000, 0);
    }
    
    // converts integer amount to money amount
    static public function int_to_money4( $val ) {
        $denom = pow(10, 4);
        return $val / $denom;
    }
    

}
                                                                  
                                                                           
                                                                           