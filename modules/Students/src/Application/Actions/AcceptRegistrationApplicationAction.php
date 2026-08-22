<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationAccepted;
use Modules\Students\Domain\Models\RegistrationApplication;
use Modules\Students\Domain\Models\StudentProfile;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class AcceptRegistrationApplicationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(RegistrationApplication $application, string $reviewerUserId): RegistrationApplication
    {
        /** @var array{0: RegistrationApplication, 1: bool} $result */
        $result = $this->transaction->run(function () use ($application, $reviewerUserId): array {
            /** @var RegistrationApplication $locked */
            $locked = RegistrationApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if ($locked->status === RegistrationStatus::WaitingAssignment && $locked->student_profile_id !== null) {
                return [$locked, false];
            }

            if ($locked->status !== RegistrationStatus::Accepted
                && !$locked->status->canTransitionTo(RegistrationStatus::Accepted)) {
                throw BusinessRuleViolation::make(
                    'registration.invalid_transition',
                    'students::errors.registration_invalid_transition',
                    ['from' => $locked->status->label(), 'to' => RegistrationStatus::Accepted->label()],
                );
            }

            if ($locked->user_id === null || trim($locked->user_id) === '') {
                throw BusinessRuleViolation::make(
                    'registration.user_account_required',
                    'students::errors.registration_user_account_required',
                );
            }

            if (StudentProfile::query()->withTrashed()->where('user_id', $locked->user_id)->exists()) {
                throw BusinessRuleViolation::make(
                    'registration.student_profile_exists',
                    'students::errors.registration_student_profile_exists',
                );
            }

            // حفظ حالة accepted داخل المعاملة يجعل التسلسل صريحًا قبل إنشاء الملف.
            $locked->status = RegistrationStatus::Accepted;
            $locked->reviewed_by = $reviewerUserId;
            $locked->reviewed_at = now()->utc();
            $locked->save();

            $profile = new StudentProfile();
            $profile->organization_id = (string) $locked->organization_id;
            $profile->user_id = $locked->user_id;
            $profile->student_code = (string) $locked->getKey();
            $profile->date_of_birth = $locked->date_of_birth;
            $profile->gender = $locked->gender->value;
            $profile->country_id = $locked->country_id;
            $profile->region_id = $locked->region_id;
            $profile->joined_at = now()->utc()->toDateString();
            $profile->save();

            $locked->student_profile_id = (string) $profile->getKey();
            $locked->status = RegistrationStatus::WaitingAssignment;
            $locked->save();

            return [$locked, true];
        });

        [$application, $created] = $result;

        if ($created) {
            $this->events->dispatch(new RegistrationAccepted(
                applicationId: (string) $application->id,
                organizationId: (string) $application->organization_id,
                studentProfileId: (string) $application->student_profile_id,
                studentUserId: (string) $application->user_id,
                actorId: $reviewerUserId,
            ));
        }

        return $application;
    }
}
