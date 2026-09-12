<?php

declare(strict_types=1);

use App\Http\Middleware\MeasureRequestPerformance;
use App\Support\QueryPerformance;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

it('bounds slow query logs and never includes SQL or bindings', function (): void {
    config(['performance.slow_query_ms' => 100, 'performance.max_query_logs_per_request' => 1]);
    Log::shouldReceive('channel')->once()->with('performance')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('slow_query', Mockery::on(
        fn (array $context): bool => !isset($context['sql']) && !isset($context['bindings'])
            && !str_contains(json_encode($context), 'private-value')
            && (float) $context['duration_ms'] === 150.0,
    ));
    $metrics = new QueryPerformance;
    $query = new QueryExecuted('select ? as secret', ['private-value'], 150, DB::connection());
    $metrics->record($query);
    $metrics->record($query);
    expect($metrics->count)->toBe(2)->and($metrics->durationMs)->toBe(300.0);
});

it('records aggregate request metrics without URL parameters or bodies', function (): void {
    config(['performance.enabled' => true, 'performance.slow_request_ms' => 0, 'performance.server_timing' => true]);
    Log::shouldReceive('channel')->once()->with('performance')->andReturnSelf();
    Log::shouldReceive('warning')->once()->with('slow_request', Mockery::on(
        fn (array $context): bool => $context['status'] === 200
            && !str_contains(json_encode($context), 'private-value'),
    ));
    $response = app(MeasureRequestPerformance::class)->handle(
        Request::create('/private?token=private-value'),
        fn () => response('ok'),
    );
    expect($response->headers->get('Server-Timing'))->toContain('app;dur=', 'db;dur=');
});

it('can disable request instrumentation', function (): void {
    config(['performance.enabled' => false]);
    Log::shouldReceive('channel')->never();
    $response = app(MeasureRequestPerformance::class)->handle(Request::create('/'), fn () => response('ok'));
    expect($response->headers->has('Server-Timing'))->toBeFalse();
});
