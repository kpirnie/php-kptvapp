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
if (! trait_exists('CacheMixedAsync')) {

    /**
     * KPT Cache Mixed Async Trait
     *
     * Provides asynchronous multi-tier cache operations for improved performance
     * across different cache backends using event loops and promises.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait CacheMixedAsync
    {
        /**
         * Async multi-tier operation with different backends
         *
         * Performs multiple cache operations across different tiers asynchronously
         * for optimal performance when working with mixed cache backends.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $operations Array of operations to perform across different tiers
         * @return CachePromise Returns a promise that resolves with operation results
         */
        public static function multiTierOperationAsync(array $operations): CachePromise
        {

            // return a new promise for the multi-tier operation
            return new CachePromise(function ($resolve, $reject) use ($operations) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // setup promises array
                    $promises = [ ];

                    // loop through each operation
                    foreach ($operations as $op) {
                        // get operation details
                        $tier = $op['tier'];
                        $method = $op['method'];
                        $key = $op['key'];

                        // match the tier and method to create appropriate promise
                        $promise = match ([$tier, $method]) {
                            [self::TIER_MEMCACHED, 'get'] => self::getFromMemcachedAsync($key),
                            [self::TIER_MEMCACHED, 'set'] => self::setToMemcachedAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_FILE, 'get'] => self::getFromFileAsync($key),
                            [self::TIER_FILE, 'set'] => self::setToFileAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_MMAP, 'get'] => self::getFromMmapAsync($key),
                            [self::TIER_MMAP, 'set'] => self::setToMmapAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            [self::TIER_OPCACHE, 'get'] => self::getFromOPcacheAsync($key),
                            [self::TIER_OPCACHE, 'set'] => self::setToOPcacheAsync($key, $op['data'], $op['ttl'] ?? 3600),
                            default => CachePromise::reject(new Exception("Unsupported async operation: {$tier}:{$method}"))
                        };

                        // add to promises array
                        $promises[ ] = $promise;
                    }

                    // wait for all promises to complete
                    CachePromise::all($promises)
                        -> then(function ($results) use ($resolve) {
                            $resolve($results);
                        })
                        -> catch(function ($error) use ($reject) {
                            $reject($error);
                        });

                // fallback to synchronous operations
                } else {
                    // try to process operations synchronously
                    try {
                        // setup results array
                        $results = [ ];

                        // loop through each operation
                        foreach ($operations as $op) {
                            $results[ ] = self::executeNonAsyncOperation($op);
                        }

                        // resolve with results
                        $resolve($results);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }

        /**
         * Execute non-async operation fallback
         *
         * Executes cache operations synchronously when async mode is not available
         * or when falling back from async operations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $op Operation details including tier, method, and parameters
         * @return mixed Returns the operation result or false on failure
         */
        private static function executeNonAsyncOperation(array $op): mixed
        {

            // get operation details
            $tier = $op['tier'];
            $method = $op['method'];
            $key = $op['key'];

            // match the tier and method to execute appropriate operation
            return match ([$tier, $method]) {
                [self::TIER_MEMCACHED, 'get'] => self::getFromMemcached($key),
                [self::TIER_MEMCACHED, 'set'] => self::setToMemcached($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_FILE, 'get'] => self::getFromFile($key),
                [self::TIER_FILE, 'set'] => self::setToFile($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_MMAP, 'get'] => self::getFromMmap($key),
                [self::TIER_MMAP, 'set'] => self::setToMmap($key, $op['data'], $op['ttl'] ?? 3600),
                [self::TIER_OPCACHE, 'get'] => self::getFromOPcache($key),
                [self::TIER_OPCACHE, 'set'] => self::setToOPcache($key, $op['data'], $op['ttl'] ?? 3600),
                default => false
            };
        }

        /**
         * Parallel cache warming for I/O intensive tiers
         *
         * Warms multiple cache tiers in parallel for improved performance
         * when pre-loading cache data across different backends.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $warm_data Array of data to warm across cache tiers
         * @return CachePromise Returns a promise that resolves with warming results
         */
        public static function parallelWarmCacheAsync(array $warm_data): CachePromise
        {

            // return a new promise for the warming operation
            return new CachePromise(function ($resolve, $reject) use ($warm_data) {

                // check if async is enabled and we have an event loop
                if (self::$_async_enabled && self::$_event_loop) {
                    // setup promises array
                    $promises = [ ];

                    // loop through each warm data item
                    foreach ($warm_data as $item) {
                        // get item details
                        $key = $item['key'];
                        $data = $item['data'];
                        $ttl = $item['ttl'] ?? 3600;
                        $tiers = $item['tiers'] ?? [self::TIER_FILE, self::TIER_MEMCACHED];

                        // loop through each tier for this item
                        foreach ($tiers as $tier) {
                            // match the tier to create appropriate promise
                            $promise = match ($tier) {
                                self::TIER_MEMCACHED => self::setToMemcachedAsync($key, $data, $ttl),
                                self::TIER_FILE => self::setToFileAsync($key, $data, $ttl),
                                self::TIER_MMAP => self::setToMmapAsync($key, $data, $ttl),
                                self::TIER_OPCACHE => self::setToOPcacheAsync($key, $data, $ttl),
                                default => CachePromise::resolve(false)
                            };

                            // add to promises array
                            $promises[ ] = $promise;
                        }
                    }

                    // wait for all promises to complete
                    CachePromise::all($promises)
                        -> then(function ($results) use ($resolve) {
                            $resolve(['warmed' => count($results), 'results' => $results]);
                        })
                        -> catch(function ($error) use ($reject) {
                            $reject($error);
                        });

                // fallback to synchronous warming
                } else {
                    // try to warm cache synchronously
                    try {
                        // setup warmed counter
                        $warmed = 0;

                        // loop through each warm data item
                        foreach ($warm_data as $item) {
                            // get item details
                            $key = $item['key'];
                            $data = $item['data'];
                            $ttl = $item['ttl'] ?? 3600;
                            $tiers = $item['tiers'] ?? [self::TIER_FILE, self::TIER_MEMCACHED];

                            // loop through each tier for this item
                            foreach ($tiers as $tier) {
                                // set to tier and increment counter if successful
                                $success = self::setToTierInternal($key, $data, $ttl, $tier);
                                if ($success) {
                                    $warmed++;
                                }
                            }
                        }

                        // resolve with warmed count
                        $resolve(['warmed' => $warmed]);

                    // whoopsie... reject the promise with the error
                    } catch (\Exception $e) {
                        $reject($e);
                    }
                }
            });
        }
    }
}
