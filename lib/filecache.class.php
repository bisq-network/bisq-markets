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
        
        // It turns out that apcu_fetch/entry is quite slow for large objects.
        // So we use apcu for inter-request cache, and static var to cache during same request/process.
        // todo: check if file has changed during same request/process. (lazy, eg after 2 secs)
        //       (checking mtime is slower and not really needed for use in a web app that is request oriented.)
        static $results = null;
        $val = @$results[$key];
        if( $val ) {
            return $val;
        }        

        // in case apcu is not installed.
        if( !function_exists( 'apcu_fetch' ) ) {
            static $warned = false;
            if( !$warned ) {
                error_log( "Warning: APCu not found. Please install APCu extension for better performance." );
                $warned = true;
            }
            $results[$key] = $val = call_user_func_array( $value_cb, $value_cb_params );
            return $val;
        }
        
        $result_key = $key;
        $ts_key = 'all_trades_timestamp';

        // We prefer to use apcu_entry if existing, because it is atomic.        
        if( function_exists( 'apcu_entry' ) ) {
            // note:  this case is untested!!!  my version of apcu is too old.
            $cached_ts = apcu_entry( $ts_key, function($key) { return time(); } );
            
            // invalidate cache if file on disk is newer than cached value.
            if( filemtime( $file ) > $cached_ts ) {
                apcu_delete( $result_key );
            }
            $result = apcu_entry( $result_key, function($key) use($file) {
                return call_user_func_array( $value_cb, $value_cb_params );
            });
            $results[$key] = $result;
            return $result;
        }
        
        // Otherwise, use apcu_fetch, apcu_store.
        $cached_ts = apcu_fetch( $ts_key );
        $cached_result = apcu_fetch( $result_key );
        
        if( $cached_result && $cached_ts && filemtime( $file ) < $cached_ts ) {
            $result = $cached_result;
        }
        else {
            $result = call_user_func_array( $value_cb, $value_cb_params );
            apcu_store( $ts_key, time() );
            apcu_store( $result_key, $result );
        }
        $results[$key] = $result;
        return $result;
    }
}
