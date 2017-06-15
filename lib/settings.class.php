<?php

date_default_timezone_set ( 'UTC' );

class settings {

    static $settings = null;
    
    static public function get($key, $missing_ok = false) {
        if( !self::$settings ) {
            self::$settings = self::get_settings();
        }
        return $missing_ok ? @self::$settings[$key] : self::$settings[$key];
    }
    
    static public function set($key, $val) {
        // we call get method to ensure that settings file has been read, and we don't later overwrite the value.
        self::get($key, $missing_ok = true);
        self::$settings[$key] = $val;
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