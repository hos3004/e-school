<?php

declare(strict_types=1);

use Modules\Students\Domain\Enums\RegistrationStatus;

it('allows only the documented registration lifecycle transitions', function (): void {
    expect(RegistrationStatus::Draft->allowedTransitions())
        ->toBe([RegistrationStatus::Submitted])
        ->and(RegistrationStatus::Submitted->allowedTransitions())
        ->toBe([
            RegistrationStatus::UnderReview,
            RegistrationStatus::Accepted,
            RegistrationStatus::Rejected,
        ])
        ->and(RegistrationStatus::UnderReview->allowedTransitions())
        ->toBe([RegistrationStatus::Accepted, RegistrationStatus::Rejected])
        ->and(RegistrationStatus::Accepted->allowedTransitions())
        ->toBe([RegistrationStatus::WaitingAssignment])
        ->and(RegistrationStatus::WaitingAssignment->allowedTransitions())
        ->toBe([RegistrationStatus::Assigned]);
});

it('clears assignment only after acceptance and marks terminal states', function (): void {
    foreach ([
        RegistrationStatus::Draft,
        RegistrationStatus::Submitted,
        RegistrationStatus::UnderReview,
        RegistrationStatus::Accepted,
        RegistrationStatus::Rejected,
    ] as $status) {
        expect($status->isClearedForAssignment())->toBeFalse();
    }

    expect(RegistrationStatus::WaitingAssignment->isClearedForAssignment())->toBeTrue()
        ->and(RegistrationStatus::Assigned->isClearedForAssignment())->toBeTrue()
        ->and(RegistrationStatus::Rejected->isTerminal())->toBeTrue()
        ->and(RegistrationStatus::Assigned->isTerminal())->toBeTrue();
});
