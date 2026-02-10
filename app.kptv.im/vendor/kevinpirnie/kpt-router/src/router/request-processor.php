<?php

/**
 * KPT Router - Core Routing Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure the trait doesn't exist first
if (! trait_exists('RouterRequestProcessor')) {

    /**
     * KPT Router Request Processor Trait
     *
     * Provides core request processing functionality including route matching,
     * middleware execution, rate limiting, and error handling for the router system.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait RouterRequestProcessor
    {
        // 404 not found callback handler
        private $notFoundCallback;

        // current request method
        private static string $currentMethod = '';

        // current request path
        private static string $currentPath = '';

        // current route parameters
        private static array $currentParams = [];

        /**
         * Set 404 Not Found handler
         *
         * Configures a custom callback function to handle 404 Not Found
         * responses when no matching route is found.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param callable $callback Handler function for 404 responses
         * @return self Returns the router instance for method chaining
         */
        public function notFound(callable $callback): self
        {

            // set the not found callback
            $this->notFoundCallback = $callback;
            return $this;
        }

        /**
         * Dispatch the router to handle current request
         *
         * Main entry point for request processing that handles middleware execution,
         * rate limiting, route matching, and error handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing
         */
        public function dispatch(): void
        {

            // try to process the request
            try {
                // get current request details
                self::$currentMethod = $this->getRequestMethod();
                self::$currentPath = $this->getRequestUri();

                // execute middlewares first
                if ($this->executeMiddlewares($this->middlewares) === false) {
                    return;
                }

                // apply rate limiting if enabled
                if ($this->rateLimitingEnabled) {
                    $this->applyRateLimiting();
                }

                // find matching route handler
                $handler = $this->findRouteHandler(self::$currentMethod, self::$currentPath);

                // execute handler or 404 callback
                if ($handler) {
                    // set current params and execute handler
                    self::$currentParams = $handler['params'];
                    $this->executeHandler($handler['callback'], $handler['params']);

                    // check if we have a custom 404 handler
                } elseif ($this->notFoundCallback) {
                    $this->executeHandler($this->notFoundCallback);

                    // default 404 response
                } else {
                    error_log("No handler found for " . self::$currentMethod . " " . self::$currentPath);
                    $this->sendNotFoundResponse();
                }

                // whoopsie... handle dispatch errors
            } catch (\Throwable $e) {
                Logger::error("Dispatch error", ['error' => $e->getMessage()]);
                $this->handleError($e);
            }
        }

        /**
         * Get the request URI
         *
         * Extracts and sanitizes the request URI from the current request
         * for use in route matching.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the sanitized request URI
         */
        private function getRequestUri(): string
        {

            // parse and sanitize the URI
            $uri = parse_url((self::getUserUri()), PHP_URL_PATH);
            return self::sanitizePath($uri);
        }

        /**
         * Get the request method
         *
         * Determines the HTTP request method from server variables
         * with validation and fallback to GET.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return string Returns the HTTP request method
         */
        private function getRequestMethod(): string
        {

            // get method from server variables
            $method = $_SERVER['REQUEST_METHOD'];

            // validate method and return
            return in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD', 'TRACE', 'CONNECT'])
                ? $method
                : 'GET';
        }

        /**
         * Find route handler for current request
         *
         * Searches through registered routes to find a matching handler
         * for the current request method and URI.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $method HTTP request method
         * @param string $uri Request URI to match
         * @return array|null Returns array containing handler and parameters or null if not found
         */
        private function findRouteHandler(string $method, string $uri): ?array
        {

            // clean up the URI - remove query string
            $uri = strtok($uri, '?');

            // Normalize the URI - ensure it starts with / and has no trailing slash (unless it's root)
            $uri = '/' . trim($uri, '/');
            if ($uri === '/') {
                // Keep root as is
            } else {
                // Remove trailing slash for all other paths
                $uri = rtrim($uri, '/');
            }

            // log debug information
            Logger::debug("Route Match", [
                'method' => $method,
                'uri' => $uri,
                'available_routes' => array_keys($this->routes[$method] ?? [])
            ]);

            // check for exact match first
            if (isset($this->routes[$method][$uri])) {
                return [
                    'callback' => $this->routes[$method][$uri],
                    'params' => []
                ];
            }

            // Also try with trailing slash (for compatibility)
            $uriWithSlash = $uri . '/';
            if ($uri !== '/' && isset($this->routes[$method][$uriWithSlash])) {
                return [
                    'callback' => $this->routes[$method][$uriWithSlash],
                    'params' => []
                ];
            }

            // try pattern matching for dynamic routes
            foreach ($this->routes[$method] ?? [] as $routePath => $callback) {
                // convert route to regex pattern
                $pattern = $this->convertRouteToPattern($routePath);

                // log pattern testing
                Logger::debug("Testing route pattern", [
                    'pattern' => $pattern,
                    'route_path' => $routePath,
                    'testing_against' => $uri
                ]);

                // test pattern against URI (with and without trailing slash)
                if (
                    preg_match($pattern, $uri, $matches) ||
                    ($uri !== '/' && preg_match($pattern, $uriWithSlash, $matches))
                ) {
                    // log successful match
                    Logger::debug("ROUTE MATCHED!", [
                        'route_path' => $routePath,
                        'matches' => $matches,
                        'callback_type' => gettype($callback)
                    ]);

                    // return handler with extracted parameters
                    return [
                        'callback' => $callback,
                        'params' => array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY)
                    ];
                }
            }

            // check for method override in POST requests
            if ($method === 'POST' && isset($_POST['_method'])) {
                // get override method
                $overrideMethod = strtoupper($_POST['_method']);

                // check if override route exists
                if (isset($this->routes[$overrideMethod][$uri])) {
                    return [
                        'callback' => $this->routes[$overrideMethod][$uri],
                        'params' => []
                    ];
                }
            }

            // log no match found
            Logger::error("No Route Matched", ['uri' => $uri]);

            // no route found
            return null;
        }

        /**
         * Convert route path to regex pattern
         *
         * Transforms a route path with parameter placeholders into
         * a regular expression pattern for matching.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $routePath Route path to convert
         * @return string Returns the regex pattern
         */
        private function convertRouteToPattern(string $routePath): string
        {

            // convert parameter placeholders to named capture groups
            return '#^' . preg_replace('/\{([a-z][a-z0-9_]*)\}/i', '(?P<$1>[^/]+)', $routePath) . '$#i';
        }

        /**
         * Execute route handler
         *
         * Invokes the matched route handler with the extracted parameters
         * and handles the response appropriately.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param callable $handler Handler function to execute
         * @param array $params Parameters to pass to the handler
         * @return void Returns nothing
         */
        private function executeHandler(callable $handler, array $params = []): void
        {

            // try to execute the handler
            try {
                // get current route info and execute handler
                $currentRoute = self::getCurrentRoute();
                $result = call_user_func_array($handler, $params);

                // handle string responses
                if (is_string($result)) {
                    echo $result;

                    // log unexpected return types
                } elseif ($result !== null) {
                    error_log("Unexpected return type from handler: " . gettype($result));
                }

                // whoopsie... handle handler execution errors
            } catch (\Throwable $e) {
                Logger::error("Handler execution failed", ['error' => $e->getMessage()]);
                $this->handleError($e);
            }
        }

        /**
         * Send 404 Not Found response
         *
         * Sends a standard 404 Not Found HTTP response with basic
         * HTML content when no route matches.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return void Returns nothing (exits execution)
         */
        private function sendNotFoundResponse(): void
        {

            // set 404 status and send basic response
            http_response_code(404);
            header('Content-Type: text/html');
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        /**
         * Handle errors
         *
         * Centralized error handling that logs errors and sends appropriate
         * HTTP response codes with user-friendly messages.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param Throwable $e Exception to handle
         * @return void Returns nothing (exits execution)
         */
        private function handleError(\Throwable $e): void
        {

            // log the error with stack trace
            Logger::error('Router error', ['error' => $e->getMessage()]);

            // determine appropriate HTTP status code
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            http_response_code($code);

            // send error message based on display_errors setting
            if (ini_get('display_errors')) {
                echo "Error {$code}: " . $e->getMessage();
            } else {
                echo "An error occurred. Please try again later.";
            }

            // exit execution
            exit;
        }

        /**
         * Get information about current matched route
         *
         * Returns comprehensive information about the currently matched
         * route including method, path, parameters, and match status.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return object Returns object containing route information
         */
        public static function getCurrentRoute(): object
        {

            // return route information object
            return (object)[
                'method' => self::$currentMethod,
                'path' => self::$currentPath,
                'params' => self::$currentParams,
                'matched' => ! empty(self::$currentMethod) && self::$currentPath !== ''
            ];
        }
    }
}
