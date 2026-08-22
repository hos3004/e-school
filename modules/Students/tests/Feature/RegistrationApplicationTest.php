<?php

declare(strict_types=1);

namespace Modules\Students\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Modules\Identity\Domain\Models\User;
use Modules\Organization\Database\Seeders\GeographySeeder;
use Modules\Organization\Domain\Contracts\GeographyQueries;
use Modules\Students\Application\Actions\AcceptRegistrationApplicationAction;
use Modules\Students\Application\Actions\RejectRegistrationApplicationAction;
use Modules\Students\Application\Actions\SubmitRegistrationApplicationAction;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Events\RegistrationRejected;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Testing\Fixtures;
use Tests\TestCase;

final class RegistrationApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(GeographySeeder::class);
    }

    public function test_accepted_application_creates_student_profile_and_clears_for_assignment(): void
    {
        $orgId = Fixtures::organizationId();
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf($egypt->id);

        $application = RegistrationApplication::query()->create([
            'organization_id' => $orgId,
            'user_id' => Fixtures::userId(),
            'status' => RegistrationStatus::Draft,
            'full_name' => 'Ahmad Student',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $egypt->id,
            'region_id' => $regions[0]->id,
            'email' => 'ahmad.student@test.local',
        ]);

        app(SubmitRegistrationApplicationAction::class)->execute($application);
        $this->assertSame(RegistrationStatus::Submitted, $application->status);

        app(AcceptRegistrationApplicationAction::class)->execute($application, Fixtures::userId());
        $this->assertSame(RegistrationStatus::WaitingAssignment, $application->status);
        $this->assertNotNull($application->student_profile_id);

        /** @var StudentAdmissionQueries $admissionQueries */
        $admissionQueries = app(StudentAdmissionQueries::class);
        $this->assertTrue($admissionQueries->isClearedForAssignment($application->student_profile_id));
    }

    public function test_rejected_application_without_reason_fails(): void
    {
        $orgId = Fixtures::organizationId();
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf($egypt->id);

        $application = RegistrationApplication::query()->create([
            'organization_id' => $orgId,
            'status' => RegistrationStatus::Submitted,
            'full_name' => 'Rejected Student',
            'date_of_birth' => '2012-01-01',
            'gender' => 'female',
            'country_id' => $egypt->id,
            'region_id' => $regions[0]->id,
            'email' => 'reject@test.local',
        ]);

        $reviewer = User::factory()->create(['organization_id' => $orgId]);
        Gate::define('student.create', fn (): bool => true);

        $this->actingAs($reviewer)
            ->postJson("/api/registration-applications/{$application->id}/reject", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->assertDatabaseCount('student_profiles', 0);
    }

    public function test_student_assignment_cleared_check_fails_if_not_accepted(): void
    {
        /** @var StudentAdmissionQueries $admissionQueries */
        $admissionQueries = app(StudentAdmissionQueries::class);
        $fakeProfileId = (string) Str::ulid();

        $this->assertFalse($admissionQueries->isClearedForAssignment($fakeProfileId));
    }

    public function test_rejected_application_does_not_create_student_profile(): void
    {
        Event::fake([RegistrationRejected::class]);
        $application = $this->submittedApplication('rejected-profile@example.test');

        app(RejectRegistrationApplicationAction::class)->execute(
            $application,
            'The application does not meet the current admission criteria.',
            Fixtures::userId(),
        );

        $this->assertSame(RegistrationStatus::Rejected, $application->fresh()->status);
        $this->assertNull($application->fresh()->student_profile_id);
        $this->assertSame(0, StudentProfile::query()->count());
        Event::assertDispatched(RegistrationRejected::class);
    }

    public function test_duplicate_contact_is_flagged_without_blocking_submission(): void
    {
        $original = $this->submittedApplication('duplicate@example.test');
        $duplicate = $this->draftApplication('duplicate@example.test');

        app(SubmitRegistrationApplicationAction::class)->execute($duplicate);

        $this->assertSame(RegistrationStatus::Submitted, $duplicate->fresh()->status);
        $this->assertSame((string) $original->id, $duplicate->fresh()->duplicate_of_application_id);
    }

    public function test_acceptance_dispatches_event_with_real_recipient_user_id(): void
    {
        Event::fake([RegistrationAccepted::class]);
        $application = $this->submittedApplication('accepted-event@example.test');

        app(AcceptRegistrationApplicationAction::class)->execute($application, Fixtures::userId());

        Event::assertDispatched(
            RegistrationAccepted::class,
            fn (RegistrationAccepted $event): bool => $event->studentUserId === $application->user_id,
        );
    }

    private function draftApplication(string $email): RegistrationApplication
    {
        /** @var GeographyQueries $geography */
        $geography = app(GeographyQueries::class);
        $egypt = $geography->findCountryByIso2('EG');
        $regions = $geography->regionsOf((string) $egypt?->id);

        return RegistrationApplication::query()->create([
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
            'status' => RegistrationStatus::Draft,
            'full_name' => 'Test Student',
            'date_of_birth' => '2010-05-15',
            'gender' => 'male',
            'country_id' => $egypt?->id,
            'region_id' => $regions[0]->id,
            'email' => $email,
        ]);
    }

    private function submittedApplication(string $email): RegistrationApplication
    {
        $application = $this->draftApplication($email);

        return app(SubmitRegistrationApplicationAction::class)->execute($application);
    }
}
