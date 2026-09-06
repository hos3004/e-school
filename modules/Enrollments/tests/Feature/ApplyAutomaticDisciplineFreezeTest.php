<?php

declare(strict_types=1);

use App\Listeners\ApplyAutomaticDisciplineFreeze;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Academics\Domain\Models\Program;
use Modules\Discipline\Domain\Enums\DisciplineActionType;
use Modules\Discipline\Domain\Events\DisciplineActionApplied;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Events\EnrollmentFrozen;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

it('freezes an active enrollment once for an automatic discipline action', function (): void {
    Event::fake([EnrollmentFrozen::class]);

    $organization = Organization::factory()->create();
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create();
    $student = StudentProfile::factory()->create([
        'organization_id' => (string) $organization->id,
        'user_id' => (string) $studentUser->id,
    ]);
    $program = Program::factory()->create(['organization_id' => (string) $organization->id]);
    $enrollment = Enrollment::query()->create([
        'organization_id' => (string) $organization->id,
        'student_profile_id' => (string) $student->id,
        'program_id' => (string) $program->id,
        'status' => EnrollmentStatus::Active,
        'applied_at' => now()->subMonth()->utc(),
        'activated_at' => now()->subMonth()->utc(),
    ]);
    $event = new DisciplineActionApplied(
        disciplineActionId: (string) str()->ulid(),
        organizationId: (string) $organization->id,
        enrollmentId: (string) $enrollment->id,
        action: DisciplineActionType::FreezeEnrollment,
        thresholdReached: 3,
        windowKey: 'R30',
        isAutomatic: true,
    );

    $listener = app(ApplyAutomaticDisciplineFreeze::class);
    $listener->handle($event);
    $listener->handle($event);

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Frozen)
        ->and($enrollment->statusHistory()->count())->toBe(1)
        ->and(DB::table('audit_log')
            ->where('auditable_type', 'enrollments')
            ->where('auditable_id', (string) $enrollment->id)
            ->where('action', 'enrollments.status_changed')
            ->count())->toBe(1);

    Event::assertDispatchedTimes(EnrollmentFrozen::class, 1);
});

it('ignores non-freeze discipline actions', function (): void {
    $listener = app(ApplyAutomaticDisciplineFreeze::class);

    $listener->handle(new DisciplineActionApplied(
        disciplineActionId: (string) str()->ulid(),
        organizationId: (string) str()->ulid(),
        enrollmentId: (string) str()->ulid(),
        action: DisciplineActionType::Notice,
        thresholdReached: 1,
        windowKey: 'R30',
        isAutomatic: true,
    ));

    expect(Enrollment::query()->count())->toBe(0);
});
