<?php

declare(strict_types=1);

namespace Modules\Students\Application\Actions;

use Modules\Students\Domain\Enums\RegistrationStatus;
use Modules\Students\Domain\Models\RegistrationApplication;
use Shared\Support\BusinessRuleViolation;
use Shared\Support\Transaction;

final readonly class ReviewRegistrationApplicationAction
{
    public function __construct(private Transaction $transaction) {}

    public function execute(RegistrationApplication $application, string $reviewerUserId): RegistrationApplication
    {
        return $this->transaction->run(function () use ($application, $reviewerUserId): RegistrationApplication {
            /** @var RegistrationApplication $locked */
            $locked = RegistrationApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->getKey());

            if (!$locked->status->canTransitionTo(RegistrationStatus::UnderReview)) {
                throw BusinessRuleViolation::make(
                    'registration.invalid_transition',
                    'students::errors.registration_invalid_transition',
                    ['from' => $locked->status->label(), 'to' => RegistrationStatus::UnderReview->label()],
                );
            }

            $locked->status = RegistrationStatus::UnderReview;
            $locked->reviewed_by = $reviewerUserId;
            $locked->reviewed_at = now()->utc();
            $locked->save();

            return $locked;
        });
    }
}
