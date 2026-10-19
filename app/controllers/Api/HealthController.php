<?php
/**
 * API Health & Diagnostics Controller
 * Employee HRIS & Leave Management System
 */

namespace App\Controllers\Api;

use Core\Database;
use PDOException;
use OpenApi\Attributes as OA;

class HealthController extends ApiController
{
    #[OA\Get(
        path: "/api/v1/health",
        summary: "System health check",
        description: "Returns high-level system operational status and database connectivity check. Does not disclose sensitive environment configurations or internal database credentials.",
        tags: ["Health and Diagnostics"],
        responses: [
            new OA\Response(
                response: 200,
                description: "System is healthy and operational",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "ok"),
                        new OA\Property(property: "code", type: "integer", example: 200),
                        new OA\Property(property: "message", type: "string", example: "System operational."),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(property: "status", type: "string", example: "healthy"),
                                new OA\Property(property: "timestamp", type: "string", example: "2026-10-19T08:50:00+08:00"),
                                new OA\Property(property: "database", type: "string", example: "connected")
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 503,
                description: "Service unavailable (database offline)",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "error"),
                        new OA\Property(property: "code", type: "integer", example: 503),
                        new OA\Property(property: "message", type: "string", example: "Database connectivity check failed.")
                    ]
                )
            )
        ]
    )]
    public function health(): void
    {
        $db = Database::getInstance();
        $dbConnected = $db->isConnected();
        $pingSuccess = false;

        if ($dbConnected) {
            try {
                $stmt = $db->getConnection()->query("SELECT 1 AS ping");
                $pingSuccess = ($stmt->fetchColumn() == 1);
            } catch (PDOException $e) {
                $pingSuccess = false;
            }
        }

        if (!$dbConnected || !$pingSuccess) {
            $this->error('Database connectivity check failed.', 503, [
                'database' => 'disconnected',
            ]);
        }

        $this->success([
            'status'    => 'healthy',
            'timestamp' => date('c'),
            'database'  => 'connected',
        ], 'System operational.');
    }

    #[OA\Get(
        path: "/health",
        summary: "Detailed framework diagnostic endpoint (Existing landing health check)",
        description: "Existing operational diagnostics endpoint providing latency and memory utilization telemetry.",
        tags: ["Health and Diagnostics"],
        responses: [
            new OA\Response(
                response: 200,
                description: "Framework diagnostic telemetry",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "status", type: "string", example: "ok"),
                        new OA\Property(property: "timestamp", type: "string", example: "2026-10-19T08:50:00+08:00"),
                        new OA\Property(property: "environment", type: "string", example: "development"),
                        new OA\Property(property: "framework", type: "string", example: "Native PHP MVC Foundation (Phase 5)")
                    ]
                )
            )
        ]
    )]
    public function frameworkHealth(): void
    {
        // Forwards to existing HomeController::health() logic
        $homeController = new \App\Controllers\HomeController();
        $homeController->health();
    }
}
