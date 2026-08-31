<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Application\Actions\ApplyForEnrollmentAction;
use Modules\Enrollments\Application\Actions\ChangeEnrollmentLevelAction;
use Modules\Enrollments\Application\Actions\FreezeEnrollmentAction;
use Modules\Enrollments\Application\Actions\ReactivateEnrollmentAction;
use Modules\Enrollments\Application\Actions\RequestReactivationAction;
use Modules\Enrollments\Application\Actions\TransitionEnrollmentStatusAction;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;

uses(RefreshDatabase::class);

it('creates and transitions an enrollment with tenant validation history and audit', function (): void {
    Gate::before(static fn (): bool => true);

    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create();
    $student = StudentProfile::factory()->create([
        'organization_id' => (string) $organization->id,
        'user_id' => (string) $studentUser->id,
    ]);
    $program = Program::factory()->create(['organization_id' => (string) $organization->id]);
    $firstLevel = Level::factory()->create(['program_id' => (string) $program->id, 'sort_order' => 1]);
    $secondLevel = Level::factory()->create(['program_id' => (string) $program->id, 'sort_order' => 2]);

    $this->actingAs($actor);
    $enrollment = app(ApplyForEnrollmentAction::class)->execute(
        organizationId: (string) $organization->id,
        studentProfileId: (string) $student->id,
        programId: (string) $program->id,
        reason: 'فتح قيد بعد مراجعة ملف القبول',
        currentLevelId: (string) $firstLevel->id,
        actorId: (string) $actor->id,
    );

    app(TransitionEnrollmentStatusAction::class)->execute(
        $enrollment,
        EnrollmentStatus::UnderReview,
        'بدء المراجعة الأكاديمية',
        (string) $actor->id,
    );
    app(TransitionEnrollmentStatusAction::class)->execute(
        $enrollment->refresh(),
        EnrollmentStatus::Approved,
        'استيفاء شروط البرنامج',
        (string) $actor->id,
    );
    app(ChangeEnrollmentLevelAction::class)->execute(
        $enrollment->refresh(),
        (string) $secondLevel->id,
        'نتيجة اختبار تحديد المستوى',
        (string) $actor->id,
    );

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Approved)
        ->and((string) $enrollment->current_level_id)->toBe((string) $secondLevel->id)
        ->and($enrollment->statusHistory()->count())->toBe(3);

    foreach (['enrollments.created', 'enrollments.status_changed', 'enrollments.level_changed'] as $action) {
        expect(DB::table('audit_log')->where('action', $action)->exists())->toBeTrue("Missing {$action}");
    }
});

it('rejects creating a cross-tenant enrollment even when ids are supplied directly', function (): void {
    $mine = Organization::factory()->create();
    $other = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $mine->id)->create();
    $foreignStudentUser = User::factory()->inOrganization((string) $other->id)->create();
    $foreignStudent = StudentProfile::factory()->create([
        'organization_id' => (string) $other->id,
        'user_id' => (string) $foreignStudentUser->id,
    ]);
    $program = Program::factory()->create(['organization_id' => (string) $mine->id]);

    $this->actingAs($actor);

    expect(fn () => app(ApplyForEnrollmentAction::class)->execute(
        organizationId: (string) $mine->id,
        studentProfileId: (string) $foreignStudent->id,
        programId: (string) $program->id,
        reason: 'محاولة عبور مؤسسة',
        actorId: (string) $actor->id,
    ))->toThrow(BusinessRuleViolation::class);

    expect(Enrollment::query()->count())->toBe(0);
});

it('enforces the full frozen reactivation path and audits every transition', function (): void {
    Gate::before(static fn (): bool => true);

    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();
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

    $this->actingAs($actor);
    app(FreezeEnrollmentAction::class)->execute($enrollment, 'قرار انضباطي موثق', 'manual', (string) $actor->id);
    app(RequestReactivationAction::class)->execute($enrollment->refresh(), 'طلب عودة بعد معالجة السبب', (string) $actor->id);
    app(TransitionEnrollmentStatusAction::class)->execute(
        $enrollment->refresh(),
        EnrollmentStatus::UnderAssessment,
        'بدء تقييم الجدية',
        (string) $actor->id,
    );
    app(ReactivateEnrollmentAction::class)->execute($enrollment->refresh(), 'اجتياز تقييم العودة', (string) $actor->id);

    expect($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->frozen_at)->toBeNull()
        ->and($enrollment->statusHistory()->count())->toBe(4)
        ->and(DB::table('audit_log')->where('action', 'enrollments.status_changed')->count())->toBe(4);
});

it('activates an approved enrollment through placement with history and audit', function (): void {
    $organization = Organization::factory()->create();
    $actor = User::factory()->inOrganization((string) $organization->id)->create();
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
        'status' => EnrollmentStatus::Approved,
        'applied_at' => now()->subDay()->utc(),
    ]);

    $this->actingAs($actor);
    $placement = app(EnrollmentPlacementGateway::class)->activate(
        organizationId: (string) $organization->id,
        studentProfileId: (string) $student->id,
        programId: (string) $program->id,
        reason: 'تسكين الطالب في المجموعة المعتمدة',
        actorId: (string) $actor->id,
    );

    expect($placement->status)->toBe(EnrollmentStatus::Active->value)
        ->and($enrollment->refresh()->status)->toBe(EnrollmentStatus::Active)
        ->and($enrollment->statusHistory()->count())->toBe(1)
        ->and(DB::table('audit_log')->where('action', 'enrollments.activated_by_placement')->exists())->toBeTrue();
});
