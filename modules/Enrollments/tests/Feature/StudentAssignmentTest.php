<?php

declare(strict_types=1);

namespace Modules\Enrollments\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Domain\Enums\ProgramType;
use Modules\Academics\Domain\Models\Program;
use Modules\Academics\Domain\Models\ProgramEligibility;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Enrollments\Application\Actions\AssignStudentToProgramAction;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Tests\TestCase;

final class StudentAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_uncleared_student_fails(): void
    {
        $orgId = (string) Str::ulid();
        $studentProfileId = (string) Str::ulid();

        $program = Program::create([
            'organization_id' => $orgId,
            'code' => 'PROG-UNAVAILABLE',
            'name' => ['ar' => 'برنامج اختبار'],
            'program_type' => ProgramType::Ongoing,
        ]);

        // Mock StudentAdmissionQueries to return false for isClearedForAssignment
        $mockQueries = $this->createMock(StudentAdmissionQueries::class);
        $mockQueries->method('isClearedForAssignment')->willReturn(false);
        $this->app->instance(StudentAdmissionQueries::class, $mockQueries);

        /** @var AssignStudentToProgramAction $action */
        $action = app(AssignStudentToProgramAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('enrollments::errors.student_not_cleared'));

        $action->execute(
            organizationId: $orgId,
            studentProfileId: $studentProfileId,
            programId: $program->id,
            facts: new ApplicantFacts,
        );
    }

    public function test_assigning_blocked_eligible_student_without_reason_fails(): void
    {
        $orgId = (string) Str::ulid();
        $studentProfileId = (string) Str::ulid();

        $program = Program::create([
            'organization_id' => $orgId,
            'code' => 'PROG-BLOCK',
            'name' => ['ar' => 'برنامج محظور'],
            'program_type' => ProgramType::Ongoing,
        ]);

        ProgramEligibility::create([
            'program_id' => $program->id,
            'countries' => [(string) Str::ulid()], // Only allowed country
        ]);

        // Mock StudentAdmissionQueries to return true
        $mockQueries = $this->createMock(StudentAdmissionQueries::class);
        $mockQueries->method('isClearedForAssignment')->willReturn(true);
        $this->app->instance(StudentAdmissionQueries::class, $mockQueries);

        /** @var AssignStudentToProgramAction $action */
        $action = app(AssignStudentToProgramAction::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(__('enrollments::errors.eligibility_blocked'));

        $action->execute(
            organizationId: $orgId,
            studentProfileId: $studentProfileId,
            programId: $program->id,
            facts: new ApplicantFacts(countryId: (string) Str::ulid()), // Different country
        );
    }

    public function test_assigning_blocked_student_with_override_and_reason_succeeds_and_audits(): void
    {
        $orgId = (string) Str::ulid();
        $studentProfileId = (string) Str::ulid();

        $program = Program::create([
            'organization_id' => $orgId,
            'code' => 'PROG-OVERRIDE',
            'name' => ['ar' => 'برنامج استثناء'],
            'program_type' => ProgramType::Ongoing,
        ]);

        ProgramEligibility::create([
            'program_id' => $program->id,
            'countries' => [(string) Str::ulid()],
            'manual_approval_required' => false,
        ]);

        // Mock StudentAdmissionQueries to return true
        $mockQueries = $this->createMock(StudentAdmissionQueries::class);
        $mockQueries->method('isClearedForAssignment')->willReturn(true);
        $this->app->instance(StudentAdmissionQueries::class, $mockQueries);

        // Authenticate user with permission
        $user = \Modules\Identity\Domain\Models\User::factory()->create();
        $this->actingAs($user);

        // Grant permission
        \Illuminate\Support\Facades\Gate::define(
            (string) config('admission.eligibility.override_permission', 'enrollment.override_eligibility'),
            fn () => true
        );

        /** @var AssignStudentToProgramAction $action */
        $action = app(AssignStudentToProgramAction::class);

        $enrollment = $action->execute(
            organizationId: $orgId,
            studentProfileId: $studentProfileId,
            programId: $program->id,
            facts: new ApplicantFacts(countryId: (string) Str::ulid()),
            overrideReason: 'موافقة استثنائية من المدير الإداري',
        );

        $this->assertNotNull($enrollment->id);
        $this->assertDatabaseHas('audit_log', [
            'action' => 'enrollment.eligibility_override',
            'auditable_id' => $enrollment->id,
            'reason' => 'موافقة استثنائية من المدير الإداري',
        ]);
    }
}
