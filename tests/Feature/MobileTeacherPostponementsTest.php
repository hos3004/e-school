<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Scheduling\Application\Actions\RequestPostponement;
use Modules\Scheduling\Domain\Models\PostponementRequest;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    Gate::define('session.postpone.approve', fn (): bool => true);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 08:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

/**
 * @return array{teacher: User, request: PostponementRequest}
 */
function mobilePostponementFixture(): array
{
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);
    $studentProfileId = Fixtures::studentProfileId();
    $courseId = Fixtures::courseId();
    $levelId = DB::table('courses')->where('id', $courseId)->value('level_id');
    $programId = DB::table('levels')->where('id', $levelId)->value('program_id');

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => $courseId,
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addDays(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->addDays(3)->addMinutes(30),
    ]);

    $enrollmentId = (string) Str::ulid();
    DB::table('enrollments')->insert([
        'id' => $enrollmentId,
        'organization_id' => $organizationId,
        'student_profile_id' => $studentProfileId,
        'program_id' => $programId,
        'status' => 'active',
        'applied_at' => CarbonImmutable::now('UTC')->subMonth(),
        'activated_at' => CarbonImmutable::now('UTC')->subMonth(),
        'current_level_id' => $levelId,
        'created_at' => CarbonImmutable::now('UTC'),
        'updated_at' => CarbonImmutable::now('UTC'),
    ]);

    DB::table('session_participants')->insert([
        'id' => (string) Str::ulid(),
        'session_id' => $session->id,
        'student_profile_id' => $studentProfileId,
        'enrollment_id' => $enrollmentId,
        'join_url_token' => Str::random(64),
        'invited_at' => CarbonImmutable::now('UTC'),
        'attended_minutes' => 0,
        'created_at' => CarbonImmutable::now('UTC'),
    ]);

    $studentUserId = DB::table('student_profiles')->where('id', $studentProfileId)->value('user_id');

    $request = app(RequestPostponement::class)->execute(
        $organizationId,
        (string) $session->id,
        (string) $studentUserId,
        $studentProfileId,
        CarbonImmutable::now('UTC')->addDays(4),
        'الطالب عنده ظرف طارئ',
    );

    return ['teacher' => $teacher, 'request' => $request];
}

it('lists the teachers own postponement requests', function (): void {
    $fixture = mobilePostponementFixture();

    $response = $this->actingAs($fixture['teacher'])->getJson('/api/teacher/postponements');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $fixture['request']->id)
        ->assertJsonPath('data.0.status', 'requested');
});

it('lets the teacher approve a postponement request', function (): void {
    $fixture = mobilePostponementFixture();

    $this->actingAs($fixture['teacher'])
        ->postJson("/api/teacher/postponements/{$fixture['request']->id}/approve")
        ->assertOk()
        ->assertJsonPath('status', 'approved');

    expect($fixture['request']->fresh()->status->value)->toBe('scheduled');
});

it('lets the teacher reject a postponement request with a reason', function (): void {
    $fixture = mobilePostponementFixture();

    $this->actingAs($fixture['teacher'])
        ->postJson("/api/teacher/postponements/{$fixture['request']->id}/reject", [
            'reason' => 'الموعد المقترح يتعارض مع حصة أخرى',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'rejected');
});

it('rejects a reject request with no reason', function (): void {
    $fixture = mobilePostponementFixture();

    $this->actingAs($fixture['teacher'])
        ->postJson("/api/teacher/postponements/{$fixture['request']->id}/reject", [])
        ->assertUnprocessable();
});

it('lets the teacher propose an alternative time', function (): void {
    $fixture = mobilePostponementFixture();

    $this->actingAs($fixture['teacher'])
        ->postJson("/api/teacher/postponements/{$fixture['request']->id}/propose-alternative", [
            'proposed_start_at' => CarbonImmutable::now('UTC')->addDays(5)->toIso8601String(),
            'reason' => 'الموعد ده أنسب لجدولي',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'alternative_proposed');
});

it('forbids a different teacher from acting on the request', function (): void {
    $fixture = mobilePostponementFixture();
    $organizationId = Fixtures::organizationId();
    $otherTeacher = User::factory()->inOrganization($organizationId)->create();
    Fixtures::staffProfileForUser($otherTeacher->id);

    $this->actingAs($otherTeacher)
        ->postJson("/api/teacher/postponements/{$fixture['request']->id}/approve")
        ->assertForbidden();
});

it('requires authentication', function (): void {
    $fixture = mobilePostponementFixture();

    $this->getJson('/api/teacher/postponements')->assertUnauthorized();
    $this->postJson("/api/teacher/postponements/{$fixture['request']->id}/approve")->assertUnauthorized();
    $this->postJson("/api/teacher/postponements/{$fixture['request']->id}/propose-alternative")->assertUnauthorized();
    $this->postJson("/api/teacher/postponements/{$fixture['request']->id}/reject")->assertUnauthorized();
});
