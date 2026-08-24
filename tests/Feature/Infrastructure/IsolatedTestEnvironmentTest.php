<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('uses resources unique to the generated test run', function (): void {
    $token = (string) env('TEST_RUN_TOKEN');
    $database = 'eschool_testing_'.$token;

    expect(app()->environment())->toBe('testing')
        ->and(config('database.default'))->toBe('pgsql')
        ->and(config('database.connections.pgsql.database'))->toBe($database)
        ->and(config('cache.default'))->toBe('array')
        ->and(config('session.driver'))->toBe('array')
        ->and(config('queue.default'))->toBe('sync')
        ->and(config('mail.default'))->toBe('array')
        ->and(config('filesystems.default'))->toBe('test_isolated')
        ->and((string) config('database.redis.options.prefix'))->toContain($database);

    expect(DB::table('migrations')->count())->toBeGreaterThan(0);

    Storage::disk('test_isolated')->put('isolation-proof.txt', $token);

    expect(Storage::disk('test_isolated')->get('isolation-proof.txt'))->toBe($token)
        ->and((string) config('filesystems.disks.test_isolated.root'))->toEndWith($token);
});
