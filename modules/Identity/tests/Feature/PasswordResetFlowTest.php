<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\Actions\IssuePasswordResetToken;
use Modules\Identity\Application\Actions\ResetPassword;
use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Events\PasswordResetRequested;
use Modules\Identity\Domain\Models\PasswordResetToken;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\IdentityPestContext;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    /** @var IdentityPestContext $this */
    $this->createTestOrganization();
});

it('issues a reset token and dispatches the request event', function (): void {
    /** @var IdentityPestContext $this */
    Event::fake([PasswordResetRequested::class]);

    User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'reset@eschool.test',
    ]);

    app(IssuePasswordResetToken::class)->execute('reset@eschool.test');

    expect(PasswordResetToken::query()->where('email', 'reset@eschool.test')->exists())->toBeTrue();

    Event::assertDispatched(PasswordResetRequested::class);
});

it('stays silent when the email does not exist', function (): void {
    /** @var IdentityPestContext $this */
    Event::fake([PasswordResetRequested::class]);

    app(IssuePasswordResetToken::class)->execute('ghost@eschool.test');

    expect(PasswordResetToken::query()->where('email', 'ghost@eschool.test')->exists())->toBeFalse();

    Event::assertNotDispatched(PasswordResetRequested::class);
});

it('resets the password with a valid token and dispatches completion', function (): void {
    /** @var IdentityPestContext $this */
    Event::fake([PasswordResetCompleted::class]);

    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'flow@eschool.test',
    ]);

    PasswordResetToken::query()->create([
        'email' => 'flow@eschool.test',
        'token' => Hash::make($raw = 'raw-reset-token'),
        'created_at' => now()->utc(),
    ]);

    $updated = app(ResetPassword::class)->execute('flow@eschool.test', $raw, 'N3w-Secret!');

    expect($updated->id)->toBe($user->id)
        ->and(Hash::check('N3w-Secret!', (string) $updated->fresh()->password))->toBeTrue()
        ->and(PasswordResetToken::query()->find('flow@eschool.test'))->toBeNull();

    Event::assertDispatched(PasswordResetCompleted::class, fn (PasswordResetCompleted $e): bool => $e->userId === $user->id);
});

it('rejects an invalid token without touching anything', function (): void {
    /** @var IdentityPestContext $this */
    /** @var User $user */
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'badtoken@eschool.test',
    ]);

    PasswordResetToken::query()->create([
        'email' => 'badtoken@eschool.test',
        'token' => Hash::make('the-real-token'),
        'created_at' => now()->utc(),
    ]);

    try {
        app(ResetPassword::class)->execute('badtoken@eschool.test', 'wrong-token', 'N3w-Secret!');
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.reset_token_invalid')
            ->and(Hash::check('password', (string) $user->fresh()->password))->toBeTrue();
    }
});

it('rejects an expired token', function (): void {
    /** @var IdentityPestContext $this */
    User::factory()->inOrganization($this->organizationId)->create([
        'email' => 'expired@eschool.test',
    ]);

    PasswordResetToken::factory()->expired()->create([
        'email' => 'expired@eschool.test',
        'token' => Hash::make('old-raw-token'),
    ]);

    try {
        app(ResetPassword::class)->execute('expired@eschool.test', 'old-raw-token', 'N3w-Secret!');
        $this->fail('Expected BusinessRuleViolation was not thrown.');
    } catch (BusinessRuleViolation $violation) {
        expect($violation->rule)->toBe('identity.reset_token_expired');
    }
});
