<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Identity\Application\Actions\ChangeUserStatus;
use Modules\Identity\Domain\Enums\UserStatus;
use Modules\Identity\Domain\Events\UserStatusChanged;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    $this->createTestOrganization();

});

function statusTarget(string $organizationId, UserStatus $status): User
{
    /** @var User $user */
    $user = User::factory()->inOrganization($organizationId)->create([
        'status' => $status,
    ]);

    return $user;
}

it('suspends an active user and records the reason', function (): void {
    Event::fake([UserStatusChanged::class]);

    $admin = statusTarget($this->organizationId, UserStatus::Active);
    $target = statusTarget($this->organizationId, UserStatus::Active);

    $action = app(ChangeUserStatus::class);
    $updated = $action->execute($target, UserStatus::Suspended, 'تكرار المخالفات', $admin->id);

    expect($updated->status)->toBe(UserStatus::Suspended)
        ->and($updated->fresh()->status)->toBe(UserStatus::Suspended);

    Event::assertDispatched(UserStatusChanged::class, fn (UserStatusChanged $e): bool => $e->userId === $target->id
        && $e->from === 'active'
        && $e->to === 'suspended'
        && $e->reason === 'تكرار المخالفات');
});

it('rejects an empty reason', function (): void {
    $target = statusTarget($this->organizationId, UserStatus::Active);

    app(ChangeUserStatus::class)
        ->execute($target, UserStatus::Suspended, '');
})->throws(BusinessRuleViolation::class);

it('rejects a user changing their own status', function (): void {
    $self = statusTarget($this->organizationId, UserStatus::Active);

    app(ChangeUserStatus::class)
        ->execute($self, UserStatus::Suspended, 'سبب مشروع', $self->id);
})->throws(BusinessRuleViolation::class);

it('rejects transitions outside the state machine', function (): void {
    $admin = statusTarget($this->organizationId, UserStatus::Active);
    // الموقوف لا ينتقل إلى نفسه — انتقال غير معرَّف في الآلة.
    $target = statusTarget($this->organizationId, UserStatus::Suspended);

    try {
        app(ChangeUserStatus::class)
            ->execute($target, UserStatus::Suspended, 'سبب مشروع', $admin->id);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.invalid_status_transition');

        return;
    }

    $this->fail('Unreachable.');
});
