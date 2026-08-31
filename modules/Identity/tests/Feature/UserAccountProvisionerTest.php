<?php

declare(strict_types=1);

use Illuminate\Support\Str;
use Modules\Identity\Domain\Contracts\DTOs\CreateUserAccountData;
use Modules\Identity\Domain\Contracts\UserAccountProvisioner;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $this->createTestOrganization();
});

it('creates a tenant-fixed account and returns only a public DTO', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $account = app(UserAccountProvisioner::class)->create(new CreateUserAccountData(
        organizationId: $this->organizationId,
        name: 'Imported Student',
        email: 'provisioned@example.test',
        username: 'provisioned.student',
        phone: null,
        password: 'Unique-Provisioned-Password-2026',
    ));

    expect($account->organizationId)->toBe($this->organizationId)
        ->and($account->email)->toBe('provisioned@example.test')
        ->and($account)->not->toBeInstanceOf(User::class);
});

it('confirms only an explicitly identified same-tenant account with matching contact', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'verified-link@example.test',
        'phone' => '+201000000010',
    ]);

    $confirmed = app(UserAccountProvisioner::class)->confirmExistingAccount(
        $this->organizationId,
        $user->id,
        'VERIFIED-LINK@example.test',
        null,
    );

    expect($confirmed->id)->toBe($user->id)
        ->and($confirmed->organizationId)->toBe($this->organizationId);
});

it('never auto-links by contact across users or organizations', function (): void {
    /** @var \Modules\Identity\Tests\Support\IdentityPestContext $this */
    $firstOrganization = $this->organizationId;
    $otherOrganization = $this->createTestOrganization();
    $other = User::factory()->inOrganization($otherOrganization)->create([
        'email' => 'do-not-link@example.test',
        'phone' => '+201000000011',
    ]);

    expect(fn () => app(UserAccountProvisioner::class)->confirmExistingAccount(
        $firstOrganization,
        $other->id,
        $other->email,
        $other->phone,
    ))->toThrow(BusinessRuleViolation::class);

    expect(fn () => app(UserAccountProvisioner::class)->confirmExistingAccount(
        $otherOrganization,
        (string) Str::ulid(),
        $other->email,
        $other->phone,
    ))->toThrow(BusinessRuleViolation::class);
});
