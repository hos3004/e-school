<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('PERFORMANCE_ENABLED', true),
    'slow_request_ms' => (int) env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
    'slow_query_ms' => (int) env('PERFORMANCE_SLOW_QUERY_MS', 500),
    'max_query_logs_per_request' => (int) env('PERFORMANCE_MAX_QUERY_LOGS', 10),
    'server_timing' => (bool) env('PERFORMANCE_SERVER_TIMING', false),
];
