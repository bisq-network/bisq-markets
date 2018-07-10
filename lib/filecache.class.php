<?php

require_once( __DIR__ . '/settings.class.php' );
require_once( __DIR__ . '/strict_mode.funcs.php' );
require_once( __DIR__ . '/currencies.class.php' );

/* This class cache's a file's contents and will watch the file for changes.
 * If the file changes, the cache is invalidated and a callback function
 * will be called to reload the file.  The reloading function can perform
 * any processing/summarization it likes, and the returned value will be cached.
 *
 * The cache is implemented using apcu extension if available and static variables.
 *
 * It turns out that apcu is quite slow for storing large objects, but still faster
 * than re-reading from disk, especially if additional storage is involved.
 *
 * For this reason, faster static variables are used to cache data between calls during
 * the same request/process, and apcu is used for inter-request caching.
 */
class filecache {
    
    static public function get( $file, $key, $value_cb, $value_cb_params=[] ) {
        
        // We use opcache for inter-request cache, and static var to cache during same request/process.
        // todo: check if file has changed during same request/process. (lazy, eg after 2 secs)
        //       (checking mtime is slower and not really needed for use in a web app that is request oriented.)
        static $results = [];
        $fullkey = $file . $key;
        $val = @$results[$fullkey];
        if( $val ) {
            return $val;
        } 
       
        $result_key = $fullkey;
        $ts_key = $fullkey . '_timestamp';
        
        // Note: in older releases, we used apcu extension, but it turns out
        // that opcache is much much faster.  For example, the trades API
        // delivers approx 40 reqs/sec with apcu, and approx 1450 reqs/sec
        // with opcache.  It seems that apcu is deserializing data each
        // request, but opcache keeps it in parsed state in memory.
        // see: https://medium.com/@dylanwenzlau/500x-faster-caching-than-redis-memcache-apc-in-php-hhvm-dcd26e8447ad
        
        // in case opcache is not installed or not enabled.
        if( !extension_loaded( 'Zend OPcache' ) || !ini_get('opcache.enable')) {
            static $warned = false;
            if( !$warned ) {
                error_log( "Warning: opcache not found. Please install or enable opcache extension for better performance." );
                $warned = true;
            }
            $results[$fullkey] = $val = call_user_func_array( $value_cb, $value_cb_params );
            return $val;
        }

        // note: I experimented with storing timestamp and data in a
        // single key/val array, to perform 1 cache lookup instead of
        // 2, but there was no visible speedup.
        $cached_ts = self::opcache_get( $ts_key );
        
        if( $cached_ts && filemtime( $file ) < $cached_ts ) {
            $result = self::opcache_get( $result_key );
        }
        if(@$result === null) {
            $result = call_user_func_array( $value_cb, $value_cb_params );
            self::opcache_set( $ts_key, time() );
            self::opcache_set( $result_key, $result );
        }
        $results[$fullkey] = $result;
        return $result;
        
    }

    // store key/val in opcache.    
    static function opcache_set($key, $val) {
       $val = var_export($val, true);
       // HHVM fails at __set_state, so just use object cast for now
       $val = str_replace('stdClass::__set_state', '(object)', $val);
       // Write to temp file first to ensure atomicity
       $safekey = md5($key);
       $dir = '/tmp/bisq_opcache';
       @mkdir($dir);
       $tmp = "$dir/$safekey." . uniqid('', true) . '.tmp';
       file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX);
       rename($tmp, "$dir/$safekey");
    }
    
    // retrieve key from opcache.
    // note that first call to get will read file from disk, but
    // subsequent operations retrieve parsed object from ram.
    static function opcache_get($key) {
       $safekey = md5($key);
        @include "/tmp/bisq_opcache/$safekey";
        return isset($val) ? $val : false;
    }
}


