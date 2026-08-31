<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Modules\Enrollments\Presentation\Filament\Resources\EnrollmentResource;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Domain\Models\Organization;
use Modules\Students\Domain\Models\StudentProfile;

uses(RefreshDatabase::class);

it('renders enrollment creation and a real operations hub with names and history', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $organization = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $organization->id)->create();
    $studentUser = User::factory()->inOrganization((string) $organization->id)->create([
        'name' => 'سارة محمود',
    ]);
    $student = StudentProfile::factory()->create([
        'organization_id' => (string) $organization->id,
        'user_id' => (string) $studentUser->id,
        'student_code' => 'STU-HUB-001',
    ]);
    $program = Program::factory()->create([
        'organization_id' => (string) $organization->id,
        'code' => 'PRG-HUB-001',
        'name' => ['ar' => 'برنامج القراءة', 'en' => 'Reading Program'],
    ]);
    $level = Level::factory()->create([
        'program_id' => (string) $program->id,
        'code' => 'LVL-HUB-1',
        'name' => ['ar' => 'المستوى التمهيدي', 'en' => 'Foundation Level'],
    ]);
    $enrollment = Enrollment::query()->create([
        'organization_id' => (string) $organization->id,
        'student_profile_id' => (string) $student->id,
        'program_id' => (string) $program->id,
        'current_level_id' => (string) $level->id,
        'status' => EnrollmentStatus::Applied,
        'applied_at' => now()->utc(),
    ]);
    EnrollmentStatusHistory::query()->create([
        'enrollment_id' => (string) $enrollment->id,
        'from_status' => null,
        'to_status' => EnrollmentStatus::Applied->value,
        'reason' => 'طلب الالتحاق بالبرنامج التجريبي',
        'changed_by' => (string) $operator->id,
        'changed_at' => now()->utc(),
    ]);

    $this->actingAs($operator)
        ->get(EnrollmentResource::getUrl('create', panel: 'admin'))
        ->assertOk()
        ->assertSeeText(__('enrollments::filament.enrollment.student'))
        ->assertDontSee('data.organization_id', false);

    $this->get(EnrollmentResource::getUrl('view', ['record' => $enrollment], panel: 'admin'))
        ->assertOk()
        ->assertSeeText('سارة محمود')
        ->assertSeeText('STU-HUB-001')
        ->assertSeeText('برنامج القراءة')
        ->assertSeeText('المستوى التمهيدي')
        ->assertSeeText('طلب الالتحاق بالبرنامج التجريبي')
        ->assertSeeText(__('enrollments::filament.hub.history'));
});

it('does not expose an enrollment from another organization through filament', function (): void {
    Gate::before(static fn (): bool => true);
    Filament::setCurrentPanel('admin');

    $mine = Organization::factory()->create();
    $other = Organization::factory()->create();
    $operator = User::factory()->inOrganization((string) $mine->id)->create();
    $studentUser = User::factory()->inOrganization((string) $other->id)->create();
    $student = StudentProfile::factory()->create([
        'organization_id' => (string) $other->id,
        'user_id' => (string) $studentUser->id,
    ]);
    $program = Program::factory()->create(['organization_id' => (string) $other->id]);
    $enrollment = Enrollment::query()->create([
        'organization_id' => (string) $other->id,
        'student_profile_id' => (string) $student->id,
        'program_id' => (string) $program->id,
        'status' => EnrollmentStatus::Applied,
        'applied_at' => now()->utc(),
    ]);

    $this->actingAs($operator)
        ->get(EnrollmentResource::getUrl('view', ['record' => $enrollment], panel: 'admin'))
        ->assertNotFound();
});
