<?php

declare(strict_types=1);

namespace Kptv\IptvSync;

use Kptv\IptvSync\KpDb;
use Kptv\IptvSync\Database\WhereClause;
use Kptv\IptvSync\Database\ComparisonOperator;

class FilterManager
{
    private array $regexCache = [];
    private int $regexErrorCount = 0;

    public function __construct(
        private readonly KpDb $db
    ) {
    }

    public function getFilters(int $userId): array
    {
        $filters = $this->db->get_all(
            table: 'stream_filters',
            where: [
                new WhereClause('u_id', $userId, ComparisonOperator::EQ),
                new WhereClause('sf_active', 1, ComparisonOperator::EQ)
            ]
        );

        return $filters ?? [];
    }

    public function applyFilters(array $streams, array $filters): array
    {
        if (empty($filters)) {
            return $streams;
        }

        // Reset error count for this batch
        $this->regexErrorCount = 0;

        $filtered = [];
        $chunkSize = 1000;
        $chunks = array_chunk($streams, $chunkSize);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $stream) {
                if ($this->shouldIncludeStream($stream, $filters)) {
                    $filtered[] = $stream;
                }
            }
            
            // Clear regex cache every chunk to free memory
            $this->regexCache = [];
            gc_collect_cycles();
        }

        if ($this->regexErrorCount > 0) {
            echo "Warning: {$this->regexErrorCount} regex errors encountered during filtering\n";
        }

        return $filtered;
    }

    private function shouldIncludeStream(array $stream, array $filters): bool
    {
        $name = $stream['s_orig_name'] ?? '';
        $uri = $stream['s_stream_uri'] ?? '';
        $group = $stream['s_tvg_group'] ?? '';

        // Check if any include filters exist
        $hasIncludeFilter = false;
        $explicitlyIncluded = false;

        foreach ($filters as $f) {
            if ($f['sf_type_id'] === 0) {
                $hasIncludeFilter = true;
                break;
            }
        }

        // Process all filters
        foreach ($filters as $f) {
            $filterType = $f['sf_type_id'];
            $filterValue = $f['sf_filter'];

            // Type 0: Always include (regex name)
            if ($filterType === 0) {
                if ($this->matchesRegex($filterValue, $name, $f['id'])) {
                    $explicitlyIncluded = true;
                }
            }

            // Type 1: Exclude (string name) - case-insensitive substring match
            if ($filterType === 1 && stripos($name, $filterValue) !== false) {
                return false;
            }

            // Type 2: Exclude (regex name)
            if ($filterType === 2) {
                if ($this->matchesRegex($filterValue, $name, $f['id'])) {
                    return false;
                }
            }

            // Type 3: Exclude (regex stream URI)
            if ($filterType === 3) {
                if ($this->matchesRegex($filterValue, $uri, $f['id'])) {
                    return false;
                }
            }

            // Type 4: Exclude (regex group)
            if ($filterType === 4) {
                if ($this->matchesRegex($filterValue, $group, $f['id'])) {
                    return false;
                }
            }
        }

        // If there are include filters and stream wasn't explicitly included, exclude it
        if ($hasIncludeFilter && !$explicitlyIncluded) {
            return false;
        }

        return true;
    }

    /**
     * Match string against regex pattern with proper error handling and caching
     * 
     * @param string $pattern The regex pattern (without delimiters)
     * @param string $subject The string to match against
     * @param int $filterId Filter ID for error reporting
     * @return bool True if pattern matches
     */
    private function matchesRegex(string $pattern, string $subject, int $filterId): bool
    {
        // Return false for empty patterns or subjects
        if (empty($pattern) || empty($subject)) {
            return false;
        }

        // Check cache first
        $cacheKey = md5($pattern . '||' . $subject);
        if (isset($this->regexCache[$cacheKey])) {
            return $this->regexCache[$cacheKey];
        }

        try {
            // Add delimiters if not present
            $wrappedPattern = $this->wrapPattern($pattern);
            
            // Suppress warnings and catch errors
            $previousError = error_reporting(0);
            $result = @preg_match($wrappedPattern, $subject);
            error_reporting($previousError);

            if ($result === false) {
                // Pattern compilation failed
                $error = preg_last_error_msg();
                echo "Regex error in filter {$filterId}: {$error} - Pattern: {$pattern}\n";
                $this->regexErrorCount++;
                $this->regexCache[$cacheKey] = false;
                return false;
            }

            $matched = $result === 1;
            $this->regexCache[$cacheKey] = $matched;
            return $matched;

        } catch (\Exception $e) {
            echo "Exception in filter {$filterId}: {$e->getMessage()} - Pattern: {$pattern}\n";
            $this->regexErrorCount++;
            $this->regexCache[$cacheKey] = false;
            return false;
        }
    }

    /**
     * Wrap pattern with delimiters if needed
     * 
     * @param string $pattern The regex pattern
     * @return string Pattern with delimiters and case-insensitive flag
     */
    private function wrapPattern(string $pattern): string
    {
        $pattern = trim($pattern);
        
        // Pattern is NOT delimited — safely wrap with '/'
        // ESCAPE / ONLY
        $body = str_replace('/', '\/', $pattern);

        return '/' . $body . '/i';
    }

    /**
     * Clear the regex cache
     */
    public function clearCache(): void
    {
        $this->regexCache = [];
        $this->regexErrorCount = 0;
    }

    /**
     * Get regex cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_size' => count($this->regexCache),
            'error_count' => $this->regexErrorCount
        ];
    }

    /**
     * Validate a regex pattern
     * 
     * @param string $pattern The pattern to validate
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public function validatePattern(string $pattern): array
    {
        try {
            $wrappedPattern = $this->wrapPattern($pattern);
            $previousError = error_reporting(0);
            $result = @preg_match($wrappedPattern, '');
            error_reporting($previousError);

            if ($result === false) {
                return [
                    'valid' => false,
                    'error' => preg_last_error_msg()
                ];
            }

            return [
                'valid' => true,
                'error' => null
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
