# KPT Database

A modern, fluent PHP database wrapper built on top of PDO, providing an elegant and secure way to interact with databases.

## Features

- **Fluent Interface**: Chain methods for readable and intuitive database operations
- **Multi-Driver Support**: MySQL, PostgreSQL, SQLite, SQL Server, and Oracle
- **PSR-12 Compliant**: Follows PHP coding standards with camelCase method names
- **Prepared Statements**: Built-in protection against SQL injection
- **Flexible Fetching**: Return results as objects or arrays, single records or collections
- **Transaction Support**: Full transaction management with commit/rollback
- **Type-Safe Parameter Binding**: Automatic parameter type detection and binding (positional and named)
- **Raw Query Support**: Execute custom SQL when needed
- **Comprehensive Logging**: Debug and error logging throughout
- **Query Profiling**: Built-in query logging for performance debugging
- **Connection Pooling**: Singleton pattern support for managing multiple connections
- **Batch Operations**: Efficient batch inserts and upsert support
- **Method Chaining**: Build complex queries with readable, chainable methods

## Requirements

- PHP 8.2 or higher
- PDO extension
- Supported databases: MySQL 5.7+, MariaDB 10.2+, PostgreSQL, SQLite, SQL Server, Oracle

## Installation

Install via Composer:

```bash
composer require kevinpirnie/kpt-database
```

## Configuration

The database class requires a settings object to be passed to the constructor and expects a `Logger` class to be available in the `KPT` namespace:

```php
$db_settings = (object) [
    'driver' => 'mysql', // mysql, pgsql, sqlite, sqlsrv, oci
    'server' => 'localhost',
    'schema' => 'your_database',
    'username' => 'your_username', 
    'password' => 'your_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'persistent' => false // Set to true for persistent connections
];

$db = new Database($db_settings);
```

**Note**: You'll need to have a `KPT\Logger` class available with static `debug()` and `error()` methods for logging functionality.

## Basic Usage

### Initialization

```php
use KPT\Database;

$db_settings = (object) [
    'driver' => 'mysql',
    'server' => 'localhost',
    'schema' => 'my_database',
    'username' => 'db_user',
    'password' => 'db_password',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci'
];

$db = new Database($db_settings);
```

### Connection Pooling

For applications that need multiple database connections or want to reuse connections:

```php
// Create or retrieve a named connection
$db = Database::getInstance('default', $db_settings);

// Later, retrieve the same connection without settings
$db = Database::getInstance('default');

// Create additional connections
$analytics_db = Database::getInstance('analytics', $analytics_settings);

// Close a specific connection when done
Database::closeInstance('analytics');
```

### Select Operations

```php
// Fetch all users
$users = $db->query("SELECT * FROM users")->fetch();

// Fetch single user by ID
$user = $db->query("SELECT * FROM users WHERE id = ?")
           ->bind([123])
           ->single()
           ->fetch();

// Or use the first() shorthand
$user = $db->query("SELECT * FROM users WHERE id = ?")
           ->bind([123])
           ->first();

// Fetch as arrays instead of objects
$users = $db->query("SELECT * FROM users")
            ->asArray()
            ->fetch();

// Fetch with limit
$recent_users = $db->query("SELECT * FROM users ORDER BY created_at DESC")
                   ->fetch(10);
```

### Insert Operations

```php
// Insert new user
$user_id = $db->query("INSERT INTO users (name, email, created_at) VALUES (?, ?, NOW())")
              ->bind(['John Doe', 'john@example.com'])
              ->execute();

// The execute() method returns the last insert ID for INSERT queries
echo "New user ID: " . $user_id;
```

### Update Operations

```php
// Update user
$affected_rows = $db->query("UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?")
                    ->bind(['Jane Doe', 123])
                    ->execute();

echo "Updated {$affected_rows} rows";
```

### Delete Operations

```php
// Delete user
$affected_rows = $db->query("DELETE FROM users WHERE id = ?")
                    ->bind([123])
                    ->execute();

echo "Deleted {$affected_rows} rows";
```

### Parameter Binding

```php
// Positional parameters (?)
$db->query("SELECT * FROM users WHERE id = ?")->bind(123);

// Multiple positional parameters
$db->query("SELECT * FROM users WHERE name = ? AND email = ?")
   ->bind(['John Doe', 'john@example.com']);

// Named parameters (:name)
$db->query("SELECT * FROM users WHERE name = :name AND email = :email")
   ->bind(['name' => 'John Doe', 'email' => 'john@example.com']);

// Automatic type detection handles strings, integers, booleans, and nulls
$db->query("SELECT * FROM users WHERE active = ? AND age > ? AND name LIKE ?")
   ->bind([true, 25, '%John%']);
```

### Transactions

```php
// Start transaction
$db->transaction();

try {
    // Perform multiple operations
    $user_id = $db->query("INSERT INTO users (name, email) VALUES (?, ?)")
                  ->bind(['John Doe', 'john@example.com'])
                  ->execute();
    
    $db->query("INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)")
       ->bind([$user_id, 'Software Developer'])
       ->execute();
    
    // Commit if all operations succeed
    $db->commit();
    
} catch (Exception $e) {
    // Rollback on any error
    $db->rollback();
    throw $e;
}

// Check if currently in a transaction
if ($db->inTransaction()) {
    // ...
}
```

### Raw Queries

For complex queries that don't fit the builder pattern:

