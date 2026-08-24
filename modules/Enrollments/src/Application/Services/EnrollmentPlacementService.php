<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Services;

use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class EnrollmentPlacementService implements EnrollmentPlacementGateway
{
    public function __construct(private Transaction $transaction) {}

    public function activate(
        string $organizationId,
        string $studentProfileId,
        string $programId,
    ): EnrollmentPlacementData {
        return $this->transaction->run(function () use ($organizationId, $studentProfileId, $programId): EnrollmentPlacementData {
            /** @var Enrollment|null $enrollment */
            $enrollment = Enrollment::query()
                ->withTrashed()
                ->where('student_profile_id', $studentProfileId)
                ->where('program_id', $programId)
                ->lockForUpdate()
                ->first();

            if ($enrollment !== null && (string) $enrollment->organization_id !== $organizationId) {
                throw BusinessRuleViolation::make(
                    'enrollments.organization_mismatch',
                    'enrollments::errors.organization_mismatch',
                );
            }

            $created = false;

            if ($enrollment === null) {
                $enrollment = Enrollment::query()->create([
                    'organization_id' => $organizationId,
                    'student_profile_id' => $studentProfileId,
                    'program_id' => $programId,
                    'status' => EnrollmentStatus::Active,
                    'applied_at' => now()->utc(),
                    'activated_at' => now()->utc(),
                ]);
                $created = true;
            } elseif ($enrollment->trashed()) {
                throw BusinessRuleViolation::make(
                    'enrollments.archived_enrollment',
                    'enrollments::errors.archived_enrollment',
                );
            } elseif ($enrollment->status !== EnrollmentStatus::Active) {
                if (!$enrollment->status->canTransitionTo(EnrollmentStatus::Active)) {
                    throw BusinessRuleViolation::make(
                        'enrollments.invalid_placement_transition',
                        'enrollments::errors.invalid_placement_transition',
                        [
                            'from' => $enrollment->status->label(),
                            'to' => EnrollmentStatus::Active->label(),
                        ],
                    );
                }

                $enrollment->status = EnrollmentStatus::Active;
                $enrollment->activated_at = now()->utc();
                $enrollment->save();
            }

            return new EnrollmentPlacementData(
                enrollmentId: (string) $enrollment->getKey(),
                organizationId: (string) $enrollment->organization_id,
                studentProfileId: (string) $enrollment->student_profile_id,
                programId: (string) $enrollment->program_id,
                status: $enrollment->status->value,
                created: $created,
            );
        });
    }
}
