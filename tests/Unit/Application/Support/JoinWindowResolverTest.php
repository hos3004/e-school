<?php

declare(strict_types=1);

use App\Application\Support\JoinWindowResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
});

it('keeps the fixed minutes window when the schedule does not allow flexible start', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    $start = CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC');
    $end = $start->addMinutes(60);

    [$canJoinAt, $canJoinUntil, $flexible] = JoinWindowResolver::resolve(
        $start,
        $end,
        $organizationId,
        false,
        false,
    );

    expect($flexible)->toBeFalse()
        ->and($canJoinAt->toIso8601String())->toBe(
            $start->subMinutes((int) config('virtual-classroom.join_window.before_minutes'))->toIso8601String(),
        )
        ->and($canJoinUntil->toIso8601String())->toBe(
            $end->addMinutes((int) config('virtual-classroom.join_window.after_minutes'))->toIso8601String(),
        );
});

it('widens the window to the organization local day when the schedule allows flexible start', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    DB::table('organizations')->where('id', $organizationId)->update(['default_timezone' => 'Africa/Cairo']);

    $start = CarbonImmutable::parse('2026-09-27 20:00:00', 'UTC');
    $end = $start->addMinutes(35);
    $expectedLocalDayStart = $start->setTimezone('Africa/Cairo')->startOfDay();

    [$canJoinAt, $canJoinUntil, $flexible] = JoinWindowResolver::resolve(
        $start,
        $end,
        $organizationId,
        true,
        true,
    );

    expect($flexible)->toBeTrue()
        ->and($canJoinAt->toIso8601String())->toBe($expectedLocalDayStart->setTimezone('UTC')->toIso8601String())
        ->and($canJoinUntil->toIso8601String())->toBe($expectedLocalDayStart->addDay()->setTimezone('UTC')->toIso8601String());

    // A join attempt several hours later, but still inside the local day
    // that started the window, must fall inside it.
    $laterSameLocalDay = $canJoinAt->addHours(6);
    expect($laterSameLocalDay->betweenIncluded($canJoinAt, $canJoinUntil))->toBeTrue();
});

it('does not widen the window when the schedule allows it but the global kill switch is disabled', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => false]);
    $organizationId = Fixtures::organizationId();
    $start = CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC');
    $end = $start->addMinutes(35);

    [, , $flexible] = JoinWindowResolver::resolve($start, $end, $organizationId, true, false);

    expect($flexible)->toBeFalse();
});

it('falls back to UTC when the organization timezone is missing or invalid', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    DB::table('organizations')->where('id', $organizationId)->update(['default_timezone' => 'not-a-real-zone']);

    $start = CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC');
    $end = $start->addMinutes(35);

    [$canJoinAt, , $flexible] = JoinWindowResolver::resolve($start, $end, $organizationId, true, false);

    expect($flexible)->toBeTrue()
        ->and($canJoinAt->toIso8601String())->toBe('2026-09-27T00:00:00+00:00');
});
