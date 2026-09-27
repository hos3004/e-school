<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Sessions\Domain\Enums\SessionStatus;
use Modules\Sessions\Domain\Models\Session;
use Shared\Testing\Fixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Fixtures::flush();
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-27 12:00:00', 'UTC'));
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('lists a past session with no report as a required report', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Completed,
        'scheduled_start' => CarbonImmutable::now('UTC')->subHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->subHours(2)->subMinutes(30),
    ]);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/required-reports');

    $response->assertOk();
    $items = $response->json('items');
    expect($items)->toHaveCount(1)
        ->and($items[0]['id'])->toBe($session->id)
        ->and($items[0]['type'])->toBe('session_report');
});

it('excludes a past session that already has a submitted report', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Completed,
        'scheduled_start' => CarbonImmutable::now('UTC')->subHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->subHours(2)->subMinutes(30),
    ]);

    DB::table('session_reports')->insert([
        'id' => (string) Str::ulid(),
        'session_id' => $session->id,
        'staff_profile_id' => $staffProfileId,
        'topics_covered' => 'تم تغطية السورة',
        'submitted_at' => now(),
        'is_late' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/required-reports');

    $response->assertOk()->assertJsonPath('items', []);
});

it('excludes a session that has not started yet', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->addHours(2),
        'scheduled_end' => CarbonImmutable::now('UTC')->addHours(2)->addMinutes(30),
    ]);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/required-reports');

    $response->assertOk()->assertJsonPath('items', []);
});

it('returns an empty list for a teacher with no staff profile', function (): void {
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();

    $response = $this->actingAs($teacher)->getJson('/api/teacher/required-reports');

    $response->assertOk()->assertJsonPath('items', []);
});

it('lists a session that was never opened from the platform (still scheduled past its time)', function (): void {
    // هذه الحالة تحديدًا هي سبب إضافة scheduled/confirmed للاستعلام أصلًا:
    // حصة فات موعدها ولم يفتح المعلم غرفتها، فلا شيء آلي يحرّكها إلى حالة
    // أخرى. لو اختفت من هنا يرجع فخ "الحصص المجدولة أبدًا" القديم.
    $organizationId = Fixtures::organizationId();
    $teacher = User::factory()->inOrganization($organizationId)->create();
    $staffProfileId = Fixtures::staffProfileForUser($teacher->id);

    $session = Session::factory()->create([
        'staff_profile_id' => $staffProfileId,
        'course_id' => Fixtures::courseId(),
        'session_type' => 'individual',
        'status' => SessionStatus::Scheduled,
        'scheduled_start' => CarbonImmutable::now('UTC')->subHours(3),
        'scheduled_end' => CarbonImmutable::now('UTC')->subHours(2)->subMinutes(30),
    ]);

    $response = $this->actingAs($teacher)->getJson('/api/teacher/required-reports');

    $response->assertOk();
    $items = $response->json('items');
    expect($items)->toHaveCount(1)->and($items[0]['id'])->toBe($session->id);
});

it('requires authentication', function (): void {
    $this->getJson('/api/teacher/required-reports')->assertUnauthorized();
});
