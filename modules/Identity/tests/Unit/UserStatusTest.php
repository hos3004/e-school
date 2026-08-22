<?php

declare(strict_types=1);

use Modules\Identity\Domain\Enums\UserStatus;

it('allows only the documented status transitions', function (): void {
    expect(UserStatus::Active->canTransitionTo(UserStatus::Suspended))->toBeTrue()
        ->and(UserStatus::Active->canTransitionTo(UserStatus::Frozen))->toBeTrue()
        ->and(UserStatus::Suspended->canTransitionTo(UserStatus::Active))->toBeTrue()
        ->and(UserStatus::Suspended->canTransitionTo(UserStatus::Frozen))->toBeTrue()
        ->and(UserStatus::Frozen->canTransitionTo(UserStatus::Active))->toBeTrue()
        ->and(UserStatus::Frozen->canTransitionTo(UserStatus::Suspended))->toBeTrue();
});

it('rejects transitions outside the state machine', function (): void {
    // النشط لا ينتقل إلى نفسه، والمجمّد لا يقفز للتجميد المباشر من النشط.
    expect(UserStatus::Active->canTransitionTo(UserStatus::Active))->toBeFalse();
});

it('only allows login while active', function (): void {
    expect(UserStatus::Active->allowsLogin())->toBeTrue()
        ->and(UserStatus::Suspended->allowsLogin())->toBeFalse()
        ->and(UserStatus::Frozen->allowsLogin())->toBeFalse();
});

it('exposes translated labels', function (): void {
    expect(UserStatus::Active->label())->toBeString()
        ->not->toBe('')
        ->and(UserStatus::Suspended->label())->toBeString()
        ->and(UserStatus::Frozen->label())->toBeString();
});
