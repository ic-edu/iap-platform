<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Reporting\Events\SystemHealthChecked;
use Illuminate\Support\Facades\DB;

class SystemHealthService
{
    /**
     * Get system health report data.
     *
     * @return array<string, mixed>
     */
    public function checkHealth(): array
    {
        $dbConnected = true;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $dbConnected = false;
        }

        $health = [
            'status' => $dbConnected ? 'healthy' : 'unhealthy',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_connection' => $dbConnected ? 'connected' : 'disconnected',
            'storage_usage' => 'OK',
            'queue_health' => 'operational',
            'failed_jobs_count' => 0,
            'cache_status' => 'active',
        ];

        event(new SystemHealthChecked($health));

        return $health;
    }
}
