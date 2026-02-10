# KPT Cache - Modern Multi-Tier Caching System

A comprehensive, high-performance caching solution that provides multiple tiers of caching including OPcache, SHMOP, APCu, YAC, Redis, Memcached, SQLite, and File-based caching with automatic tier discovery, connection pooling, and failover support.

## ✨ Features

- **🚀 Multi-Tier Architecture**: Automatically discovers and prioritizes cache tiers for optimal performance
- **🔄 Automatic Failover**: Seamlessly falls back to available tiers when primary tiers fail
- **⚡ Connection Pooling**: Efficient connection management for Redis and Memcached
- **🔧 Async Operations**: Promise-based asynchronous cache operations with event loop support
- **🏥 Health Monitoring**: Comprehensive health checks and alerting for all cache tiers
- **🔑 Smart Key Management**: Intelligent key generation with tier-specific limitations handling
- **📊 Statistics & Monitoring**: Detailed performance metrics and usage statistics
- **🛠️ CLI Tools**: Command-line utilities for cache management and cleanup
- **🎯 Zero Configuration**: Works out of the box with sensible defaults
- **🔒 Production Ready**: Battle-tested with comprehensive error handling

## 🎯 Supported Cache Tiers

| Tier | Type | Priority | Use Case |
|------|------|----------|----------|
| **Array** | Memory | Highest | Request-level ultra-fast caching |
| **OPcache** | Memory | High | PHP opcode and file-based caching |
| **SHMOP** | Shared Memory | High | Inter-process shared memory |
| **APCu** | Memory | High | User data cache in shared memory |
| **YAC** | Memory | High | Yet Another Cache extension |
| **Redis** | Network | Medium | Distributed caching and sessions |
| **Memcached** | Network | Medium | Distributed memory caching |
| **SQLite** | Database | Low | Local database caching |
| **File** | Filesystem | Lowest | Fallback file-based caching |

## 📦 Installation

### Requirements

- PHP 8.1 or higher
- Composer
- At least one cache backend (File system is always available)

### Installation via Composer

```bash
composer require kevinpirnie/kpt-cache
```

### Optional Extensions

For maximum performance, install these PHP extensions:

```bash
# Redis support
pecl install redis

# Memcached support  
pecl install memcached

# APCu support
pecl install apcu

# YAC support
pecl install yac
```

## 🚀 Quick Start

### Basic Usage

```php
<?php
require_once 'vendor/autoload.php';

use KPT\Cache;

// Initialize the cache system
Cache::configure([
    'path' => '/var/cache/myapp',
    'prefix' => 'myapp:',
    'allowed_backends' => [ 'array', 'redis', 'memcached', 'opcache', 'shmop', 'file' ], // also: apcu, yac, sqlite
]);

// Store data (automatically uses best available tier)
Cache::set('user:123', $userData, 3600);

// Retrieve data
$userData = Cache::get('user:123');

// Delete data
Cache::delete('user:123');

// Clear all cache
Cache::clear();
```

### Advanced Configuration

```php
<?php
require_once 'vendor/autoload.php';

use KPT\Cache;

Cache::configure([
    'path' => '/var/cache/myapp',
    'prefix' => 'myapp:',
    'backends' => [
        'redis' => [
            'host' => 'localhost',
            'port' => 6379,
            'database' => 0,
            'persistent' => true
        ],
        'memcached' => [
            'host' => 'localhost', 
            'port' => 11211,
            'persistent' => true
        ],
    ]
]);
```

## 💡 Usage Examples

### Tier-Specific Operations

```php
// Store in specific tier
Cache::setToTier('key', $data, 3600, 'redis');

// Get from specific tier
$data = Cache::getFromTier('key', 'redis');

// Multi-tier operations
$results = Cache::setToTiers('key', $data, 3600, ['redis', 'memcached']);
$results = Cache::deleteFromTiers('key', ['redis', 'memcached']);
```

### Async Operations

```php
// Enable async support
Cache::enableAsync($eventLoop);

// Async operations with promises
Cache::getAsync('user:123')
    ->then(function($userData) {
        // Process user data
        return Cache::setAsync('processed:123', $processedData, 1800);
    })
    ->catch(function($error) {
        // Handle errors
        Logger::error('Cache error: ' . $error->getMessage());
    });

// Batch async operations
$promises = [
    Cache::getAsync('user:123'),
    Cache::getAsync('user:456'),
    Cache::getAsync('user:789')
];

CachePromise::all($promises)
    ->then(function($results) {
        // All users loaded
        foreach($results as $userData) {
            // Process each user
        }
    });
```

