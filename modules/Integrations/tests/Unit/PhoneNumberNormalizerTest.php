<?php

declare(strict_types=1);

use Modules\Integrations\Infrastructure\Gateways\PhoneNumberNormalizer;

it('normalizes an Egyptian local mobile number to E.164', function (): void {
    $normalizer = new PhoneNumberNormalizer;

    expect($normalizer->normalize('01001234567', 'EG'))->toBe('+201001234567');
});

it('normalizes a Saudi local mobile number to E.164', function (): void {
    $normalizer = new PhoneNumberNormalizer;

    expect($normalizer->normalize('0551234567', 'SA'))->toBe('+966551234567');
});

it('preserves a valid international number without requiring a country', function (): void {
    $normalizer = new PhoneNumberNormalizer;

    expect($normalizer->normalize('0020 100 123 4567', null))->toBe('+201001234567');
});

it('rejects malformed and unsupported local numbers clearly', function (string $phone, ?string $country): void {
    $normalizer = new PhoneNumberNormalizer;

    expect(fn (): string => $normalizer->normalize($phone, $country))
        ->toThrow(InvalidArgumentException::class);
})->with([
    'malformed phone' => ['not-a-phone', 'EG'],
    'too short' => ['0123', 'EG'],
    'unsupported country' => ['0612345678', 'MA'],
]);
