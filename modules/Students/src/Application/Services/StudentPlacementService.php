<?php

declare(strict_types=1);

namespace Modules\Students\Application\Services;

use Modules\Identity\Domain\Contracts\UserQueryService;
use Modules\Students\Domain\Contracts\StudentPlacementGateway;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Modules\Students\Domain\ValueObjects\StudentPlacementData;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class StudentPlacementService implements StudentPlacementGateway
{
    public function __construct(private Transaction $transaction, private UserQueryService $users) {}

    public function findCleared(string $studentProfileId, ?string $applicationId = null): ?StudentPlacementData
    {
        $profile = StudentProfile::query()->whereKey($studentProfileId)->lockForUpdate()->first();
        if ($profile === null) {
            return null;
        }
        $user = $this->users->findSummary($profile->user_id);
        if ($user === null || $user->organizationId !== $profile->organization_id || !$user->isActive()) {
            return null;
        }
        /** @var RegistrationApplication|null $application */
        $application = RegistrationApplication::query()
            ->forOrganization($profile->organization_id)->where('user_id', $profile->user_id)
            ->when($applicationId !== null, fn ($query) => $query->whereKey($applicationId))
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
            dateOfBirth: $profile->date_of_birth?->toDateString(),
            gender: $profile->gender?->value,
            countryId: $profile->country_id,
            regionId: $profile->region_id,
        );
    }

    public function markAssigned(string $organizationId, string $studentProfileId, ?string $applicationId = null): void
    {
        $this->transaction->run(function () use ($organizationId, $studentProfileId, $applicationId): void {
            /** @var RegistrationApplication|null $application */
            $application = RegistrationApplication::query()
                ->forOrganization($organizationId)
                ->when($applicationId !== null, fn ($query) => $query->whereKey($applicationId))
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
