<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

beforeEach(function (): void {
    Gate::define('session.view', fn (): bool => true);
});

function makeTeacherSession(string $staffProfileId, CarbonImmutable $scheduledStart): Session
{
    return Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'scheduled_start' => $scheduledStart,
        'scheduled_end' => $scheduledStart->addMinutes(60),
    ]);
}

it('returns only the sessions scheduled on the requested day in the organization timezone', function (): void {
    // منظمة الاختبار افتراضيًا Africa/Cairo (UTC+2 دون توقيت صيفي)، فمنتصف
    // يوم بتوقيت القاهرة يقع بأمان داخل نفس اليوم بتوقيت UTC.
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($user->id);

    $today = CarbonImmutable::createFromFormat('!Y-m-d', '2026-09-27', 'Africa/Cairo')->setTime(10, 0);
    $todaySession = makeTeacherSession($staffProfileId, $today);
    makeTeacherSession($staffProfileId, $today->subDay());
    makeTeacherSession($staffProfileId, $today->addDay());

    $response = $this->actingAs($user)->getJson('/api/sessions?date=2026-09-27');

    $response->assertOk();
    $ids = collect((array) $response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)->and($ids->first())->toBe($todaySession->id);
});

it('returns everything when no date filter is given, unchanged from before', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($user->id);

    $today = CarbonImmutable::now('UTC')->addDay();
    makeTeacherSession($staffProfileId, $today);
    makeTeacherSession($staffProfileId, $today->addDay());

    $response = $this->actingAs($user)->getJson('/api/sessions');

    $response->assertOk();
    expect(collect((array) $response->json('data')))->toHaveCount(2);
});

it('accepts an explicit from/to range', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($user->id);

    $inRange = makeTeacherSession($staffProfileId, CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
    makeTeacherSession($staffProfileId, CarbonImmutable::parse('2026-10-05 08:00:00', 'UTC'));

    $response = $this->actingAs($user)->getJson(
        '/api/sessions?from=2026-09-26T00:00:00Z&to=2026-09-28T00:00:00Z',
    );

    $response->assertOk();
    $ids = collect((array) $response->json('data'))->pluck('id');

    expect($ids)->toHaveCount(1)->and($ids->first())->toBe($inRange->id);
});

it('rejects a malformed date', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();

    $this->actingAs($user)
        ->getJson('/api/sessions?date=27-09-2026')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('rejects a to before from', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();

    $this->actingAs($user)
        ->getJson('/api/sessions?from=2026-09-28T00:00:00Z&to=2026-09-26T00:00:00Z')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to']);
});

it('a teacher never sees another teacher\'s session even inside the same day', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($user->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    $today = CarbonImmutable::createFromFormat('!Y-m-d', '2026-09-27', 'Africa/Cairo')->setTime(10, 0);
    makeTeacherSession($otherStaffProfileId, $today);

    $response = $this->actingAs($user)->getJson('/api/sessions?date=2026-09-27');

    $response->assertOk();
    expect(collect((array) $response->json('data')))->toHaveCount(0);
});
