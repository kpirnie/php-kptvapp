<?php

/**
 * KPT Cache - YAC Caching Trait
 * Yet Another Cache (YAC) extension implementation
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheYAC')) {

    /**
     * KPT Cache YAC Trait
     *
     * Provides Yet Another Cache (YAC) extension functionality for
     * high-performance shared memory caching between PHP processes.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheYAC
    {
        /**
         * Test if YAC cache is actually working
         *
         * Performs a comprehensive test of YAC functionality to ensure
         * the extension is loaded and working properly.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if YAC test passes, false otherwise
         */
        private static function testYacConnection(): bool
        {

            // try to test yac functionality
            try {
                // get yac configuration
                $config = CacheConfig::get('yac');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Test with a simple store/fetch operation
                $test_key = $prefix . 'test_' . uniqid();
                $test_value = 'test_value_' . time();

                // Try to store and retrieve
                if (yac_add($test_key, $test_value, 60)) {
                    // get the stored value
                    $retrieved = yac_get($test_key);

                    // clean up the test key
                    yac_delete($test_key);

                    // return comparison result
                    return $retrieved === $test_value;
                }

                // failed to store
                return false;

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "YAC test failed: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Get item from YAC cache
         *
         * Retrieves a cached item from YAC shared memory cache
         * with proper key prefixing and error handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return mixed Returns the cached data or false if not found
         */
        private static function getFromYac(string $key): mixed
        {

            // If YAC is not loaded, just return false
            if (! extension_loaded('yac')) {
                return false;
            }

            // try to get item from yac
            try {
                // get yac configuration
                $config = CacheConfig::get('yac');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // Setup the prefixed key
                $prefixed_key = $prefix . $key;

                // Fetch the value
                $value = yac_get($prefixed_key);

                // YAC returns false for non-existent keys
                return $value !== false ? $value : false;

            // whoopsie... setup the error
            } catch (\Exception $e) {
                self::$_last_error = "YAC get error: " . $e -> getMessage();
            }

            // return false if not found or error
            return false;
        }

        /**
         * Set item to YAC cache
         *
         * Stores an item in YAC shared memory cache with proper
         * key prefixing and TTL support.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return bool Returns true if successful, false otherwise
         */
        private static function setToYac(string $key, mixed $data, int $ttl): bool
        {

            // check if yac extension is loaded
            if (! extension_loaded('yac')) {
                return false;
            }

            // try to set item to yac
            try {
                // get yac configuration
                $config = CacheConfig::get('yac');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // setup prefixed key and store the item
                $prefixed_key = $prefix . $key;
                return yac_set($prefixed_key, $data, $ttl);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "YAC set error: " . $e -> getMessage();
                return false;
            }
        }

        /**
         * Delete item from YAC cache
         *
         * Removes a cached item from YAC shared memory cache
         * with proper key prefixing and error handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to delete
         * @return bool Returns true if successful, false otherwise
         */
        private static function deleteFromYac(string $key): bool
        {

            // if the extension isn't loaded, just return true
            if (! extension_loaded('yac')) {
                return true;
            }

            // try to delete the item
            try {
                // get yac configuration
                $config = CacheConfig::get('yac');
                $prefix = $config['prefix'] ?? CacheConfig::getGlobalPrefix();

                // setup prefixed key
                $prefixed_key = $prefix . $key;

                // debug logging
                Logger::debug('Delete from YAC', ['key' => $prefixed_key]);

                // return deleting the item
                return yac_delete($prefixed_key);

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                // log the error
                Logger::error("YAC delete error", ['error' => $e -> getMessage()]);
                self::$_last_error = "YAC delete error: " . $e -> getMessage();
            }

            // default return
            return false;
        }

        /**
         * Clear all items from YAC cache
         *
         * Flushes all cached items from YAC shared memory cache.
         * This operation affects all cached data in the YAC instance.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return bool Returns true if successful, false otherwise
         */
        public static function clearYac(): bool
        {

            // check if yac extension is loaded and flush if available
            if (! extension_loaded('yac')) {
                return false;
            }

            // try to flush all yac cache
            try {
                // debug logging
                Logger::debug('Clearing YAC cache');

                // return flushing the cache
                return yac_flush();

            // whoopsie... setup the error and return false
            } catch (\Exception $e) {
                self::$_last_error = "YAC clear error: " . $e -> getMessage();
                Logger::error("YAC clear error", ['error' => $e -> getMessage()]);
                return false;
            }
        }

        /**
         * Cleanup expired items from YAC cache
         *
         * YAC handles expiration automatically and there's no way to
         * iterate through keys or force cleanup. YAC cleans expired
         * items on access automatically.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return int Returns the number of items cleaned (always 0 for YAC)
         */
        private static function cleanupYac(): int
        {

            // setup the count
            $count = 0;

            // YAC handles expiration automatically
            // There's no way to iterate through keys or force cleanup
            // YAC cleans expired items on access

            // debug logging
            Logger::debug('YAC cleanup called - automatic cleanup handled by YAC');

            // return the count
            return $count;
        }
    }
}
