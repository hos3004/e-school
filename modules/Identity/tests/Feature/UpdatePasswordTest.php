<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\Actions\UpdatePassword;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    $this->createTestOrganization();
});

it('changes the password when the current one is correct', function (): void {
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'password' => Hash::make('old-Secret-99'),
    ]);

    $updated = app(UpdatePassword::class)->execute($user, 'old-Secret-99', 'new-Secret-77');

    expect(Hash::check('new-Secret-77', (string) $updated->fresh()->password))->toBeTrue()
        ->and(Hash::check('old-Secret-99', (string) $updated->fresh()->password))->toBeFalse();
});

it('rejects a wrong current password', function (): void {
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'password' => Hash::make('real-current'),
    ]);

    try {
        app(UpdatePassword::class)->execute($user, 'wrong-current', 'new-Secret-77');
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.current_password_wrong')
            ->and(Hash::check('real-current', (string) $user->fresh()->password))->toBeTrue();
    }
});

it('rejects setting the same password again', function (): void {
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'password' => Hash::make('same-password-1'),
    ]);

    try {
        app(UpdatePassword::class)->execute($user, 'same-password-1', 'same-password-1');
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.password_unchanged');
    }
});
