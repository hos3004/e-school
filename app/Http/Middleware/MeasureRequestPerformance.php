<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\QueryPerformance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class MeasureRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!(bool) config('performance.enabled')) {
            return $next($request);
        }

        $started = defined('LARAVEL_START') && !app()->runningInConsole() ? LARAVEL_START : microtime(true);
        $queries = app(QueryPerformance::class);
        $response = null;

        try {
            $response = $next($request);

            return $response;
        } finally {
            $duration = (microtime(true) - $started) * 1000;
            if ($duration >= (int) config('performance.slow_request_ms')) {
                Log::channel('performance')->warning('slow_request', [
                    'route' => $request->route()?->getName(),
                    'method' => $request->method(),
                    'status' => $response?->getStatusCode() ?? 500,
                    'duration_ms' => round($duration, 2),
                    'query_ms' => round($queries->durationMs, 2),
                    'query_count' => $queries->count,
                ]);
            }

            if ($response !== null && (bool) config('performance.server_timing')) {
                $response->headers->set('Server-Timing', sprintf(
                    'app;dur=%.2f, db;dur=%.2f', $duration, $queries->durationMs,
                ));
            }
        }
    }
}
