<?php

declare(strict_types=1);

use App\Application\Actions\EnterClassroom;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Modules\VirtualClassroom\Domain\Enums\JoinRole;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    config()->set('virtual-classroom.default', 'null');
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return object{id: string, title: mixed, status: string, scheduled_start: string, scheduled_end: string, session_type: string}
 */
function flexibleStartFixtureRow(string $sessionType, string $startsAt, string $endsAt): object
{
    $organizationId = Fixtures::organizationId();
    $staffProfileId = Fixtures::staffProfileId();
    $courseId = Fixtures::courseId();

    $session = Session::query()->create([
        'organization_id' => $organizationId,
        'course_id' => $courseId,
        'staff_profile_id' => $staffProfileId,
        'session_type' => $sessionType,
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => $startsAt,
        'scheduled_end' => $endsAt,
        'title' => ['ar' => 'Flexible start test', 'en' => 'Flexible start test'],
    ]);

    return (object) [
        'id' => (string) $session->id,
        'title' => $session->title,
        'status' => $session->status->value,
        'scheduled_start' => $session->scheduled_start->toIso8601String(),
        'scheduled_end' => $session->scheduled_end->toIso8601String(),
        'session_type' => $sessionType,
    ];
}

it('still enforces the narrow join window for a group session even when the flag is on', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    $row = flexibleStartFixtureRow('group', '2026-09-27 14:00:00', '2026-09-27 15:00:00');

    // Far outside the ±15 minute window, hours before the scheduled start.
    expect(fn () => app(EnterClassroom::class)->url(
        row: $row,
        organizationId: $organizationId,
        userId: Fixtures::userId(),
        displayName: 'Teacher',
        role: JoinRole::Moderator,
        isFrozen: false,
        isTeacher: true,
    ))->toThrow(BusinessRuleViolation::class);
});

it('lets the teacher start an individual session hours later the same day when flexible start is enabled', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    DB::table('organizations')->where('id', $organizationId)->update(['default_timezone' => 'UTC']);
    $row = flexibleStartFixtureRow('individual', '2026-09-27 14:00:00', '2026-09-27 14:35:00');

    // 8 hours after the scheduled start, but still the same UTC calendar day.
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 22:00:00', 'UTC'));

    $url = app(EnterClassroom::class)->url(
        row: $row,
        organizationId: $organizationId,
        userId: Fixtures::userId(),
        displayName: 'Teacher',
        role: JoinRole::Moderator,
        isFrozen: false,
        isTeacher: true,
    );

    expect($url)->toBeString()->not->toBe('');
});

it('rejects joining an individual flexible session on the following calendar day', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => true]);
    $organizationId = Fixtures::organizationId();
    DB::table('organizations')->where('id', $organizationId)->update(['default_timezone' => 'UTC']);
    $row = flexibleStartFixtureRow('individual', '2026-09-27 14:00:00', '2026-09-27 14:35:00');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-28 01:00:00', 'UTC'));

    expect(fn () => app(EnterClassroom::class)->url(
        row: $row,
        organizationId: $organizationId,
        userId: Fixtures::userId(),
        displayName: 'Teacher',
        role: JoinRole::Moderator,
        isFrozen: false,
        isTeacher: true,
    ))->toThrow(BusinessRuleViolation::class);
});

it('does not widen an individual session join window when the flag is disabled', function (): void {
    config(['scheduling.flexible_individual_start.enabled' => false]);
    $organizationId = Fixtures::organizationId();
    $row = flexibleStartFixtureRow('individual', '2026-09-27 14:00:00', '2026-09-27 14:35:00');

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 22:00:00', 'UTC'));

    expect(fn () => app(EnterClassroom::class)->url(
        row: $row,
        organizationId: $organizationId,
        userId: Fixtures::userId(),
        displayName: 'Teacher',
        role: JoinRole::Moderator,
        isFrozen: false,
        isTeacher: true,
    ))->toThrow(BusinessRuleViolation::class);
});
