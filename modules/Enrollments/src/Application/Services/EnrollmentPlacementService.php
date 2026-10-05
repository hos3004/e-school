<?php

declare(strict_types=1);

namespace Modules\Enrollments\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Audit\Domain\Contracts\AuditRecorder;
use Modules\Enrollments\Domain\Contracts\EnrollmentPlacementGateway;
use Modules\Enrollments\Domain\Enums\EnrollmentStatus;
use Modules\Enrollments\Domain\Models\Enrollment;
use Modules\Enrollments\Domain\Models\EnrollmentStatusHistory;
use Modules\Enrollments\Domain\ValueObjects\EnrollmentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class EnrollmentPlacementService implements EnrollmentPlacementGateway
{
    public function __construct(
        private Transaction $transaction,
        private AuditRecorder $audit,
    ) {}

    public function activate(
        string $organizationId,
        string $studentProfileId,
        string $programId,
        string $reason,
        ?string $actorId = null,
        ?string $correlationId = null,
    ): EnrollmentPlacementData {
        $reason = trim($reason);
        if ($reason === '') {
            throw BusinessRuleViolation::make(
                'enrollments.placement_reason_required',
                'enrollments::errors.placement_reason_required',
            );
        }

        return $this->transaction->run(function () use ($organizationId, $studentProfileId, $programId, $reason, $actorId, $correlationId): EnrollmentPlacementData {
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
            $fromStatus = $enrollment?->status;

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
                if ($enrollment->status === EnrollmentStatus::UnderAssessment) {
                    throw BusinessRuleViolation::make(
                        'enrollments.reactivation_requires_permission',
                        'enrollments::errors.reactivation_requires_permission',
                        ['permission' => 'enrollment.reactivate'],
                    );
                }

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

            if ($created || $fromStatus !== EnrollmentStatus::Active) {
                $resolvedActorId = $actorId ?? (auth()->id() === null ? 'system' : (string) auth()->id());

                EnrollmentStatusHistory::query()->create([
                    'enrollment_id' => (string) $enrollment->getKey(),
                    'from_status' => $fromStatus?->value,
                    'to_status' => EnrollmentStatus::Active->value,
                    'reason' => $reason,
                    'changed_by' => $resolvedActorId,
                    'changed_at' => CarbonImmutable::now('UTC'),
                ]);

                $this->audit->record(
                    organizationId: $organizationId,
                    actorId: $actorId,
                    actorType: $actorId === null ? 'system' : 'user',
                    action: $created ? 'enrollments.created_by_placement' : 'enrollments.activated_by_placement',
                    auditableType: 'enrollments',
                    auditableId: (string) $enrollment->getKey(),
                    oldValues: $fromStatus === null ? null : ['status' => $fromStatus->value],
                    newValues: [
                        'status' => EnrollmentStatus::Active->value,
                        'student_profile_id' => $studentProfileId,
                        'program_id' => $programId,
                    ],
                    reason: $reason,
                    correlationId: $correlationId,
                );
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