### Health Monitoring

```php
// Check overall health
$healthStatus = Cache::isHealthy();

// Check specific tier health
$isRedisHealthy = Cache::isTierHealthy('redis');

// Get detailed health information
$healthDetails = CacheHealthMonitor::checkAllTiers();

// Get performance statistics
$stats = Cache::getStats();
```

### Key Management

```php
// Namespaced keys
CacheKeyManager::setGlobalNamespace('myapp');
$key = CacheKeyManager::createNamespacedKey('user:123', 'session', 'redis');

// Analyze key generation
$keyInfo = CacheKeyManager::analyzeKey('very-long-key-name', 'memcached');

// Get tier limitations
$limitations = CacheKeyManager::getTierLimitations();
```

## 🔧 Configuration

### Global Configuration

```php
Cache::configure([
    // Global cache path (used by file, opcache, sqlite)
    'path' => '/var/cache/myapp',
    
    // Global key prefix  
    'prefix' => 'myapp:',
    
    // Backend-specific settings
    'backends' => [
        'redis' => [
            'host' => 'redis.example.com',
            'port' => 6379,
            'database' => 1,
            'prefix' => 'custom:',
            'persistent' => true,
            'retry_attempts' => 3
        ],
        'memcached' => [
            'host' => 'memcached.example.com',
            'port' => 11211,
            'persistent' => true
        ],
        'file' => [
            'path' => '/custom/cache/path',
            'permissions' => 0755
        ]
    ]
]);
```

### Environment-Specific Configuration

```php
// Development
if ($_ENV['APP_ENV'] === 'development') {
    Cache::configure([
        'path' => '/tmp/dev-cache',
        'prefix' => 'dev:'
    ]);
}

// Production
if ($_ENV['APP_ENV'] === 'production') {
    Cache::configure([
        'path' => '/var/cache/production',
        'prefix' => 'prod:',
        'backends' => [
            'redis' => [
                'host' => $_ENV['REDIS_HOST'],
                'port' => $_ENV['REDIS_PORT'],
                'database' => $_ENV['REDIS_DB']
            ]
        ]
    ]);
}
```

## 📊 Monitoring & Statistics

### Performance Statistics

```php
// Get comprehensive statistics
$stats = Cache::getStats();

/*
Returns:
[
    'opcache' => [
        'opcache_enabled' => true,
        'memory_usage' => [...],
        'statistics' => [...]
    ],
    'redis' => [
        'connected_clients' => 5,
        'memory_usage' => 45.2,
        'hit_rate' => 96.5
    ],
    'connection_pools' => [...],
    'tier_manager' => [...],
    'health_monitor' => [...]
]
*/
```

### Health Monitoring

```php
// Set up health monitoring
CacheHealthMonitor::initialize([
    'monitoring_enabled' => true,
    'check_interval' => 60,
    'alert_config' => [
        'log_alerts' => true,
        'email_alerts' => true,
        'callback_alerts' => function($tier, $message, $details) {
            // Custom alert handling
            SlackNotifier::send("Cache Alert: {$message}");
        }
    ]
]);

// Get health status
$health = CacheHealthMonitor::checkAllTiers();
```

## 🛠️ CLI Tools

The package includes CLI tools for cache management. After installation, you can access them in your vendor directory:

### Cache Cleaner

```bash
# Clear all caches
php vendor/kevinpirnie/kpt-cache/cache/cleaner.php --clear_all

# Clear specific tier
php vendor/kevinpirnie/kpt-cache/cache/cleaner.php --clear_tier=redis

# Cleanup expired entries
php vendor/kevinpirnie/kpt-cache/cache/cleaner.php --cleanup
```

### Integration with Cron

```bash
# Add to crontab for automatic cleanup
# Cleanup expired entries every hour
0 * * * * php /path/to/your/app/vendor/kevinpirnie/kpt-cache/cache/cleaner.php --cleanup

# Full cache clear daily at 3 AM
0 3 * * * php /path/to/your/app/vendor/kevinpirnie/kpt-cache/cache/cleaner.php --clear_all
```

## 🔍 Debugging & Troubleshooting

### Debug Information

```php
// Get comprehensive debug info
$debugInfo = Cache::debug();

// Check tier availability
$availableTiers = Cache::getAvailableTiers();

// Validate configuration
$configValidation = CacheConfig::validateGlobal();

// Get last errors
$lastError = Cache::getLastError();
```

### Common Issues

#### Redis Connection Issues
```php
// Test Redis connectivity
if (!Cache::isTierAvailable('redis')) {
    $error = Cache::getLastError();
    Logger::error("Redis unavailable: " . $error);
}
```

