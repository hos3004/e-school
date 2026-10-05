<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

final class QueryPerformance
{
    public float $durationMs = 0;

    public int $count = 0;

    private int $logged = 0;

    public function record(QueryExecuted $query): void
    {
        $this->durationMs += $query->time;
        $this->count++;

        if ($query->time < (int) config('performance.slow_query_ms')
            || $this->logged >= (int) config('performance.max_query_logs_per_request')) {
            return;
        }

        $this->logged++;
        // Never log SQL, bindings, URLs, or user data.
        Log::channel('performance')->warning('slow_query', [
            'query_hash' => hash('sha256', $query->sql),
            'connection' => $query->connectionName,
            'duration_ms' => $query->time,
            'route' => request()->route()?->getName(),
        ]);
    }
}
