<?php

declare(strict_types=1);

use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\IdentityPestContext;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var IdentityPestContext $this */
    $this->createTestOrganization();
});

it('revokes mobile tokens and active devices when a reset completes', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    $user->createToken('mobile');
    $device = UserDevice::factory()->forUser($user)->create([
        'push_token' => str_repeat('a', 64),
    ]);

    expect($user->tokens()->count())->toBe(1)
        ->and($device->revoked_at)->toBeNull();

    event(new PasswordResetCompleted(
        userId: $user->id,
        organizationId: $user->organization_id,
    ));

    $device->refresh();

    expect($user->tokens()->count())->toBe(0)
        ->and($device->revoked_at)->not->toBeNull()
        ->and($device->push_token)->toBeNull();
});

it('does not touch other users tokens or devices', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $target */
    $target = User::factory()->inOrganization($this->organizationId)->create();
    /** @var User $bystander */
    $bystander = User::factory()->inOrganization($this->organizationId)->create();

    $target->createToken('mobile');
    $bystander->createToken('mobile');
    $bystanderDevice = UserDevice::factory()->forUser($bystander)->create([
        'push_token' => str_repeat('b', 64),
    ]);

    event(new PasswordResetCompleted(
        userId: $target->id,
        organizationId: $target->organization_id,
    ));

    $bystanderDevice->refresh();

    expect($target->tokens()->count())->toBe(0)
        ->and($bystander->tokens()->count())->toBe(1)
        ->and($bystanderDevice->revoked_at)->toBeNull();
});

it('is safe to run when the user has no tokens or devices', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    event(new PasswordResetCompleted(
        userId: $user->id,
        organizationId: $user->organization_id,
    ));

    expect($user->tokens()->count())->toBe(0);
});
