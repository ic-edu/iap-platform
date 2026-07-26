<?php

namespace App\Http\Controllers;

use App\Modules\Reporting\Services\SystemHealthService;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    public function __construct(protected SystemHealthService $healthService) {}

    /**
     * Detailed Application System Health Check (/health).
     */
    public function health(): JsonResponse
    {
        $health = $this->healthService->checkHealth();

        return response()->json([
            'success' => true,
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
            'data' => $health,
        ]);
    }

    /**
     * Kubernetes & Load Balancer Readiness Probe (/ready).
     */
    public function ready(): JsonResponse
    {
        return response()->json([
            'status' => 'ready',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Kubernetes & Load Balancer Liveness Probe (/live).
     */
    public function live(): JsonResponse
    {
        return response()->json([
            'status' => 'live',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}
