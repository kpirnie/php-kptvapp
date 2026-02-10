<?php

/**
 * KPT Router - Handler Resolution Trait
 *
 * @since 8.4
 * @author Kevin Pirnie <me@kpirnie.com>
 * @package KP Library
 */

// throw it under my namespace
namespace KPT;

// make sure it doesn't already exist
if (! trait_exists('RouterResponseHandler')) {

    /**
     * KPT Router Response Handler Trait
     *
     * Provides comprehensive response handling functionality including view rendering,
     * controller resolution, and template management for the router system.
     *
     * @since 8.4
     * @author Kevin Pirnie <me@kpirnie.com>
     * @package KP Library
     */
    trait RouterResponseHandler
    {
        // views directory path
        private string $viewsPath = '';

        // shared view data
        private array $viewData = [];

        /**
         * Set the views directory path
         *
         * Configures the base directory path where view template files
         * are located for rendering responses.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $path Path to views directory
         * @return self Returns the router instance for method chaining
         */
        public function setViewsPath(string $path): self
        {

            // set the views path without trailing slash
            $this->viewsPath = rtrim($path, '/');
            return $this;
        }

        /**
         * Render a view template with data
         *
         * Loads and renders a view template file with the provided data,
         * using output buffering for clean content capture.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $template View file path (relative to views directory)
         * @param array $data Data to pass to the view
         * @return string Returns the rendered content
         * @throws RuntimeException If view file not found
         */
        public function view(string $template, array $data = []): string
        {

            // build full template path
            $templatePath = $this->viewsPath . '/' . ltrim($template, '/');

            // check if template file exists
            if (! file_exists($templatePath)) {
                Logger::error('View template not found', ['file' => $templatePath]);
                throw new \RuntimeException("View template not found: {$templatePath}");
            }

            // extract view data and shared data
            extract(array_merge($this->viewData, $data), EXTR_SKIP);
            ob_start();

            // try to render the template
            try {
                include $templatePath;
                return ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                Logger::error("View rendering failed", ['error' => $e->getMessage()]);
                throw $e;
            }
        }

        /**
         * Share data with all views
         *
         * Stores data that will be available to all view templates,
         * supporting both single key-value pairs and arrays of data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string|array $key Data key or array of key-value pairs
         * @param mixed $value Value if key is string
         * @return self Returns the router instance for method chaining
         */
        public function share($key, $value = null): self
        {

            // handle array of data or single key-value pair
            if (is_array($key)) {
                $this->viewData = array_merge($this->viewData, $key);
            } else {
                $this->viewData[$key] = $value;
            }

            // return for chaining
            return $this;
        }

        /**
         * Resolve handler to callable
         *
         * Converts various handler formats (strings, controller references, etc.)
         * into executable callable functions for route handling.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param mixed $handler Handler to resolve
         * @param array $data Additional handler data
         * @return callable Returns the resolved handler
         * @throws InvalidArgumentException If handler cannot be resolved
         */
        private function resolveHandler($handler, array $data = []): callable
        {

            // return if already callable
            if (is_callable($handler)) {
                return $handler;
            }

            // handle string-based handlers
            if (is_string($handler)) {
                // check for type-prefixed handlers (view:, controller:)
                if (strpos($handler, ':') !== false) {
                    // split type and target
                    list($type, $target) = explode(':', $handler, 2);

                    // make sure we have a type
                    if (!in_array($type, ['view', 'controller'])) {
                        Logger::error("Unknown handler type", ['type' => $type]);
                    }

                    // handle based on type
                    return match ($type) {
                        'view' => $this->createViewHandler($target, $data),
                        'controller' => $this->createControllerHandler($target, $data),
                        default => throw new \InvalidArgumentException("Unknown handler type: {$type}")
                    };
                }

                // Check if it's a controller format (Class@method)
                if (strpos($handler, '@') !== false) {
                    return $this->createControllerHandler($handler);
                }

                // default to view handler
                return $this->createViewHandler($handler, $data);
            }

            // handler format not supported
            Logger::error("Unknown handler type", ['Handler must be callable or string']);
            throw new \InvalidArgumentException('Handler must be callable or string');
        }

        /**
         * Create view handler
         *
         * Creates a callable handler that renders a view template with
         * route parameters and additional data.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $viewPath Path to view file
         * @param array $data Additional view data
         * @return callable Returns the view handler function
         */
        private function createViewHandler(string $viewPath, array $data = []): callable
        {

            // return the results of creating the view
            return function (...$params) use ($viewPath, $data) {

                // setup view data array
                $viewData = [];

                // get current route and extract parameters
                $currentRoute = self::getCurrentRoute();

                // loop the route parameters
                foreach ($currentRoute->params as $key => $value) {
                    $viewData[$key] = $value;
                }

                // include current route object if requested
                if (isset($data['currentRoute']) && $data['currentRoute']) {
                    $viewData['currentRoute'] = $currentRoute;
                }

                // merge with additional data
                $viewData = array_merge($viewData, $data);

                // Create a cache key based on view path and data
                $cache_key = 'view_cache_' . md5($viewPath . serialize($viewData));

                // check if we have a cachedel querystring or post
                if (isset($_GET['cachedel']) || isset($_POST)) {
                    Cache::delete($cache_key);
                }

                // should the view be cached?
                $should_cache = (isset($viewData['should_cache']) && $viewData['should_cache']) ?? false;

                // if caching is enabled, try to get from cache first
                if ($should_cache) {
                    $cached_content = Cache::get($cache_key);

                    // if we have content, log the cache hit then return it
                    if ($cached_content !== false) {
                        Logger::debug("View cache HIT for template: {$viewPath}");
                        return $cached_content;
                    }

                    // log the cache miss
                    Logger::debug("View cache MISS for template: {$viewPath}");
                }

                // render the view
                $content = $this->view($viewPath, $viewData);

                // cache if needed, for at least 1 hour
                if ($should_cache) {
                    Cache::set($cache_key, $content, $viewData['cache_length'] ?? 3600);
                }

                // return the content
                return $content;
            };
        }

        /**
         * Create controller handler
         *
         * Creates a callable handler that instantiates a controller class
         * and calls the specified method with route parameters.
         *
         * @since 8.4
         * @author Kevin Pirnie <me@kpirnie.com>
         *
         * @param string $controller Controller identifier (e.g., "UserController@show")
         * @param array $data Additional handler data
         * @return callable Returns the controller handler function
         * @throws InvalidArgumentException If controller format is invalid
         * @throws RuntimeException If controller class doesn't exist or method is not callable
         */
        private function createControllerHandler(string $controller, array $data = []): callable
        {

            // return closure that handles controller execution
            return function (...$params) use ($controller, $data) {

                // Create a cache key based on controller, method, and params
                $cache_key = 'cont_cache_' . md5($controller . serialize($params));

                // check if we have a cachedel querystring or post
                if (isset($_GET['cachedel']) || isset($_POST)) {
                    // delete the cached item first
                    Cache::delete($cache_key);
                }

                // should the controller be cached?
                $should_cache = (isset($data['should_cache']) && $data['should_cache']) ?? false;

                // If caching is enabled, try to get from cache first
                if ($should_cache) {
                    // Try to get cached content
                    $cached_content = Cache::get($cache_key);
                    if ($cached_content !== false) {
                        Logger::debug("Controller cache HIT for: {$controller}");
                        return $cached_content;
                    }

                    // debug log for miss
                    Logger::debug("Controller cache MISS for: {$controller}");
                }

                // validate controller format
                if (! strpos($controller, '@')) {
                    Logger::error('Invalid Controller Format', [$controller]);
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    throw new \InvalidArgumentException("Controller format must be 'ClassName@methodName', got: {$controller}");
                }

                // split class and method
                list($class, $method) = explode('@', $controller, 2);

                // Trim any whitespace
                $class = trim($class);
                $method = trim($method);

                // validate class and method names
                if (empty($class) || empty($method)) {
                    Logger::error('Missing Class or Method', [$controller]);
                    // phpcs:ignore Generic.Files.LineLength.TooLong
                    throw new \InvalidArgumentException("Both controller class and method must be specified: {$controller}");
                }

                // Check if class exists
                if (! class_exists($class)) {
                    Logger::error('Missing Class', [$class]);
                    throw new \RuntimeException("Controller class not found: {$class}");
                }

                // Instantiate the controller
                $controllerInstance = new $class();

                // Check if method exists and is callable
                if (! method_exists($controllerInstance, $method)) {
                    Logger::error('Missing Method', [$method]);
                    throw new \RuntimeException("Method '{$method}' not found in controller '{$class}'");
                }

                // verify method is callable
                if (! is_callable([$controllerInstance, $method])) {
                    Logger::error('Method Not Callable', [$method]);
                    throw new \RuntimeException("Method '{$method}' is not callable in controller '{$class}'");
                }

                // Call the controller method with parameters
                $result = call_user_func_array([$controllerInstance, $method], $params);

                // check of the cache data exists, and if it's true cache it
                if ($should_cache) {
                    Cache::set($cache_key, $result, $data['cache_length'] ?? 3600);
                }

                // Clean up
                unset($controllerInstance);

                // return the result
                return $result;
            };
        }
    }
}
