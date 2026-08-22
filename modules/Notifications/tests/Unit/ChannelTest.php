<?php

declare(strict_types=1);

use Modules\Notifications\Domain\Enums\Channel;

it('exposes every supported channel as a string value', function (): void {
    expect(Channel::values())->toBe(['email', 'sms', 'push', 'whatsapp', 'in_app'])
        ->and(count(Channel::cases()))->toBe(5);
});

it('translates channel labels', function (): void {
    foreach (Channel::cases() as $channel) {
        expect($channel->label())->not->toBeEmpty();
    }
});

it('round-trips from and to its value', function (): void {
    foreach (Channel::cases() as $channel) {
        expect(Channel::from($channel->value))->toBe($channel);
    }
});
