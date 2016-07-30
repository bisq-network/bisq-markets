<?php

date_default_timezone_set ( 'UTC' );

class settings {
    
    static public function get($key) {
        static $settings = null;
        if( !$settings ) {
            $settings = self::get_settings();
        }
        return $settings[$key];
    }
    
    static private function get_settings() {
        
        $path = realpath( __DIR__ . '/../' ) . '/settings.json';
        
        if( !file_exists( $path ) ) {
            $msg = "Settings file does not exist in at $path. Please create it.\n";
            echo $msg;
            throw new Exception( $msg );
        }
        $data = json_decode( file_get_contents($path ), true);
        if( !$data ) {
            throw new Exception( "Error parsing json in $path." );
        }
        return $data;
    }
}