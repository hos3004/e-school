<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\Actions\IssuePhonePasswordResetOtp;
use Modules\Identity\Application\Actions\ResetPasswordWithOtp;
use Modules\Identity\Domain\Contracts\PhonePasswordResetOtpDelivery;
use Modules\Identity\Domain\Events\PasswordResetCompleted;
use Modules\Identity\Domain\Events\PhonePasswordResetRequested;
use Modules\Identity\Domain\Models\User;
use Modules\Identity\Domain\Models\UserDevice;
use Modules\Identity\Tests\Concerns\CreatesTestOrganization;
use Modules\Identity\Tests\Support\FakePhonePasswordResetOtpDelivery;
use Shared\Support\BusinessRuleViolation;

uses(CreatesTestOrganization::class);

beforeEach(function (): void {
    $this->createTestOrganization();
    $this->phoneDelivery = new FakePhonePasswordResetOtpDelivery;
    app()->instance(PhonePasswordResetOtpDelivery::class, $this->phoneDelivery);
});

function insertPhoneResetOtp(User $user, string $otp, ?CarbonImmutable $expiresAt = null, int $attempts = 0): void
{
    DB::table('phone_password_reset_tokens')->insert([
        'user_id' => $user->id,
        'organization_id' => $user->organization_id,
        'token_hash' => Hash::make($otp),
        'attempts' => $attempts,
        'expires_at' => $expiresAt ?? CarbonImmutable::now('UTC')->addMinutes(10),
        'created_at' => now()->utc(),
        'updated_at' => now()->utc(),
    ]);
}

it('issues a tenant-bound hashed OTP through the delivery contract and a safe event', function (): void {
    Event::fake([PhonePasswordResetRequested::class]);
    $user = User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000001']);

    app(IssuePhonePasswordResetOtp::class)->execute($this->organizationId, '+20 100 000 0001');

    $record = DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->organization_id)->toBe($this->organizationId)
        ->and($this->phoneDelivery->deliveries)->toHaveCount(1)
        ->and(Hash::check($this->phoneDelivery->deliveries[0]['otp'], (string) $record->token_hash))->toBeTrue();

    Event::assertDispatched(PhonePasswordResetRequested::class, function (PhonePasswordResetRequested $event) use ($user): bool {
        return $event->userId === $user->id
            && $event->organizationId === $this->organizationId
            && !array_key_exists('otp', $event->payload())
            && !array_key_exists('phone', $event->payload());
    });
});

it('returns the same silent outcome for unknown and duplicate tenant phones', function (): void {
    User::factory()->count(2)->inOrganization($this->organizationId)->create(['phone' => '+201000000002']);

    app(IssuePhonePasswordResetOtp::class)->execute($this->organizationId, '+201000000002');
    app(IssuePhonePasswordResetOtp::class)->execute($this->organizationId, '+201000000999');

    expect($this->phoneDelivery->deliveries)->toBeEmpty()
        ->and(DB::table('phone_password_reset_tokens')->count())->toBe(0);
});

it('does not redeliver within the configured resend interval', function (): void {
    User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000003']);
    $action = app(IssuePhonePasswordResetOtp::class);

    $action->execute($this->organizationId, '+201000000003');
    $action->execute($this->organizationId, '+201000000003');

    expect($this->phoneDelivery->deliveries)->toHaveCount(1);
});

it('atomically resets once, rotates persistent access and revokes active devices', function (): void {
    Event::fake([PasswordResetCompleted::class]);
    $user = User::factory()->inOrganization($this->organizationId)->create([
        'phone' => '+201000000004',
        'remember_token' => 'old-remember-token',
    ]);
    $device = UserDevice::factory()->create(['user_id' => $user->id, 'revoked_at' => null, 'push_token' => 'push']);
    insertPhoneResetOtp($user, '123456');

    app(ResetPasswordWithOtp::class)->execute(
        $this->organizationId,
        '+201000000004',
        '123456',
        'Unique-Phone-Password-2026',
    );

    $fresh = $user->fresh();
    expect(Hash::check('Unique-Phone-Password-2026', (string) $fresh?->password))->toBeTrue()
        ->and($fresh?->remember_token)->not->toBe('old-remember-token')
        ->and(DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($device->fresh()?->revoked_at)->not->toBeNull()
        ->and($device->fresh()?->push_token)->toBeNull();

    expect(fn () => app(ResetPasswordWithOtp::class)->execute(
        $this->organizationId,
        '+201000000004',
        '123456',
        'Another-Phone-Password-2027',
    ))->toThrow(BusinessRuleViolation::class);

    Event::assertDispatched(PasswordResetCompleted::class, fn (PasswordResetCompleted $event): bool => $event->userId === $user->id);
});

it('persists invalid-attempt increments and removes the token at the configured limit', function (): void {
    $user = User::factory()->inOrganization($this->organizationId)->create(['phone' => '+201000000005']);
    $maximum = (int) config('identity.phone_password_reset.max_verification_attempts');
    insertPhoneResetOtp($user, '123456', attempts: $maximum - 2);

    foreach (range(1, 2) as $attempt) {
        try {
            app(ResetPasswordWithOtp::class)->execute(
                $this->organizationId,
                '+201000000005',
                '999999',
                'Unique-Phone-Password-2026',
            );
            $this->fail('Expected invalid OTP.');
        } catch (BusinessRuleViolation $violation) {
            expect($violation->rule)->toBe('identity.phone_reset_invalid');
        }

        $exists = DB::table('phone_password_reset_tokens')->where('user_id', $user->id)->exists();
        expect($exists)->toBe($attempt === 1);
    }
});

it('persists deletion of expired tokens and never crosses organization boundaries', function (): void {
    $firstOrganization = $this->organizationId;
    $otherOrganization = $this->createTestOrganization();
    $otherUser = User::factory()->inOrganization($otherOrganization)->create(['phone' => '+201000000006']);
    insertPhoneResetOtp($otherUser, '123456', CarbonImmutable::now('UTC')->subSecond());

    expect(fn () => app(ResetPasswordWithOtp::class)->execute(
        $firstOrganization,
        '+201000000006',
        '123456',
        'Unique-Phone-Password-2026',
    ))->toThrow(BusinessRuleViolation::class);

    expect(DB::table('phone_password_reset_tokens')->where('user_id', $otherUser->id)->exists())->toBeTrue();

    expect(fn () => app(ResetPasswordWithOtp::class)->execute(
        $otherOrganization,
        '+201000000006',
        '123456',
        'Unique-Phone-Password-2026',
    ))->toThrow(BusinessRuleViolation::class);

    expect(DB::table('phone_password_reset_tokens')->where('user_id', $otherUser->id)->exists())->toBeFalse();
});
