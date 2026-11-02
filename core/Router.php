<?php
/**
 * Core Application Router
 * Employee HRIS & Leave Management System
 */

namespace Core;

use RuntimeException;

class Router
{
    /**
     * Registered application routes grouped by HTTP method.
     * Format: [ 'GET' => [ ['path' => '/...', 'pattern' => '#...#', 'handler' => ..., 'middleware' => [...] ] ] ]
     */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'DELETE' => [],
        'PATCH'  => [],
    ];

    /**
     * Register a GET route.
     */
    public function get(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $path, array|callable $handler, array $middleware = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middleware);
    }

    /**
     * Add a route with regex compilation for parameterized path segments.
     */
    private function addRoute(string $method, string $path, array|callable $handler, array $middleware = []): self
    {
        $normalizedPath = $this->normalizePath($path);

        // Convert {param} placeholders into regex capturing groups: ([^/]+)
        $pattern = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '([^/]+)', $normalizedPath);
        $regex = '#^' . $pattern . '$#';

        $this->routes[$method][] = [
            'path'       => $normalizedPath,
            'regex'      => $regex,
            'handler'    => $handler,
            'middleware' => $middleware,
        ];

        return $this;
    }

    /**
     * Normalize a route path or request URI to standard leading slash and no trailing slash.
     */
    private function normalizePath(string $path): string
    {
        $clean = trim($path, '/');
        return $clean === '' ? '/' : '/' . $clean;
    }

    /**
     * Resolve the application-relative request path from the server request URI.
     */
    public function resolveRequestPath(?string $requestUri = null): string
    {
        $uri = $requestUri ?? ($_SERVER['REQUEST_URI'] ?? '/');

        // Extract path component discarding query parameters
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';

        // Strip project base directory prefix if served in subfolder (e.g. /GitHub_Clone_File_Projects/HRIS-SYSTEM/public)
        $baseUrl = config('config.base_url', '');
        $basePath = parse_url($baseUrl, PHP_URL_PATH);

        if ($basePath && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        // Also handle if accessed without /public in base path (e.g. /GitHub_Clone_File_Projects/HRIS-SYSTEM/)
        if ($basePath) {
            $baseWithoutPublic = preg_replace('#/public/?$#', '', $basePath);
            if ($baseWithoutPublic && $baseWithoutPublic !== '/' && str_starts_with($path, $baseWithoutPublic)) {
                $path = substr($path, strlen($baseWithoutPublic));
                if (str_starts_with($path, '/public')) {
                    $path = substr($path, strlen('/public'));
                }
            }
        }

        // Also handle direct index.php in URI if present
        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php'));
        }

        return $this->normalizePath($path);
    }

    /**
     * Dispatch the current request to the matched route and handler.
     */
    public function dispatch(?string $method = null, ?string $path = null): mixed
    {
        $httpMethod = strtoupper($method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestPath = $path !== null ? $this->normalizePath($path) : $this->resolveRequestPath();

        // Handle Cross-Origin Resource Sharing (CORS) for API and docs routes
        $isApi = str_starts_with($requestPath, '/api/') || str_starts_with($requestPath, '/docs');
        if ($isApi) {
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            $allowOrigin = false;

            if ($origin !== '') {
                // Relaxed localhost CORS only for explicit non-production environments
                $isLocalDev = in_array(config('config.app_env', 'production'), ['development', 'local', 'testing'], true);
                if ($isLocalDev && preg_match('#^https?://(localhost|127\.0\.0\.1)(:\d+)?$#i', $origin)) {
                    $allowOrigin = true;
                } else {
                    $baseUrl = getenv('BASE_URL') ?: 'http://localhost:8080';
                    $parsedBase = parse_url($baseUrl, PHP_URL_HOST);
                    $parsedOrigin = parse_url($origin, PHP_URL_HOST);
                    if ($parsedBase && $parsedOrigin && strcasecmp($parsedBase, $parsedOrigin) === 0) {
                        $allowOrigin = true;
                    }
                }

                if ($allowOrigin) {
                    header("Access-Control-Allow-Origin: {$origin}");
                    header("Access-Control-Allow-Credentials: true");
                    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
                    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, Accept, Origin");
                    header("Access-Control-Max-Age: 86400");
                }
            }

            if ($httpMethod === 'OPTIONS') {
                if (!$allowOrigin && $origin === '') {
                    header("Access-Control-Allow-Origin: *");
                    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");
                    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token, Accept, Origin");
                }
                http_response_code(204);
                exit;
            }
        }

        $pathMatchedOtherMethod = false;

        // 1. Check routes for requested HTTP method
        if (isset($this->routes[$httpMethod])) {
            foreach ($this->routes[$httpMethod] as $route) {
                if (preg_match($route['regex'], $requestPath, $matches)) {
                    array_shift($matches); // Remove full match

                    // Execute registered route middlewares
                    foreach ($route['middleware'] as $mw) {
                        $middlewareInstance = is_string($mw) ? new $mw() : $mw;
                        if ($middlewareInstance instanceof Middleware) {
                            if (!$middlewareInstance->handle()) {
                                return null; // Middleware intercepted request
                            }
                        }
                    }

                    // Execute route handler
                    return $this->executeHandler($route['handler'], $matches);
                }
            }
        }

        // 2. Check if route path exists for other methods (405 Method Not Allowed)
        foreach ($this->routes as $m => $routesList) {
            if ($m !== $httpMethod) {
                foreach ($routesList as $route) {
                    if (preg_match($route['regex'], $requestPath)) {
                        $pathMatchedOtherMethod = true;
                        break 2;
                    }
                }
            }
        }

        if ($pathMatchedOtherMethod) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_keys($this->routes)));
            $this->renderError(405, "405 Method Not Allowed", "The requested method {$httpMethod} is not permitted for path: {$requestPath}");
            return null;
        }

        // 3. No match found: HTTP 404 Not Found
        http_response_code(404);
        $this->renderError(404, "404 Page Not Found", "The requested resource was not found: {$requestPath}");
        return null;
    }

    /**
     * Execute controller action or closure.
     */
    private function executeHandler(array|callable $handler, array $params): mixed
    {
        if (is_callable($handler)) {
            return call_user_func_array($handler, $params);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$controllerClass, $method] = $handler;

            // Handle fully qualified or alias class name
            if (!class_exists($controllerClass)) {
                // Try prepending App\Controllers\ if not found
                $namespaced = 'App\\Controllers\\' . ltrim($controllerClass, '\\');
                if (class_exists($namespaced)) {
                    $controllerClass = $namespaced;
                } else {
                    throw new RuntimeException("Controller class not found: {$controllerClass}");
                }
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $method)) {
                throw new RuntimeException("Method [{$method}] does not exist on controller [{$controllerClass}].");
            }

            return call_user_func_array([$controller, $method], $params);
        }

        throw new RuntimeException("Invalid route handler format.");
    }

    /**
     * Render a clean HTTP error page.
     */
    private function renderError(int $code, string $title, string $message): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $acceptHeader = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (str_contains($requestUri, '/api/') || str_contains($acceptHeader, 'application/json')) {
            json_response([
                'status'  => 'error',
                'code'    => $code,
                'message' => $message,
            ], $code);
            return;
        }

        // If an explicit 404 view exists in app/views/errors/{code}.php, render it
        $customView = __DIR__ . "/../app/views/errors/{$code}.php";
        if (file_exists($customView)) {
            require $customView;
            return;
        }

        // Default clean enterprise error layout
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= e($title) ?> - HRIS</title>
            <style>
                body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #F8FAFC; color: #0F172A; display: flex; align-items: center; justify-content: center; min-height: 80vh; margin: 0; padding: 20px; }
                .error-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 12px; padding: 36px; max-width: 520px; text-align: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
                .code { font-size: 48px; font-weight: 800; color: #2563EB; margin: 0; line-height: 1; }
                h1 { font-size: 20px; margin: 16px 0 8px 0; color: #0F172A; }
                p { font-size: 14px; color: #64748B; line-height: 1.6; margin-bottom: 24px; }
                a { display: inline-block; background: #2563EB; color: white; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; font-size: 14px; }
            </style>
        </head>
        <body>
            <div class="error-card">
                <div class="code"><?= $code ?></div>
                <h1><?= e($title) ?></h1>
                <p><?= e($message) ?></p>
                <a href="<?= e(url('/')) ?>">Return to Landing Page</a>
            </div>
        </body>
        </html>
        <?php
    }
}
