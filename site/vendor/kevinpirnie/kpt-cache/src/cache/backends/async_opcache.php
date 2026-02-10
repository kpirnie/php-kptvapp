<?php

/**
 * Async Cache Traits for I/O-intensive cache backends
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't already exist
if (! trait_exists('CacheOPCacheAsync')) {

    /**
     * KPT Cache OPCache Async Trait
     *
     * Provides asynchronous OPCache operations for improved performance
     * in I/O-intensive applications using event loops and promises.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheOPCacheAsync
    {
        /**
         * Async get from OPCache
         *
         * Asynchronously retrieves an item from OPCache using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getFromOPcacheAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to get the item from OPCache
                        try {
                            // get the result and resolve
                            $result = self::getFromOPcache($key);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to get the item synchronously
                    try {
                        // get the result and resolve
                        $result = self::getFromOPcache($key);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async set to OPCache
         *
         * Asynchronously stores an item in OPCache using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to store
         * @param mixed $data The data to cache
         * @param int $ttl Time to live in seconds
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function setToOPcacheAsync(string $key, mixed $data, int $ttl): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $resolve, $reject) {

                        // try to set the item to OPCache
                        try {
                            // set the result and resolve
                            $result = self::setToOPcache($key, $data, $ttl);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to set the item synchronously
                    try {
                        // set the result and resolve
                        $result = self::setToOPcache($key, $data, $ttl);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async cleanup OPCache files
         *
         * Asynchronously cleans up OPCache files to maintain optimal
         * performance and free up disk space.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return CachePromise Returns a promise that resolves with cleanup status
         */
        public static function cleanupOPcacheFilesAsync(): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($resolve, $reject) {

                        // try to cleanup OPCache files
                        try {
                            // cleanup OPCache files and resolve
                            $result = self::cleanupOPcacheFiles();
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to cleanup OPCache files synchronously
                    try {
                        // cleanup OPCache files and resolve
                        $result = self::cleanupOPcacheFiles();
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
