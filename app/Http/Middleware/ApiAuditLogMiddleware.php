<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuditLogMiddleware
{
    public function __construct(protected ActivityLogger $logger) {}

    /**
     * Handle an incoming API request & log metadata to ActivityLog.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $userId = $request->user()?->id;
        $action = 'api_request';
        $desc = sprintf(
            '%s %s [%d] (%sms)',
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            $executionTimeMs
        );

        $this->logger->log(
            $action,
            $desc,
            null,
            [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status_code' => $response->getStatusCode(),
                'execution_time_ms' => $executionTimeMs,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            $userId
        );

        return $response;
    }
}
