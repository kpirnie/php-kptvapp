<?php

/**
 * KPT Router - Middleware Handling Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure it doesn't already exist
if (! trait_exists('RouterMiddlewareHandler')) {

    /**
     * KPT Router - Middleware Handling Trait
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait RouterMiddlewareHandler
    {
        /** @var array Hold the internal middleware array */
        private array $middlewares = [];

        /**
         * Add global middleware
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param callable $middleware Middleware function
         * @return self
         */
        public function addMiddleware(callable $middleware): self
        {

            // add it to the array
            $this->middlewares[] = $middleware;
            Logger::debug("Added to middleware", ['callable' => $middleware]);
            return $this;
        }

        /**
         * Execute middlewares
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param array $middlewares Array of middlewares to execute
         * @return bool True if all middlewares passed, false if one failed
         */
        private function executeMiddlewares(array $middlewares): bool
        {

            // loop over all the declared middlewares
            foreach ($middlewares as $middleware) {
                // debug logging
                Logger::debug("Executing to middleware", ['callable' => $middleware]);

                // if the callable execution is false, return false
                if ($middleware() === false) {
                    return false;
                }
            }

            // default return
            return true;
        }

        /**
         * Resolve middleware to callable
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param mixed $middleware Middleware to resolve
         * @return callable|null Resolved middleware or null if cannot be resolved
         */
        private function resolveMiddleware($middleware): ?callable
        {

            // if the middleware is indeed callable, just return it
            if (is_callable($middleware)) {
                return $middleware;
            }

            // if the middleware is a tring
            if (is_string($middleware)) {
                // make sure it's actually set as a middleware definition first
                if (isset($this->middlewareDefinitions[$middleware])) {
                    // hold the definition
                    $definition = $this->middlewareDefinitions[$middleware];

                    // if it's a string, return the resolved string
                    if (is_string($definition)) {
                        return $this->resolveStringMiddleware($definition);
                    }

                    // debug logging
                    Logger::debug("Resolve Middleware Definition", ['definition' => $definition]);

                    // return the definition
                    return $definition;
                }

                // debug logging
                Logger::debug("Resolve Middlewared", ['middleware' => $middleware]);

                // return the resolved string
                return $this->resolveStringMiddleware($middleware);
            }

            // couldn't resolve it, so log the error
            Logger::error("Could Not Resolve Middleware", ['middleware' => $middleware]);
            return null;
        }

        /**
         * Resolve string middleware
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $middleware Middleware string to resolve
         * @return callable|null Resolved middleware or null if unknown type
         */
        private function resolveStringMiddleware(string $middleware): ?callable
        {

            // Check if it's a registered middleware definition, and return if it is
            if (isset($this->middlewareDefinitions[$middleware])) {
                return $this->middlewareDefinitions[$middleware];
            }

            // If no middleware found, log warning and return null
            Logger::error("Middleware Not Found", ['middleware' => $middleware]);
            return null;
        }

        /**
         * Create wrapped handler with middleware
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param callable $handler Original handler
         * @param array $middlewares Middlewares to wrap
         * @return callable Wrapped handler
         */
        private function createWrappedHandler(callable $handler, array $middlewares): callable
        {

            // return the called middleware with it's parameters
            return function (...$params) use ($handler, $middlewares) {

                // loop over them
                foreach ($middlewares as $middleware) {
                    // resolve the middleware
                    $middlewareCallable = $this->resolveMiddleware($middleware);

                    // if it is callable and run, just return
                    if ($middlewareCallable && $middlewareCallable() === false) {
                        return;
                    }
                }

                // debug logging
                // phpcs:ignore Generic.Files.LineLength.TooLong
                Logger::debug("Middleware Handler", ['middlewares' => $middlewares, 'handler' => $handler, 'params' => $params,]);

                // return the results from the callable handler
                return call_user_func_array($handler, $params);
            };
        }
    }
}
