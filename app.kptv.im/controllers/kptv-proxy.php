<?php

/**
 * Live Stream Proxy for CORS bypass
 * Handles HLS, MPEG-TS, and live video streams
 * PHP 8.4 compatible - NO CACHING/DOWNLOADS
 */

// no direct access
defined('KPTV_PATH') || die('Direct Access is not allowed!');

// Configuration
define('ALLOWED_DOMAINS', [
    // Add your allowed stream domains here for security
    // Example: 'stream.example.com',
    // Leave empty to allow all (not recommended for production)
]);

define('MAX_REDIRECTS', 5);
define('STREAM_CHUNK_SIZE', 4096); // 4KB chunks for streaming

/**
 * KPTV_Proxy
 * 
 * Live Stream Proxy for CORS bypass
 * Handles HLS, MPEG-TS, and live video streams
 * PHP 8.4 compatible - NO CACHING/DOWNLOADS
 */
class KPTV_Proxy
{
    private string $url;
    private array $responseHeaders = [];

    /**
     * Main handler
     */
    public function handleStreamPlayback(): void
    {
        try {
            // Get and validate URL
            $this->url = htmlspecialchars_decode($_GET['url'] ?? '');

            if (empty($this->url)) {
                $this->sendError('No URL provided', 400);
                return;
            }

            // Validate URL
            if (!filter_var($this->url, FILTER_VALIDATE_URL)) {
                $this->sendError('Invalid URL', 400);
                return;
            }

            // Check domain whitelist if configured
            if (!empty(ALLOWED_DOMAINS) && !$this->isDomainAllowed()) {
                $this->sendError('Domain not allowed', 403);
                return;
            }

            // Handle OPTIONS request for CORS
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                $this->handleCorsOptions();
                return;
            }

            // Determine content type and handle accordingly
            $urlPath = parse_url($this->url, PHP_URL_PATH);
            $extension = strtolower(pathinfo($urlPath, PATHINFO_EXTENSION));

            // match the extension to determine how to handle the stream
            match ($extension) {
                'm3u8' => $this->handleM3U8(),
                'ts' => $this->streamDirect('video/mp2t'),
                default => $this->streamDirect()
            };
        } catch (Exception $e) {
            error_log('Stream proxy error: ' . $e->getMessage());
            $this->sendError('Proxy error', 500);
        }
    }

    /**
     * Handle M3U8 playlist files (process URLs but don't cache)
     */
    private function handleM3U8(): void
    {
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => MAX_REDIRECTS,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Accept: application/vnd.apple.mpegurl, application/x-mpegURL, */*'
            ]
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $content === false) {
            $this->sendError('Failed to fetch playlist', 502);
            return;
        }

        // Process playlist - convert relative URLs to absolute via proxy
        $processedContent = $this->processM3U8($content);

        // Send response with appropriate headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range');
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo $processedContent;
    }

    /**
     * Process M3U8 content to proxy URLs
     */
    private function processM3U8(string $content): string
    {
        $lines = explode("\n", $content);
        $baseUrl = dirname($this->url) . '/';
        $processed = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Keep empty lines and comments as-is
            if (empty($line) || str_starts_with($line, '#')) {
                $processed[] = $line;
                continue;
            }

            // Convert to absolute URL if relative
            if (!filter_var($line, FILTER_VALIDATE_URL)) {
                if (str_starts_with($line, '/')) {
                    $parsed = parse_url($this->url);
                    $line = $parsed['scheme'] . '://' . $parsed['host'] . $line;
                } else {
                    $line = $baseUrl . $line;
                }
            }

            // Proxy the URL
            $processed[] = '/proxy/stream?url=' . urlencode($line);
        }

        return implode("\n", $processed);
    }

    /**
     * Stream content directly without buffering
     */
    private function streamDirect(?string $forceContentType = null): void
    {
        // Build request headers
        $requestHeaders = [];

        // Pass through range header if present
        if (isset($_SERVER['HTTP_RANGE'])) {
            $requestHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
        }

        // Add user agent
        $requestHeaders[] = 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

        // Initialize cURL
        $ch = curl_init($this->url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => MAX_REDIRECTS,
            CURLOPT_BUFFERSIZE => STREAM_CHUNK_SIZE,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTPHEADER => $requestHeaders,
            CURLOPT_HEADERFUNCTION => [$this, 'captureResponseHeader'],
            CURLOPT_WRITEFUNCTION => [$this, 'streamResponseBody'],
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => [$this, 'checkAbort']
        ]);

        // Disable output buffering for live streaming
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Set CORS headers first
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type');
        header('Access-Control-Expose-Headers: Content-Length, Content-Range');

        // Prevent caching for live streams
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Override content type if specified
        if ($forceContentType) {
            header('Content-Type: ' . $forceContentType);
        }

        // Execute the stream
        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            error_log('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);
    }

    /**
     * Capture response headers from origin
     */
    private function captureResponseHeader($ch, string $header): int
    {
        $len = strlen($header);
        $header = trim($header);

        if (empty($header)) {
            return $len;
        }

        // Parse header
        if (preg_match('/^HTTP\//', $header)) {
            // Status line
            $parts = explode(' ', $header, 3);
            if (isset($parts[1])) {
                http_response_code((int)$parts[1]);
            }
        } else {
            // Regular header
            $parts = explode(':', $header, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);

                // Pass through certain headers
                $passthrough = [
                    'content-type',
                    'content-length',
                    'content-range',
                    'accept-ranges',
                    'etag',
                    'last-modified'
                ];

                if (in_array(strtolower($name), $passthrough)) {
                    header($name . ': ' . $value);
                }
            }
        }

        return $len;
    }

    /**
     * Stream response body directly to client
     */
    private function streamResponseBody($ch, string $data): int
    {
        echo $data;
        flush();
        return strlen($data);
    }

    /**
     * Check if connection was aborted
     */
    private function checkAbort($ch, $downloadTotal, $downloadNow, $uploadTotal, $uploadNow): int
    {
        if (connection_aborted()) {
            return 1; // Abort cURL
        }
        return 0; // Continue
    }

    /**
     * Handle CORS preflight OPTIONS request
     */
    private function handleCorsOptions(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Allow-Headers: Range, Origin, Content-Type, Accept');
        header('Access-Control-Max-Age: 86400');
        header('Content-Length: 0');
        http_response_code(204);
    }

    /**
     * Check if domain is allowed
     */
    private function isDomainAllowed(): bool
    {
        if (empty(ALLOWED_DOMAINS)) {
            return true; // Allow all if not configured
        }

        $host = parse_url($this->url, PHP_URL_HOST);
        return in_array($host, ALLOWED_DOMAINS);
    }

    /**
     * Send error response
     */
    private function sendError(string $message, int $code): void
    {
        http_response_code($code);
        header('Content-Type: text/plain');
        header('Access-Control-Allow-Origin: *');
        echo $message;
    }
}

// Initialize and run
//$proxy = new LiveStreamProxy();
//$proxy->handleStreamPlayback();