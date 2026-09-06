<?php

declare(strict_types=1);

namespace App\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Modules\Academics\Domain\Contracts\ProgramEligibilityEvaluator;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Students\Application\Services\ConsoleQuranRegistrationService;
use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

/** One transaction for accepted Quran request -> enrollment -> schedule -> selected request. */
final readonly class PlaceConsoleQuranStudentAction
{
    public function __construct(
        private BulkCreateIndividualQuranSchedulesAction $schedules,
        private ConsoleQuranRegistrationService $registrations,
        private EnrollmentPlacementGateway $enrollments,
        private StudentPlacementGateway $students,
        private ProgramEligibilityEvaluator $eligibility,
        private Transaction $transaction,
    ) {}

    /** @param array<string, mixed> $data */
    public function execute(string $organizationId, string $studentId, string $programId, string $courseId, array $data, string $actorId): string
    {
        return $this->transaction->run(function () use ($organizationId, $studentId, $programId, $courseId, $data, $actorId): string {
            $applicationId = $data['application_id'] ?? null;
            $reason = __('students::admin.individual_quran.placement_audit_reason');
            if ($applicationId !== null) {
                Gate::authorize('enrollment.create');
                $this->registrations->authorizePlacement($organizationId, $studentId, $applicationId, $programId, $courseId);
                $student = $this->students->findCleared($studentId, $applicationId);
                if ($student === null || $student->organizationId !== $organizationId) {
                    throw BusinessRuleViolation::make('enrollments.student_not_cleared', 'enrollments::errors.student_not_cleared');
                }
                $result = $this->eligibility->evaluate($programId, new ApplicantFacts(
                    dateOfBirth: $student->dateOfBirth === null ? null : CarbonImmutable::parse($student->dateOfBirth),
                    gender: $student->gender, countryId: $student->countryId, regionId: $student->regionId,
                ));
                if (!$result->eligible) {
                    throw BusinessRuleViolation::make('enrollments.eligibility_blocked', 'enrollments::errors.eligibility_blocked', ['violations' => implode(',', $result->blocking)]);
                }
                $this->enrollments->activate($organizationId, $studentId, $programId, $reason, $actorId);
            }

            return $this->schedules->executeSingle(
                organizationId: $organizationId, studentProfileId: $studentId, staffProfileId: $data['staff_profile_id'],
                weeklySlots: $data['weekly_slots'], intervalWeeks: (int) $data['interval_weeks'], durationMinutes: (int) $data['duration_minutes'],
                timezone: $data['timezone'], startsOn: $data['starts_on'], endsOn: $data['ends_on'] ?? null,
                actorId: $actorId, reason: $reason, applicationId: $applicationId,
            );
        });
    }
}
