# KPT Logger

A simple, universal application logger for PHP that provides basic logging capabilities with configurable output destinations and four standard log levels.

## Features

- **Four Log Levels**: ERROR, WARNING, INFO, DEBUG
- **Flexible Output**: Log to system log or custom file
- **Context Support**: Include additional data with log entries
- **Stack Traces**: Optional stack trace inclusion for debugging
- **Always-On Errors**: Error messages log even when logging is disabled
- **Thread-Safe**: File writing with exclusive locks
- **Easy Integration**: Simple static methods for quick logging

## Requirements

- PHP 8.0 or higher
- Write permissions for log file directory (if using file logging)

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-logger
```

## Basic Usage

### Initialize the Logger

```php
use KPT\Logger;

// Enable logging with stack traces
$logger = new Logger(true, true);

// Enable logging without stack traces
$logger = new Logger(true, false);

// Disable logging (errors still log)
$logger = new Logger(false);
```

### Log Messages

```php
// Log an error (always logs, even when disabled)
Logger::error("Database connection failed");

// Log a warning (only when enabled)
Logger::warning("API rate limit approaching");

// Log info (only when enabled)
Logger::info("User successfully logged in");

// Log debug info (only when enabled)
Logger::debug("Processing user data", ['user_id' => 123]);
```

### Using the Alias

```php
// You can also use the shorter LOG alias
LOG::error("Something went wrong!");
LOG::info("Operation completed");
```

## Configuration

### Set Log File

```php
// Log to a custom file
Logger::setLogFile('/var/log/myapp.log');

// Log to system log (default)
Logger::setLogFile(null);
```

### Advanced Logging with Context

```php
// Include additional context data
Logger::error("Payment processing failed", [
    'user_id' => 456,
    'amount' => 99.99,
    'transaction_id' => 'txn_123456'
]);

// Override stack trace setting per call
Logger::debug("Debugging info", [], false); // No stack trace
Logger::error("Critical error", [], true);  // Force stack trace
```

## Log Levels

| Level | Constant | Description | Always Logs |
|-------|----------|-------------|-------------|
| ERROR | `Logger::LEVEL_ERROR` | Error conditions | ✅ Yes |
| WARNING | `Logger::LEVEL_WARNING` | Warning conditions | Only when enabled |
| INFO | `Logger::LEVEL_INFO` | Informational messages | Only when enabled |
| DEBUG | `Logger::LEVEL_DEBUG` | Debug-level messages | Only when enabled |

## Output Format

Log entries are formatted as follows:

```
[2024-08-18 14:30:25] ERROR: Database connection failed | Context: {"host":"localhost","port":3306} | Stack: [...]
```

**Format breakdown:**
- `[2024-08-18 14:30:25]` - Timestamp
- `ERROR` - Log level
- `Database connection failed` - Your message
- `Context: {...}` - Additional context data (if provided)
- `Stack: [...]` - Stack trace (if enabled)

## Examples

### Basic Error Logging

```php
try {
    // Some risky operation
    $result = riskyOperation();
} catch (Exception $e) {
    Logger::error("Operation failed: " . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
```

### Application Monitoring

```php
// Track user actions
Logger::info("User login", ['user_id' => $userId, 'ip' => $_SERVER['REMOTE_ADDR']]);

// Monitor performance
$start = microtime(true);
processData();
$duration = microtime(true) - $start;

Logger::debug("Data processing completed", ['duration' => $duration]);
```

### API Integration Logging

```php
// Log API calls
Logger::info("Making API request", ['endpoint' => $url, 'method' => 'POST']);

if ($response->getStatusCode() !== 200) {
    Logger::warning("API returned non-200 status", [
        'status' => $response->getStatusCode(),
        'body' => $response->getBody()
    ]);
}
```

## File Logging Setup

```php
// Set up file logging with error handling
if (!Logger::setLogFile('/var/log/myapp.log')) {
    // Fallback to system log if file setup fails
    Logger::setLogFile(null);
    Logger::warning("Could not set custom log file, using system log");
}
```

## Best Practices

1. **Initialize Early**: Set up the logger at the beginning of your application
2. **Use Appropriate Levels**: Reserve ERROR for actual errors, use DEBUG sparingly in production
3. **Include Context**: Add relevant data to help with debugging
4. **Handle File Permissions**: Ensure your application can write to the log directory
5. **Log File Rotation**: Implement log rotation for production environments
6. **Sensitive Data**: Avoid logging passwords or other sensitive information

## Thread Safety

The logger uses `LOCK_EX` when writing to files, making it safe for concurrent access in multi-threaded environments.

## Performance Considerations

- Disabled logging levels have minimal performance impact (simple boolean check)
- Stack traces add overhead - disable in production if not needed
- File I/O is synchronous - consider async logging for high-traffic applications

## Author

**Kevin Pirnie** - [me@kpirnie.com](mailto:me@kpirnie.com)

## License

Part of the KP Library package.

## Version

Since version 8.4
