<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Identity\Application\Actions\RegisterDevice;
use Modules\Identity\Application\Actions\RevokeDevice;
use Modules\Identity\Domain\Events\DeviceRegistered;
use Modules\Identity\Domain\Events\DeviceRevoked;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\IdentityPestContext;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var IdentityPestContext $this */
    $this->createTestOrganization();
});

it('registers a device for a user', function (): void {
    /** @var IdentityPestContext $this */
    Event::fake([DeviceRegistered::class]);

    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    $device = app(RegisterDevice::class)->execute($user->id, [
        'device_name' => 'iPhone 15',
        'platform' => 'ios',
        'push_token' => str_repeat('a', 64),
    ]);

    expect($device->user_id)->toBe($user->id)
        ->and($device->isRevoked())->toBeFalse()
        ->and($device->canReceivePush())->toBeTrue();

    Event::assertDispatched(DeviceRegistered::class, fn (DeviceRegistered $e): bool => $e->deviceId === $device->id);
});

it('rejects a push token already bound to another active device', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $owner */
    $owner = User::factory()->inOrganization($this->organizationId)->create();
    /** @var User $other */
    $other = User::factory()->inOrganization($this->organizationId)->create();

    UserDevice::factory()->forUser($owner)->create(['push_token' => str_repeat('b', 64)]);

    try {
        app(RegisterDevice::class)->execute($other->id, [
            'push_token' => str_repeat('b', 64),
        ]);
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.push_token_in_use');
    }
});

it('revokes a device and clears its push token', function (): void {
    /** @var IdentityPestContext $this */
    Event::fake([DeviceRevoked::class]);

    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    /** @var UserDevice $device */
    $device = UserDevice::factory()->forUser($user)->create([
        'push_token' => str_repeat('c', 64),
    ]);

    $revoked = app(RevokeDevice::class)->execute($device);

    expect($revoked->isRevoked())->toBeTrue()
        ->and($revoked->push_token)->toBeNull()
        ->and($revoked->canReceivePush())->toBeFalse();

    Event::assertDispatched(DeviceRevoked::class);
});

it('rejects revoking an already revoked device', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create();

    /** @var UserDevice $device */
    $device = UserDevice::factory()->forUser($user)->revoked()->create();

    app(RevokeDevice::class)->execute($device);
})->throws(BusinessRuleViolation::class);

it('allows the same push token after it was revoked elsewhere', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $owner */
    $owner = User::factory()->inOrganization($this->organizationId)->create();
    /** @var User $newOwner */
    $newOwner = User::factory()->inOrganization($this->organizationId)->create();

    // رمز سابق على جهاز مسحوب لا يمنع إعادة استخدامه — الفحص للنشط فقط.
    UserDevice::factory()->forUser($owner)->revoked()->create([
        'push_token' => null,
    ]);

    $device = app(RegisterDevice::class)->execute($newOwner->id, [
        'push_token' => str_repeat('d', 64),
    ]);

    expect($device->exists)->toBeTrue();
});