#### File Permission Issues
```php
// Check cache path permissions
$pathInfo = Cache::getCachePathInfo();
if (!$pathInfo['is_writable']) {
    // Fix permissions or update path
    Cache::updateCachePath('/tmp/alternative-cache');
}
```

#### Memory Issues
```php
// Monitor memory usage
$stats = Cache::getStats();
foreach (['opcache', 'apcu'] as $tier) {
    if (isset($stats[$tier]['memory_usage'])) {
        $usage = $stats[$tier]['memory_usage'];
        if ($usage > 90) {
            Logger::warning("{$tier} memory usage high: {$usage}%");
        }
    }
}
```

## 🚀 Performance Optimization

### Best Practices

1. **Tier Priority**: Use faster tiers for frequently accessed data
2. **Key Design**: Use consistent, meaningful key patterns
3. **TTL Strategy**: Set appropriate expiration times
4. **Connection Pooling**: Enable for Redis/Memcached in high-traffic apps
5. **Monitoring**: Regular health checks and performance monitoring

### Configuration for High Traffic

```php
Cache::configure([
    'backends' => [
        'redis' => [
            'host' => 'redis-cluster.example.com',
            'persistent' => true,
            'retry_attempts' => 3,
            'connection_timeout' => 2
        ]
    ]
]);

// Enable connection pooling
CacheConnectionPool::configurePool('redis', [
    'min_connections' => 5,
    'max_connections' => 20,
    'idle_timeout' => 300
]);
```

## 🧪 Testing

### Unit Testing Example

```php
<?php
use PHPUnit\Framework\TestCase;
use KPT\Cache;

class CacheTest extends TestCase
{
    public function setUp(): void
    {
        Cache::configure(['path' => '/tmp/test-cache']);
    }

    public function testBasicOperations()
    {
        // Test set/get
        $this->assertTrue(Cache::set('test:key', 'test-value', 60));
        $this->assertEquals('test-value', Cache::get('test:key'));
        
        // Test delete
        $this->assertTrue(Cache::delete('test:key'));
        $this->assertFalse(Cache::get('test:key'));
    }

    public function testTierSpecificOperations()
    {
        if (Cache::isTierAvailable('redis')) {
            $this->assertTrue(
                Cache::setToTier('redis:test', 'redis-value', 60, 'redis')
            );
            $this->assertEquals(
                'redis-value', 
                Cache::getFromTier('redis:test', 'redis')
            );
        }
    }
}
```

## 📚 API Reference

### Core Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `Cache::set($key, $data, $ttl)` | Store data in cache | `bool` |
| `Cache::get($key)` | Retrieve data from cache | `mixed` |
| `Cache::delete($key)` | Delete item from cache | `bool` |
| `Cache::clear()` | Clear all cached data | `bool` |
| `Cache::getStats()` | Get performance statistics | `array` |
| `Cache::isHealthy()` | Check system health | `array` |

### Tier-Specific Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `Cache::setToTier($key, $data, $ttl, $tier)` | Store in specific tier | `bool` |
| `Cache::getFromTier($key, $tier)` | Get from specific tier | `mixed` |
| `Cache::deleteFromTier($key, $tier)` | Delete from specific tier | `bool` |
| `Cache::setToTiers($key, $data, $ttl, $tiers)` | Store in multiple tiers | `array` |

### Async Methods

| Method | Description | Returns |
|--------|-------------|---------|
| `Cache::enableAsync($eventLoop)` | Enable async operations | `void` |
| `Cache::getAsync($key)` | Async get operation | `CachePromise` |
| `Cache::setAsync($key, $data, $ttl)` | Async set operation | `CachePromise` |
| `Cache::getBatchAsync($keys)` | Async batch get | `CachePromise` |

## 🤝 Contributing

Contributions are welcome! Please follow these guidelines:

1. **Code Style**: Follow PSR-12 coding standards
2. **Documentation**: Update documentation for new features
3. **Testing**: Add tests for new functionality
4. **Backwards Compatibility**: Maintain API compatibility

### Development Setup

```bash
git clone https://github.com/kpirnie/kp-cache.git
cd kp-cache
composer install
```

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/kpirnie/kp-cache/issues)
- **Packagist**: [kevinpirnie/kpt-cache](https://packagist.org/packages/kevinpirnie/kpt-cache)

## 🙏 Acknowledgments

- Redis community for excellent documentation
- Memcached team for reliable caching
- PHP community for extensions and tools
- All contributors who helped improve this system

---

**KPT Cache** - Built with ❤️ for high-performance PHP applications
