<?php

namespace Tests\Unit\Api;

use App\Controllers\Api\DocsController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Yaml\Yaml;

/**
 * Guards OpenAPI 3.0 spec parity with registered /api/v1/* routes and verifies
 * Swagger UI / API response security hardening.
 */
class OpenApiAndDocsSecurityTest extends TestCase
{
    public function test_every_api_v1_route_is_documented_in_openapi_yaml(): void
    {
        $indexPath = $this->repoPath('public/index.php');
        $indexSource = file_get_contents($indexPath);
        $this->assertNotFalse($indexSource, 'Unable to read public/index.php.');

        preg_match_all(
            '/\$router->(?:get|post|put|patch|delete)\(\'(\/api\/v1[^\']*)\'/',
            $indexSource,
            $matches
        );
        $routePaths = array_values(array_unique($matches[1]));
        $this->assertNotEmpty($routePaths, 'Expected /api/v1/* routes in public/index.php.');

        $spec = Yaml::parseFile($this->repoPath('docs/api/openapi.yaml'));
        $this->assertIsArray($spec);
        $this->assertArrayHasKey('paths', $spec);
        $specPaths = array_keys($spec['paths']);

        $missing = array_values(array_diff($routePaths, $specPaths));
        $this->assertSame(
            [],
            $missing,
            'Undocumented /api/v1/* route(s) missing from docs/api/openapi.yaml: ' . implode(', ', $missing)
        );
    }

    public function test_openapi_yaml_and_json_have_matching_paths(): void
    {
        $yamlSpec = Yaml::parseFile($this->repoPath('docs/api/openapi.yaml'));
        $jsonRaw = file_get_contents($this->repoPath('docs/api/openapi.json'));
        $this->assertNotFalse($jsonRaw, 'Unable to read docs/api/openapi.json.');

        $jsonSpec = json_decode($jsonRaw, true);
        $this->assertIsArray($jsonSpec, 'docs/api/openapi.json must be valid JSON.');

        $yamlPaths = array_keys($yamlSpec['paths'] ?? []);
        $jsonPaths = array_keys($jsonSpec['paths'] ?? []);
        sort($yamlPaths);
        sort($jsonPaths);

        $this->assertSame($yamlPaths, $jsonPaths, 'docs/api/openapi.yaml and openapi.json paths have drifted.');
    }

    public function test_docs_controller_allows_testing_env_and_hardens_responses(): void
    {
        $controller = new DocsController();
        $method = new ReflectionMethod(DocsController::class, 'isDocumentationAllowed');

        $this->assertTrue(
            $method->invoke($controller),
            'DocsController should allow documentation viewing in APP_ENV=testing.'
        );

        $source = file_get_contents($this->repoPath('app/controllers/Api/DocsController.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString("config('config.app_env', 'production')", $source);
        $this->assertStringContainsString('Cache-Control: no-store', $source);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $source);
    }

    public function test_api_controller_sets_no_store_and_nosniff_headers(): void
    {
        $source = file_get_contents($this->repoPath('app/controllers/Api/ApiController.php'));
        $this->assertNotFalse($source);
        $this->assertStringContainsString('Cache-Control: no-store, no-cache, must-revalidate, private', $source);
        $this->assertStringContainsString('X-Content-Type-Options: nosniff', $source);
    }

    private function repoPath(string $relativePath): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
