<?php

declare(strict_types=1);

namespace Modules\Students\Application\Services;

use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\ValueObjects\StudentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class StudentPlacementService implements StudentPlacementGateway
{
    public function __construct(private Transaction $transaction) {}

    public function findCleared(string $studentProfileId): ?StudentPlacementData
    {
        /** @var RegistrationApplication|null $application */
        $application = RegistrationApplication::query()
            ->where('student_profile_id', $studentProfileId)
            ->whereIn('status', [
                RegistrationStatus::WaitingAssignment->value,
                RegistrationStatus::Assigned->value,
            ])
            ->first();

        if ($application === null || $application->user_id === null) {
            return null;
        }

        return new StudentPlacementData(
            applicationId: (string) $application->getKey(),
            organizationId: (string) $application->organization_id,
            studentProfileId: (string) $application->student_profile_id,
            studentUserId: (string) $application->user_id,
            status: $application->status->value,
            dateOfBirth: $application->date_of_birth?->toDateString(),
            gender: $application->gender?->value,
            countryId: $application->country_id,
            regionId: $application->region_id,
        );
    }

    public function markAssigned(string $organizationId, string $studentProfileId): void
    {
        $this->transaction->run(function () use ($organizationId, $studentProfileId): void {
            /** @var RegistrationApplication|null $application */
            $application = RegistrationApplication::query()
                ->forOrganization($organizationId)
                ->where('student_profile_id', $studentProfileId)
                ->lockForUpdate()
                ->first();

            if ($application === null) {
                throw BusinessRuleViolation::make(
                    'registration.not_cleared_for_assignment',
                    'students::errors.registration_not_cleared_for_assignment',
                );
            }

            if ($application->status === RegistrationStatus::Assigned) {
                return;
            }

            if (!$application->status->canTransitionTo(RegistrationStatus::Assigned)) {
                throw BusinessRuleViolation::make(
                    'registration.invalid_transition',
                    'students::errors.registration_invalid_transition',
                    [
                        'from' => $application->status->label(),
                        'to' => RegistrationStatus::Assigned->label(),
                    ],
                );
            }

            $application->status = RegistrationStatus::Assigned;
            $application->save();
        });
    }
}
