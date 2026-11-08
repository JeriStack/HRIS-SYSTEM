<?php
/**
 * Core Base Controller
 * Employee HRIS & Leave Management System
 */

namespace Core;

use RuntimeException;

abstract class Controller
{
    /**
     * Render a view template with optional layout wrapper.
     *
     * @param string $viewPath Dot-notated or slashed view name (e.g., 'home.index' or 'employees/directory')
     * @param array $data Associative array of data variables passed to view
     * @param string|null $layout Layout name in app/views/layouts/ (e.g., 'main', 'auth'), or null for standalone
     */
    protected function view(string $viewPath, array $data = [], ?string $layout = 'main'): void
    {
        // Prevent directory traversal attacks
        if (str_contains($viewPath, '..')) {
            throw new RuntimeException("Invalid view path: Directory traversal is prohibited.");
        }

        // Convert dot notation to directory slashes (e.g. 'employees.index' -> 'employees/index')
        $relativePath = str_replace('.', '/', $viewPath);
        $viewFile = __DIR__ . '/../app/views/' . ltrim($relativePath, '/') . '.php';

        if (!file_exists($viewFile)) {
            throw new RuntimeException("View template not found: [{$viewPath}] (resolved to: {$viewFile})");
        }

        // Extract variables for view scope (prevent overwriting internal variables)
        extract($data, EXTR_SKIP);

        // Capture view output buffer
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Render layout if specified
        if ($layout !== null) {
            if (str_contains($layout, '..')) {
                throw new RuntimeException("Invalid layout path: Directory traversal is prohibited.");
            }

            $layoutFile = __DIR__ . '/../app/views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        // Output raw view content if no layout or layout not found
        echo $content;
    }

    /**
     * Send a JSON response with status code and headers.
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        json_response($data, $statusCode);
    }

    /**
     * Perform an HTTP redirect.
     */
    protected function redirect(string $url, int $statusCode = 302): void
    {
        redirect($url, $statusCode);
    }
}
