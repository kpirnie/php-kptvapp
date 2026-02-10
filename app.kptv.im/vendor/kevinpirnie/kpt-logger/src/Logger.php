<?php

/**
 * KPT Logger - Simple Universal Application Logger
 *
 * Provides basic logging capabilities for any application with support for
 * four log levels and configurable output destinations (system log or file).
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

declare(strict_types=1);

namespace KPT;

use JsonException;

/**
 * KPT Logger
 *
 * Simple, focused logging system for applications with configurable
 * output destinations and four standard log levels.
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */
class Logger
{
    /** @var int Error: error conditions */
    public const LEVEL_ERROR = 1;

    /** @var int Warning: warning conditions */
    public const LEVEL_WARNING = 2;

    /** @var int Info: informational messages */
    public const LEVEL_INFO = 3;

    /** @var int Debug: debug-level messages */
    public const LEVEL_DEBUG = 4;

    /** @var bool Whether logging is enabled (errors always log) */
    private static bool $enabled = false;

    /** @var string|null Log file path */
    private static string|null $logFile = null;

    /** @var bool Whether to include stack trace */
    private static bool $includeStackTrace = true;

    /**
     * Initialize the logger class
     *
     * Sets whether or not debug logs are enabled, and if a stack trace should be logged
     */
    public function __construct(
        private readonly bool $instanceEnabled,
        private readonly bool $instanceShowStack = true
    ) {
        self::$enabled = $this->instanceEnabled;
        self::$includeStackTrace = $this->instanceShowStack;
    }

    /**
     * Get level name from level constant
     */
    private static function getLevelName(int $level): string
    {
        return match ($level) {
            self::LEVEL_ERROR => 'ERROR',
            self::LEVEL_WARNING => 'WARNING',
            self::LEVEL_INFO => 'INFO',
            self::LEVEL_DEBUG => 'DEBUG',
            default => 'UNKNOWN'
        };
    }

    /**
     * Log an error message
     *
     * Errors always log, even when logging is disabled
     *
     * @param string $message Error message
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     */
    public static function error(
        string $message,
        array $context = [],
        bool|null $includeStack = null
    ): void {
        self::writeLog(
            message: $message,
            level: self::LEVEL_ERROR,
            context: $context,
            includeStack: $includeStack
        );
    }

    /**
     * Log a warning message
     *
     * Only logs when logging is enabled
     *
     * @param string $message Warning message
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     */
    public static function warning(
        string $message,
        array $context = [],
        bool|null $includeStack = null
    ): void {
        if (!self::$enabled) {
            return;
        }

        self::writeLog(
            message: $message,
            level: self::LEVEL_WARNING,
            context: $context,
            includeStack: $includeStack
        );
    }

    /**
     * Log an info message
     *
     * Only logs when logging is enabled
     *
     * @param string $message Info message
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     */
    public static function info(
        string $message,
        array $context = [],
        bool|null $includeStack = null
    ): void {
        if (!self::$enabled) {
            return;
        }

        self::writeLog(
            message: $message,
            level: self::LEVEL_INFO,
            context: $context,
            includeStack: $includeStack
        );
    }

    /**
     * Log a debug message
     *
     * Only logs when logging is enabled
     *
     * @param string $message Debug message
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     */
    public static function debug(
        string $message,
        array $context = [],
        bool|null $includeStack = null
    ): void {
        if (!self::$enabled) {
            return;
        }

        self::writeLog(
            message: $message,
            level: self::LEVEL_DEBUG,
            context: $context,
            includeStack: $includeStack
        );
    }

    /**
     * Set the log file path
     *
     * @param string|null $filePath Log file path (null to use system log)
     * @return bool Returns true if file is writable or null, false otherwise
     */
    public static function setLogFile(string|null $filePath): bool
    {
        if ($filePath === null) {
            self::$logFile = null;
            return true;
        }

        $dir = dirname($filePath);

        // Create directory if it doesn't exist
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }

        // Check if directory is writable
        if (!is_writable($dir)) {
            return false;
        }

        self::$logFile = $filePath;
        return true;
    }

    /**
     * Write log data to configured destination
     *
     * @param string $message Log message
     * @param int $level Log level
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     */
    private static function writeLog(
        string $message,
        int $level,
        array $context = [],
        bool|null $includeStack = null
    ): void {
        $formattedMessage = self::formatLogEntry(
            message: $message,
            level: $level,
            context: $context,
            includeStack: $includeStack
        );

        match (self::$logFile) {
            null => error_log($formattedMessage),
            default => @file_put_contents(
                self::$logFile,
                $formattedMessage . PHP_EOL,
                FILE_APPEND | LOCK_EX
            )
        };
    }

    /**
     * Format log entry for output
     *
     * @param string $message Log message
     * @param int $level Log level
     * @param array<string, mixed> $context Additional context data
     * @param bool|null $includeStack Whether to include stack trace (null = use global setting)
     * @return string Returns formatted log entry
     */
    private static function formatLogEntry(
        string $message,
        int $level,
        array $context = [],
        bool|null $includeStack = null
    ): string {
        $timestamp = date('Y-m-d H:i:s');
        $levelName = self::getLevelName($level);
        $formatted = "[{$timestamp}] {$levelName}: {$message}";

        // Add context if present
        if (!empty($context)) {
            try {
                $contextJson = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $formatted .= " | Context: {$contextJson}";
            } catch (JsonException) {
                $formatted .= ' | Context: [JSON encoding failed]';
            }
        }

        // Determine whether to include stack trace
        $shouldIncludeStack = $includeStack ?? self::$includeStackTrace;

        // Add stack trace if enabled
        if ($shouldIncludeStack) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $filteredTrace = array_slice($trace, 3);

            try {
                $traceJson = json_encode($filteredTrace, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                $formatted .= " | Stack: {$traceJson}";
            } catch (JsonException) {
                $formatted .= ' | Stack: [JSON encoding failed]';
            }
        }

        return $formatted;
    }

    /**
     * Enable or disable logging
     */
    public static function setEnabled(bool $enabled): void
    {
        self::$enabled = $enabled;
    }

    /**
     * Check if logging is enabled
     */
    public static function isEnabled(): bool
    {
        return self::$enabled;
    }

    /**
     * Set stack trace inclusion globally
     */
    public static function setIncludeStackTrace(bool $include): void
    {
        self::$includeStackTrace = $include;
    }

    /**
     * Get current log file path
     */
    public static function getLogFile(): string|null
    {
        return self::$logFile;
    }
}
