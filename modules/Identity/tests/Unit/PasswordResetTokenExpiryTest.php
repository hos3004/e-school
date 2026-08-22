<?php

declare(strict_types=1);

use Modules\Identity\Domain\Models\PasswordResetToken;

it('accepts a token created inside the configured expiry window', function (): void {
    $token = PasswordResetToken::factory()->fresh()->make();

    expect($token->isFresh())->toBeTrue();
});

it('rejects a token older than the configured expiry window', function (): void {
    $expired = PasswordResetToken::factory()->expired()->make();

    expect($expired->isFresh())->toBeFalse();
});

it('rejects a token with no creation timestamp at all', function (): void {
    $token = new PasswordResetToken(['email' => 'a@b.c', 'token' => 'x']);

    expect($token->isFresh())->toBeFalse();
});
