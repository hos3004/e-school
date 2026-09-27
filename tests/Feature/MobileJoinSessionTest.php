<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    Gate::define('session.join', fn (): bool => true);
    config()->set('virtual-classroom.default', 'null');
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

function makeJoinableSession(string $staffProfileId, ?CarbonImmutable $start = null): Session
{
    $start ??= CarbonImmutable::now('UTC');

    return Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => $start,
        'scheduled_end' => $start->addMinutes(30),
    ]);
}

it('returns a join url for the sessions own teacher inside the join window', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($user->id);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC'));
    $session = makeJoinableSession($staffProfileId, CarbonImmutable::now('UTC'));

    $response = $this->actingAs($user)->postJson("/api/sessions/{$session->id}/join");

    $response->assertOk()->assertJsonStructure(['join_url']);
    expect($response->json('join_url'))->toBeString()->not->toBe('');
});

it('returns 404 for a session that belongs to a different teacher', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($user->id);
    $otherStaffProfileId = Fixtures::staffProfileForUser(Fixtures::userId());

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC'));
    $session = makeJoinableSession($otherStaffProfileId, CarbonImmutable::now('UTC'));

    $this->actingAs($user)
        ->postJson("/api/sessions/{$session->id}/join")
        ->assertNotFound();
});

it('rejects joining outside the join window with a business rule violation', function (): void {
    $organizationId = Fixtures::organizationId();
    $user = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($user->id);

    // الحصة الساعة 14:00، والمحاولة الساعة 10:00 — بعيد جدًا عن نافذة الـ20 دقيقة.
    $session = makeJoinableSession($staffProfileId, CarbonImmutable::parse('2026-09-27 14:00:00', 'UTC'));
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 10:00:00', 'UTC'));

    $this->actingAs($user)
        ->postJson("/api/sessions/{$session->id}/join")
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'virtualclassroom.join_window_closed')
        ->assertJsonPath('error.message', trans('virtualclassroom::errors.join_window_closed'));
});

it('requires authentication', function (): void {
    $staffProfileId = Fixtures::staffProfileId();
    $session = makeJoinableSession($staffProfileId, CarbonImmutable::now('UTC'));

    $this->postJson("/api/sessions/{$session->id}/join")->assertUnauthorized();
});
