<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Modules\AccessControl\Domain\Contracts\AccessControlQuerier;
use Modules\Organization\Domain\Contracts\GeographyQueries;

it('loads all permission decisions for a model in one query per request scope', function (): void {
    /** @var AccessControlQuerier $querier */
    $querier = app(AccessControlQuerier::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    foreach (['students.view_any', 'staff.view_any', 'sessions.view_any'] as $permission) {
        $querier->modelHasPermission('users', '01PERF0000000000000000000', $permission, 'web');
    }

    expect(DB::getQueryLog())->toHaveCount(1);
});

it('batches all regions and reuses them within the request scope', function (): void {
    /** @var GeographyQueries $geography */
    $geography = app(GeographyQueries::class);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $geography->regionsOf('01COUNTRY00000000000000000');
    $geography->regionsOf('01COUNTRY00000000000000001');
    $geography->regionsOf('01COUNTRY00000000000000002');

    expect(DB::getQueryLog())->toHaveCount(1);
});
