<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Illuminate\Contracts\Events\Dispatcher;
use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Events\RegistrationRejected;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class RejectRegistrationApplicationAction
{
    public function __construct(
        private Transaction $transaction,
        private Dispatcher $events,
    ) {}

    public function execute(RegistrationApplication $application, string $reason, string $reviewerUserId): RegistrationApplication
    {
        $reason = trim($reason);

        $requiresReason = (bool) config('admission.application.rejection_requires_reason', true);
        if ($requiresReason && $reason === '') {
            throw BusinessRuleViolation::make(
                'registration.rejection_reason_required',
                'students::errors.registration_rejection_reason_required',
            );
        }

        $application = $this->transaction->run(function () use ($application, $reason, $reviewerUserId): RegistrationApplication {
            /** @var RegistrationApplication $locked */
            $locked = RegistrationApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if (!$locked->status->canTransitionTo(RegistrationStatus::Rejected)) {
                throw BusinessRuleViolation::make(
                    'registration.invalid_transition',
                    'students::errors.registration_invalid_transition',
                    ['from' => $locked->status->label(), 'to' => RegistrationStatus::Rejected->label()],
                );
            }

            $locked->status = RegistrationStatus::Rejected;
            $locked->decision_reason = $reason;
            $locked->reviewed_by = $reviewerUserId;
            $locked->reviewed_at = now()->utc();
            $locked->save();

            return $locked;
        });

        $this->events->dispatch(new RegistrationRejected(
            applicationId: (string) $application->id,
            organizationId: (string) $application->organization_id,
            reason: $reason,
            studentUserId: $application->user_id,
            actorId: $reviewerUserId,
        ));

        return $application;
    }
}
