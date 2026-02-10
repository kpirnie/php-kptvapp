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
if (! trait_exists('CacheMemcachedAsync')) {

    /**
     * KPT Cache Memcached Async Trait
     *
     * Provides asynchronous Memcached caching operations for improved performance
     * in I/O-intensive applications using event loops and promises.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheMemcachedAsync
    {
        /**
         * Async get from Memcached
         *
         * Asynchronously retrieves an item from Memcached using promises
         * and event loop integration for non-blocking operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The cache key to retrieve
         * @return CachePromise Returns a promise that resolves with the cached data
         */
        public static function getFromMemcachedAsync(string $key): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $resolve, $reject) {

                        // try to get the item from Memcached
                        try {
                            // get the result and resolve
                            $result = self::getFromMemcached($key);
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
                        $result = self::getFromMemcached($key);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async set to Memcached
         *
         * Asynchronously stores an item in Memcached using promises
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
        public static function setToMemcachedAsync(string $key, mixed $data, int $ttl): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($key, $data, $ttl) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($key, $data, $ttl, $resolve, $reject) {

                        // try to set the item to Memcached
                        try {
                            // set the result and resolve
                            $result = self::setToMemcached($key, $data, $ttl);
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
                        $result = self::setToMemcached($key, $data, $ttl);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async multi-get from Memcached
         *
         * Asynchronously retrieves multiple items from Memcached using promises
         * for improved performance when fetching multiple cache keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $keys Array of cache keys to retrieve
         * @return CachePromise Returns a promise that resolves with key-value pairs
         */
        public static function memcachedMultiGetAsync(array $keys): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($keys) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($keys, $resolve, $reject) {

                        // try to get multiple items from Memcached
                        try {
                            // get the result and resolve
                            $result = self::memcachedMultiGet($keys);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to get multiple items synchronously
                    try {
                        // get the result and resolve
                        $result = self::memcachedMultiGet($keys);
                        $resolve($result);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Async multi-set to Memcached
         *
         * Asynchronously stores multiple items in Memcached using promises
         * for improved performance when setting multiple cache keys.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $items Array of key-value pairs to store
         * @param int $ttl Time to live in seconds
         * @return CachePromise Returns a promise that resolves with success status
         */
        public static function memcachedMultiSetAsync(array $items, int $ttl = 3600): CachePromise
        {

            // return a new promise for the async operation
            return new CachePromise(function ($resolve, $reject) use ($items, $ttl) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // schedule the operation on the next tick
                    self::$_event_loop -> futureTick(function () use ($items, $ttl, $resolve, $reject) {

                        // try to set multiple items to Memcached
                        try {
                            // set the result and resolve
                            $result = self::memcachedMultiSet($items, $ttl);
                            $resolve($result);

                        // whoopsie... reject the promise with the error
                        } catch (\Exception $e) {
                            $reject($e);
                        }
                    });

                // fallback to synchronous operation
                } else {
                    // try to set multiple items synchronously
                    try {
                        // set the result and resolve
                        $result = self::memcachedMultiSet($items, $ttl);
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
