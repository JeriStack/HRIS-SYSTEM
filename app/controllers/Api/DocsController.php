<?php
/**
 * API Documentation Controller
 * Renders embedded Swagger UI and serves generated OpenAPI specifications
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use Core\Controller;

class DocsController extends Controller
{
    /**
     * Check if documentation viewing is allowed in current environment.
     */
    private function isDocumentationAllowed(): bool
    {
        $appEnv = config('config.app_env', 'production');
        return in_array($appEnv, ['development', 'local', 'testing'], true);
    }

    /**
     * Send a consistent JSON 403 response when documentation is disabled.
     */
    private function denyDocumentationAccess(): never
    {
        http_response_code(403);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=UTF-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('X-Content-Type-Options: nosniff');
        }
        echo json_encode([
            'status'  => 'error',
            'code'    => 403,
            'message' => 'API documentation is disabled in production environments unless explicitly authorized.',
        ]);
        exit;
    }

    /**
     * Render the interactive Swagger UI interface.
     * Route: GET /docs or GET /api/docs
     */
    public function index(): void
    {
        if (!$this->isDocumentationAllowed()) {
            $this->denyDocumentationAccess();
        }

        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, private');
            header('X-Content-Type-Options: nosniff');
        }

        $specUrl = url('/api/docs/openapi.yaml');
        $csrfToken = csrf_token();
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>HRIS API Documentation | Swagger UI</title>
            <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui.css">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    background: #F8FAFC;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                }
                .top-banner {
                    background: #1E293B;
                    color: #FFFFFF;
                    padding: 12px 24px;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                .brand {
                    font-size: 16px;
                    font-weight: 700;
                    letter-spacing: -0.02em;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .badge {
                    background: #2563EB;
                    color: #FFFFFF;
                    padding: 3px 8px;
                    border-radius: 4px;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                }
                .links a {
                    color: #94A3B8;
                    text-decoration: none;
                    margin-left: 16px;
                    font-size: 13px;
                    font-weight: 500;
                    transition: color 0.15s;
                }
                .links a:hover {
                    color: #FFFFFF;
                }
                .swagger-ui .topbar {
                    display: none;
                }
                .swagger-ui .info {
                    margin: 25px 0;
                }
            </style>
        </head>
        <body>
            <div class="top-banner">
                <div class="brand">
                    <span>Employee HRIS & Leave Management System</span>
                    <span class="badge">OpenAPI 3.0</span>
                </div>
                <div class="links">
                    <a href="<?= e(url('/api/docs/openapi.yaml')) ?>" target="_blank" rel="noopener noreferrer">Download YAML</a>
                    <a href="<?= e(url('/api/docs/openapi.json')) ?>" target="_blank" rel="noopener noreferrer">Download JSON</a>
                    <a href="<?= e(url('/dashboard')) ?>">Back to HRIS</a>
                </div>
            </div>

            <div id="swagger-ui"></div>

            <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
            <script src="https://unpkg.com/swagger-ui-dist@5.17.14/swagger-ui-standalone-preset.js"></script>
            <script>
                window.onload = function() {
                    const ui = SwaggerUIBundle({
                        url: "<?= e($specUrl) ?>",
                        dom_id: '#swagger-ui',
                        deepLinking: true,
                        presets: [
                            SwaggerUIBundle.presets.apis,
                            SwaggerUIStandalonePreset
                        ],
                        plugins: [
                            SwaggerUIBundle.plugins.DownloadUrl
                        ],
                        layout: "BaseLayout",
                        requestInterceptor: (req) => {
                            req.credentials = 'include';
                            const csrf = "<?= e($csrfToken) ?>";
                            const method = String(req.method || '').toUpperCase();
                            if (csrf && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                                req.headers['X-CSRF-Token'] = csrf;
                            }
                            return req;
                        }
                    });
                    window.ui = ui;
                };
            </script>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Serve generated OpenAPI YAML specification file.
     * Route: GET /api/docs/openapi.yaml
     */
    public function yamlSpec(): void
    {
        if (!$this->isDocumentationAllowed()) {
            $this->denyDocumentationAccess();
        }

        $filePath = __DIR__ . '/../../../docs/api/openapi.yaml';
        if (!file_exists($filePath)) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 'error',
                'message' => 'OpenAPI YAML specification has not been generated yet. Please run vendor/bin/openapi app -o docs/api/openapi.yaml',
            ]);
            exit;
        }

        header('Content-Type: application/yaml; charset=UTF-8');
        header('Content-Disposition: inline; filename="openapi.yaml"');
        readfile($filePath);
        exit;
    }

    /**
     * Serve generated OpenAPI JSON specification file.
     * Route: GET /api/docs/openapi.json
     */
    public function jsonSpec(): void
    {
        if (!$this->isDocumentationAllowed()) {
            $this->denyDocumentationAccess();
        }

        $filePath = __DIR__ . '/../../../docs/api/openapi.json';
        if (!file_exists($filePath)) {
            // If json file doesn't exist, try converting yaml or return 404
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode([
                'status'  => 'error',
                'message' => 'OpenAPI JSON specification has not been generated yet. Please run composer swagger:generate',
            ]);
            exit;
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: inline; filename="openapi.json"');
        readfile($filePath);
        exit;
    }
}
