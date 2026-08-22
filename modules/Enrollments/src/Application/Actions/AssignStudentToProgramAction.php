<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Academics\Application\Services\EligibilityEvaluator;
use Modules\Academics\Domain\ValueObjects\ApplicantFacts;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Students\Domain\Contracts\StudentAdmissionQueries;

final class AssignStudentToProgramAction
{
    public function __construct(
        private readonly StudentAdmissionQueries $studentQueries,
        private readonly EligibilityEvaluator $eligibilityEvaluator,
    ) {}

    public function execute(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        ApplicantFacts $facts,
        ?string $overrideReason = null,
    ): Enrollment {
        // 1. Must be cleared for assignment by Students module
        if (!$this->studentQueries->isClearedForAssignment($studentProfileId)) {
            throw new \InvalidArgumentException(__('enrollments::errors.student_not_cleared'));
        }

        // 2. Evaluate Program Eligibility
        $eligibilityResult = $this->eligibilityEvaluator->evaluate($programId, $facts);

        if (!$eligibilityResult->eligible) {
            $overridePermission = (string) config('admission.eligibility.override_permission', 'enrollment.override_eligibility');
            $user = auth()->user();

            $canOverride = $user !== null && $user->can($overridePermission);
            $hasReason = is_string($overrideReason) && trim($overrideReason) !== '';

            if (!$canOverride || !$hasReason) {
                throw new \InvalidArgumentException(__('enrollments::errors.eligibility_blocked'));
            }
        }

        return DB::transaction(function () use ($organizationId, $studentProfileId, $programId, $eligibilityResult, $overrideReason): Enrollment {
            /** @var Enrollment $enrollment */
            $enrollment = Enrollment::create([
                'organization_id' => $organizationId,
                'student_profile_id' => $studentProfileId,
                'program_id' => $programId,
                'status' => $eligibilityResult->requiresManualApproval ? EnrollmentStatus::Applied : EnrollmentStatus::Active,
                'applied_at' => now(),
                'activated_at' => $eligibilityResult->requiresManualApproval ? null : now(),
            ]);

            if (!$eligibilityResult->eligible && is_string($overrideReason) && trim($overrideReason) !== '') {
                DB::table('audit_log')->insert([
                    'id' => (string) Str::ulid(),
                    'user_id' => auth()->id(),
                    'action' => 'enrollment.eligibility_override',
                    'auditable_type' => Enrollment::class,
                    'auditable_id' => $enrollment->id,
                    'reason' => $overrideReason,
                    'created_at' => now(),
                ]);
            }

            return $enrollment;
        });
    }
}
