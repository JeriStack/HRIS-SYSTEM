<?php
/**
 * Global Core Helper Functions
 * Employee HRIS & Leave Management System
 */

if (!function_exists('config')) {
    /**
     * Retrieve configuration settings by dot notation or top-level key.
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $configs = [];

        $parts = explode('.', $key);
        $file = $parts[0];

        if (!isset($configs[$file])) {
            $filePath = __DIR__ . '/../config/' . $file . '.php';
            if (file_exists($filePath)) {
                $configs[$file] = require $filePath;
            } else {
                $configs[$file] = [];
            }
        }

        if (count($parts) === 1) {
            return $configs[$file] ?? $default;
        }

        $current = $configs[$file];
        for ($i = 1; $i < count($parts); $i++) {
            $segment = $parts[$i];
            if (is_array($current) && array_key_exists($segment, $current)) {
                $current = $current[$segment];
            } else {
                return $default;
            }
        }

        return $current;
    }
}

if (!function_exists('e')) {
    /**
     * Escape HTML output safely using htmlspecialchars.
     */
    function e(?string $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Generate a relative or absolute URL based on the application base path.
     */
    function url(string $path = ''): string
    {
        $baseUrl = rtrim((string)config('config.base_url', ''), '/');
        $cleanPath = ltrim($path, '/');
        return $cleanPath ? $baseUrl . '/' . $cleanPath : $baseUrl;
    }
}

if (!function_exists('asset')) {
    /**
     * Generate an asset URL.
     */
    function asset(string $path): string
    {
        return url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    /**
     * Perform an HTTP redirect and terminate script execution.
     */
    function redirect(string $url, int $statusCode = 302): void
    {
        // If not a full URL with scheme, build application URL
        if (!preg_match('#^https?://#i', $url)) {
            $url = url($url);
        }

        http_response_code($statusCode);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('json_response')) {
    /**
     * Output a JSON response with status code and headers.
     */
    function json_response(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
