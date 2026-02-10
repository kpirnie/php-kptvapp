<?php

/**
 * KPT Router - Rate Limiting Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist first
if (! trait_exists('RouterRateLimiter')) {

    /**
     * KPT Router Rate Limiter Trait
     *
     * Provides comprehensive rate limiting functionality with Redis and file-based
     * storage backends for controlling API request rates and preventing abuse.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait RouterRateLimiter
    {
        // rate limit configuration array
        private array $rateLimits = [
            'global' => [
                'limit' => 100,
                'window' => 60,
                'storage' => 'file'
            ]
        ];

        // redis connection instance
        private ?\Redis $redis = null;

        // file storage path for rate limits
        private string $rateLimitPath = '';

        // rate limiting enabled flag
        private bool $rateLimitingEnabled = false;

        /**
         * Enable rate limiting with automatic Redis/file fallback
         *
         * Initializes rate limiting with Redis as the preferred backend,
         * automatically falling back to file-based storage if Redis is unavailable.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $redisConfig Redis configuration array
         * @return bool Returns true if rate limiting was enabled successfully
         */
        // phpcs:ignore Generic.Files.LineLength.TooLong
        public function enableRateLimiter(array $redisConfig = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'password' => null]): bool
        {

            // First try to initialize Redis rate limiting
            if ($this->initRedisRateLimiting($redisConfig)) {
                Logger::debug('Rate limiting enabled with Redis backend');
                return true;
            }

            // Fall back to file-based rate limiting
            $this->enableFileRateLimiting();
            Logger::debug('Rate limiting enabled with file backend (Redis unavailable)');
            return true;
        }

        /**
         * Initialize Redis-based rate limiting
         *
         * Establishes a connection to Redis and configures it for use
         * as the rate limiting storage backend.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $config Redis configuration array
         * @return bool Returns true if initialization succeeded, false otherwise
         */
        // phpcs:ignore Generic.Files.LineLength.TooLong
        private function initRedisRateLimiting(array $config = ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 0.0, 'password' => null]): bool
        {

            // try to initialize redis connection
            try {
                // create new redis instance
                $this->redis = new \Redis();
                $connected = $this->redis->connect(
                    $config['host'],
                    $config['port'],
                    $config['timeout']
                );

                // check if connection failed
                if (! $connected) {
                    Logger::error('Redis ratelimiter failed', ['error' => 'failed to connect']);
                    throw new \RuntimeException('Failed to connect to Redis');
                }

                // configure redis settings
                $this->redis->select(1);
                $this->redis->setOption(\Redis::OPT_PREFIX, (KPT_URI ?? 'kpt_router') . '_RL:');

                // authenticate if password provided
                if (! empty($config['password'])) {
                    $this->redis->auth($config['password']);
                }

                // test connection with ping
                $this->redis->ping();

                // set storage type and enable rate limiting
                $this->rateLimits['global']['storage'] = 'redis';
                $this->rateLimitingEnabled = true;
                return true;

                // whoopsie... log error and return false
            } catch (\Throwable $e) {
                Logger::error('Redis ratelimiter failed', ['error' => $e->getMessage()]);
                $this->rateLimitingEnabled = false;
                return false;
            }
        }

        /**
         * Enable file-based rate limiting
         *
         * Configures file-based storage for rate limiting when Redis
         * is not available or connection fails.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        private function enableFileRateLimiting(): void
        {

            // Ensure the rate limit directory exists
            if (! is_dir($this->rateLimitPath)) {
                mkdir($this->rateLimitPath, 0755, true);
            }

            // configure for file storage and enable
            $this->rateLimits['global']['storage'] = 'file';
            $this->rateLimitingEnabled = true;
        }

        /**
         * Disable rate limiting
         *
         * Turns off rate limiting functionality for all requests,
         * allowing unlimited access.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public function disableRateLimiting(): void
        {

            // disable rate limiting
            $this->rateLimitingEnabled = false;
        }

        /**
         * Apply rate limiting to the current request
         *
         * Checks the current request against rate limits and either
         * allows the request or throws an exception if limit is exceeded.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @throws RuntimeException When rate limit is exceeded
         * @return void Returns nothing if request is allowed
         */
        private function applyRateLimiting(): void
        {

            // check if rate limiting is enabled
            if (! $this->rateLimitingEnabled) {
                return;
            }

            // get client ip and create cache key
            $clientIp = self::getUserIp();
            $cacheKey = 'rate_limit_' . md5($clientIp);

            // get rate limit configuration
            $limit = $this->rateLimits['global']['limit'];
            $window = $this->rateLimits['global']['window'];
            $storageType = $this->rateLimits['global']['storage'];

            // try to apply rate limiting
            try {
                // handle rate limiting based on storage type
                if ($storageType === 'redis' && $this->redis !== null) {
                    $current = $this->handleRedisRateLimit($cacheKey, $limit, $window);
                } else {
                    $current = $this->handleFileRateLimit($cacheKey, $limit, $window);
                }

                // check if rate limit exceeded
                if ($current >= $limit) {
                    header('Retry-After: ' . $window);
                    Logger::error('Rate limit exceeded', ['hits' => $current]);
                    throw new \RuntimeException('Rate limit exceeded', 429);
                }

                // set rate limit headers
                header('X-RateLimit-Limit: ' . $limit);
                header('X-RateLimit-Remaining: ' . max(0, $limit - $current - 1));
                header('X-RateLimit-Reset: ' . (time() + $window));

                // whoopsie... handle rate limiting errors
            } catch (\Exception $e) {
                Logger::error('Rate limiting error', ['error' => $e->getMessage()]);

                // check if strict mode is enabled
                if ($this->rateLimits['global']['strict_mode'] ?? false) {
                    Logger::error('Rate limiting error', ['error' => 'Rate limit service unavailable']);
                    throw new \RuntimeException('Rate limit service unavailable', 503);
                }
            }
        }

        /**
         * Handle Redis-based rate limiting
         *
         * Implements rate limiting logic using Redis as the storage backend
         * with atomic operations for accurate counting.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The rate limit key for the client
         * @param int $limit Maximum allowed requests in the window
         * @param int $window Time window in seconds
         * @return int Returns the current request count for the client
         */
        private function handleRedisRateLimit(string $key, int $limit, int $window): int
        {

            // get current count from redis
            $current = $this->redis->get($key);

            // check if key exists
            if ($current !== false) {
                // check if already at limit
                if ((int) $current >= $limit) {
                    return (int) $current;
                }

                // increment the counter
                $this->redis->incr($key);
                return (int) $current + 1;
            }

            // first request - set initial count with expiration
            $this->redis->setex($key, $window, 1);
            return 1;
        }

        /**
         * Handle file-based rate limiting
         *
         * Implements rate limiting logic using file storage with proper
         * locking and expiration handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $key The rate limit key for the client
         * @param int $limit Maximum allowed requests in the window
         * @param int $window Time window in seconds
         * @return int Returns the current request count for the client
         */
        private function handleFileRateLimit(string $key, int $limit, int $window): int
        {

            // setup file path and current time
            $file = $this->rateLimitPath . '/' . $key;
            $now = time();

            // check if file exists
            if (file_exists($file)) {
                // read existing data
                $data = json_decode(file_get_contents($file), true);

                // check if window hasn't expired
                if ($data['expires'] > $now) {
                    // increment count and update file
                    $current = $data['count'] + 1;
                    file_put_contents($file, json_encode([
                        'count' => $current,
                        'expires' => $data['expires']
                    ]), LOCK_EX);

                    // return updated count
                    return $current;
                }
            }

            // first request or expired window - create new entry
            file_put_contents($file, json_encode([
                'count' => 1,
                'expires' => $now + $window
            ]), LOCK_EX);

            // return initial count
            return 1;
        }
    }
}