```php
// Raw SELECT
$results = $db->raw("
    SELECT u.*, p.bio 
    FROM users u 
    LEFT JOIN profiles p ON u.id = p.user_id 
    WHERE u.created_at > ?
", ['2023-01-01']);

// Raw INSERT with parameters
$insert_id = $db->raw("
    INSERT INTO complex_table (col1, col2, col3) 
    SELECT ?, ?, ? 
    FROM another_table 
    WHERE condition = ?
", ['value1', 'value2', 'value3', 'condition_value']);
```

### Helper Methods

```php
// Count records
$total_users = $db->count('users');
$active_users = $db->count('users', '*', 'active = ?', [true]);
$unique_emails = $db->count('users', 'DISTINCT email');

// Check if records exist
if ($db->exists('users', 'email = ?', ['john@example.com'])) {
    echo "User exists!";
}

// Get first record (shorthand for ->single()->fetch())
$user = $db->query("SELECT * FROM users WHERE email = ?")
           ->bind(['john@example.com'])
           ->first();
```

### Batch Insert

```php
// Insert multiple rows efficiently
$columns = ['name', 'email', 'created_at'];
$rows = [
    ['John Doe', 'john@example.com', '2024-01-01'],
    ['Jane Doe', 'jane@example.com', '2024-01-02'],
    ['Bob Smith', 'bob@example.com', '2024-01-03'],
];

$inserted = $db->insertBatch('users', $columns, $rows);
echo "Inserted {$inserted} rows";
```

### Upsert and Replace

```php
// Insert or update on duplicate key (MySQL)
$db->upsert(
    'users',
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'], // insert data
    ['name' => 'John Doe', 'email' => 'john@example.com'] // update data on duplicate
);

// Replace (delete + insert if exists)
$db->replace('users', [
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);
```

### Query Profiling

Enable query profiling to debug slow queries:

```php
// Enable profiling
$db->enableProfiling();

// Run your queries
$users = $db->query("SELECT * FROM users")->fetch();
$posts = $db->query("SELECT * FROM posts WHERE user_id = ?")->bind([1])->fetch();

// Get the query log
$log = $db->getQueryLog();
foreach ($log as $entry) {
    echo "Query: {$entry['query']}\n";
    echo "Duration: {$entry['duration_ms']}ms\n";
    echo "Timestamp: {$entry['timestamp']}\n";
}

// Clear the log
$db->clearQueryLog();

// Disable profiling
$db->disableProfiling();
```

### Quoting Values

For edge cases where manual escaping is needed:

```php
$quoted = $db->quote("O'Brien");
// Returns: 'O\'Brien'
```

## Method Reference

### Query Building

- `query(string $sql)` - Set the SQL query to execute
- `bind(mixed $params)` - Bind parameters (single value, array, or named parameters)
- `single()` - Set mode to fetch single record
- `many()` - Set mode to fetch multiple records (default)
- `asArray()` - Return results as associative arrays
- `asObject()` - Return results as objects (default)

### Execution

- `fetch(?int $limit = null)` - Execute SELECT queries and return results
- `first()` - Fetch the first record (shorthand for single()->fetch())
- `execute()` - Execute INSERT/UPDATE/DELETE queries
- `raw(string $query, array $params = [])` - Execute raw SQL

### Helper Methods

- `count(string $table, string $column = '*', ?string $where = null, array $params = [])` - Count records
- `exists(string $table, string $where, array $params = [])` - Check if records exist
- `insertBatch(string $table, array $columns, array $rows)` - Insert multiple rows
- `upsert(string $table, array $data, array $update)` - Insert or update on duplicate
- `replace(string $table, array $data)` - Replace record
- `quote(string $value, int $type = PDO::PARAM_STR)` - Quote a string for safe use

### Transactions

- `transaction()` - Begin a transaction
- `commit()` - Commit the current transaction  
- `rollback()` - Roll back the current transaction
- `inTransaction()` - Check if currently in a transaction

### Connection Management

- `configure(array|object $config)` - Static method to create a configured instance
- `getInstance(string $name, ?object $settings)` - Get or create a named connection
- `closeInstance(string $name)` - Close a named connection

### Profiling

- `enableProfiling()` - Enable query logging
- `disableProfiling()` - Disable query logging
- `getQueryLog()` - Get logged queries
- `clearQueryLog()` - Clear the query log

### Utilities

- `getLastId()` - Get the last inserted ID
- `reset()` - Reset the query builder state

## Method Chaining

All query building methods return `$this`, allowing for fluent chaining:

```php
$user = $db->query("SELECT * FROM users WHERE email = ?")
           ->bind('john@example.com')
           ->single()
           ->asArray()
           ->fetch();
```

## Error Handling

The class throws exceptions for database errors. Always wrap database operations in try-catch blocks:

```php
try {
    $result = $db->query("SELECT * FROM users")->fetch();
} catch (Exception $e) {
    // Handle database error
    error_log("Database error: " . $e->getMessage());
}
```

## Logging

The class includes comprehensive logging through a `Logger` class:

- Debug logs for successful operations
- Error logs for failures and exceptions
- Parameter binding information
- Query execution details

## Security

- **Prepared Statements**: All queries use prepared statements to prevent SQL injection
- **Parameter Type Detection**: Automatic binding with appropriate PDO parameter types
- **Input Validation**: Validates queries and parameters before execution

## Contributing

1. Fork the repository
2. Create a feature branch
3. Add tests for new functionality
4. Ensure all tests pass
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Author

**Kevin Pirnie** - [me@kpirnie.com](mailto:me@kpirnie.com)
