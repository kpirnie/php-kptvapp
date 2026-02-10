<?php

/**
 * KPT Router - HTTP Methods Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure it doesn't already exist
if (! trait_exists('RouterRouteHandler')) {

    /**
     * KPT Router Route Handler Trait
     *
     * Provides comprehensive route registration and management functionality
     * including HTTP method handlers, middleware integration, and route resolution.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait RouterRouteHandler
    {
        // registered routes by HTTP method
        private array $routes = [];

        // middleware definitions registry
        private array $middlewareDefinitions = [];

        /**
         * Register middleware definitions
         *
         * Registers multiple middleware definitions that can be referenced
         * by name in route configurations.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $definitions Array of middleware name => callable pairs
         * @return self Returns the router instance for method chaining
         */
        public function registerMiddlewareDefinitions(array $definitions): self
        {

            // merge with existing middleware definitions
            $this->middlewareDefinitions = array_merge($this->middlewareDefinitions, $definitions);
            return $this;
        }

        /**
         * Register routes from array definition
         *
         * Registers multiple routes from an array of route definitions,
         * allowing for bulk route registration.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $routes Array of route definitions
         * @return self Returns the router instance for method chaining
         */
        public function registerRoutes(array $routes): self
        {

            // loop through each route and register it
            foreach ($routes as $route) {
                $this->registerSingleRoute($route);
            }

            // return for chaining
            return $this;
        }

        /**
         * Register a single route from array definition
         *
         * Processes and registers a single route from an array definition
         * with validation and middleware support.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $route Route definition array
         * @return self Returns the router instance for method chaining
         * @throws InvalidArgumentException If route definition is invalid
         */
        private function registerSingleRoute(array $route): self
        {

            // validate required route properties
            if (! isset($route['method']) || ! isset($route['path']) || ! isset($route['handler'])) {
                throw new \InvalidArgumentException('Route must have method, path, and handler defined');
            }

            // extract route configuration
            $method = strtoupper($route['method']);
            $path = $route['path'];
            $handler = $route['handler'];
            $middlewares = $route['middleware'] ?? [];
            $should_cache = $route['should_cache'] ?? false;
            $cache_length = $route['cache_length'] ?? 3600;
            $data = $route['data'] ?? [];

            // make sure the cache flag is passed through
            $data['should_cache'] = $should_cache;
            $data['cache_length'] = $cache_length;

            // validate HTTP method
            if (! in_array($method, ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'TRACE', 'CONNECT'])) {
                Logger::error('Invalid HTTP Method', [$method]);
                throw new \InvalidArgumentException("Invalid HTTP method: {$method}");
            }

            // log route registration debug info
            Logger::debug("Route Registration", [
                'path' => $path,
                'method' => $method,
                'middlewares' => $middlewares,
                'handler' => $handler,
                'should_cache' => $should_cache
            ]);

            // resolve handler and wrap with middlewares
            $callableHandler = $this->resolveHandler($handler, $data);
            $wrappedHandler = $this->createWrappedHandler($callableHandler, $middlewares);

            // now add the route
            $this->addRoute($method, $path, $wrappedHandler);

            // return for chaining
            return $this;
        }

        /**
         * Add a route to the router
         *
         * Adds a route to the internal routes registry with proper path
         * sanitization and base path handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $method HTTP method
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return void Returns nothing
         */
        private function addRoute(string $method, string $path, callable $callback): void
        {

            // sanitize the path
            $path = self::sanitizePath($path);

            // build full path with base path
            $fullPath = $this->basePath === '/' ? $path : self::sanitizePath($this->basePath . $path);
            $fullPath = preg_replace('#/+#', '/', $fullPath);

            // register the route if not already exists
            if (! isset($this->routes[$method][$fullPath])) {
                $this->routes[$method][$fullPath] = $callback;
            }
        }

        /**
         * Register a single middleware definition
         *
         * Registers a named middleware that can be referenced in route
         * configurations by its name.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $name Middleware name
         * @param callable $middleware Middleware callable
         * @return self Returns the router instance for method chaining
         */
        public function registerMiddleware(string $name, callable $middleware): self
        {

            // register the middleware by name
            $this->middlewareDefinitions[$name] = $middleware;
            return $this;
        }

        /**
         * Get registered middleware definitions
         *
         * Returns all currently registered middleware definitions
         * for inspection or debugging purposes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of middleware definitions
         */
        public function getMiddlewareDefinitions(): array
        {

            // return all middleware definitions
            return $this->middlewareDefinitions;
        }

        /**
         * Get all registered routes
         *
         * Returns all registered routes organized by HTTP method
         * for inspection or debugging purposes.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @return array Returns array of routes grouped by HTTP method
         */
        public function getRoutes(): array
        {

            // return routes organized by HTTP method
            return [
                'GET' => array_keys($this->routes['GET'] ?? []),
                'POST' => array_keys($this->routes['POST'] ?? []),
                'PUT' => array_keys($this->routes['PUT'] ?? []),
                'PATCH' => array_keys($this->routes['PATCH'] ?? []),
                'DELETE' => array_keys($this->routes['DELETE'] ?? []),
                'HEAD' => array_keys($this->routes['HEAD'] ?? []),
                'TRACE' => array_keys($this->routes['TRACE'] ?? []),
                'CONNECT' => array_keys($this->routes['CONNECT'] ?? []),
            ];
        }

        /**
         * Register a GET route
         *
         * Registers a route that responds to HTTP GET requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function get(string $path, callable $callback): self
        {

            // register GET route
            $this->addRoute('GET', $path, $callback);
            return $this;
        }

        /**
         * Register a POST route
         *
         * Registers a route that responds to HTTP POST requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function post(string $path, callable $callback): self
        {

            // register POST route
            $this->addRoute('POST', $path, $callback);
            return $this;
        }

        /**
         * Register a PUT route
         *
         * Registers a route that responds to HTTP PUT requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function put(string $path, callable $callback): self
        {

            // register PUT route
            $this->addRoute('PUT', $path, $callback);
            return $this;
        }

        /**
         * Register a PATCH route
         *
         * Registers a route that responds to HTTP PATCH requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function patch(string $path, callable $callback): self
        {

            // register PATCH route
            $this->addRoute('PATCH', $path, $callback);
            return $this;
        }

        /**
         * Register a DELETE route
         *
         * Registers a route that responds to HTTP DELETE requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function delete(string $path, callable $callback): self
        {

            // register DELETE route
            $this->addRoute('DELETE', $path, $callback);
            return $this;
        }

        /**
         * Register a HEAD route
         *
         * Registers a route that responds to HTTP HEAD requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function head(string $path, callable $callback): self
        {

            // register HEAD route
            $this->addRoute('HEAD', $path, $callback);
            return $this;
        }

        /**
         * Register a TRACE route
         *
         * Registers a route that responds to HTTP TRACE requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function trace(string $path, callable $callback): self
        {

            // register TRACE route
            $this->addRoute('TRACE', $path, $callback);
            return $this;
        }

        /**
         * Register a CONNECT route
         *
         * Registers a route that responds to HTTP CONNECT requests
         * with the specified path and handler.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Route path
         * @param callable $callback Route handler
         * @return self Returns the router instance for method chaining
         */
        public function connect(string $path, callable $callback): self
        {

            // register CONNECT route
            $this->addRoute('CONNECT', $path, $callback);
            return $this;
        }
    }
}
