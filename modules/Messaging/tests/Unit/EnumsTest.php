<?php

declare(strict_types=1);

use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Enums\ParticipantRole;

it('exposes labels through translation files', function (): void {
    expect(ConversationType::Direct->label())->toBeString()
        ->and(ParticipantRole::Owner->label())->toBeString();
});

it('knows which conversation types allow multiple participants', function (): void {
    expect(ConversationType::Direct->allowsMultipleParticipants())->toBeFalse()
        ->and(ConversationType::Group->allowsMultipleParticipants())->toBeTrue()
        ->and(ConversationType::class->allowsMultipleParticipants())->toBeTrue();
});

it('knows which participant roles can moderate', function (): void {
    expect(ParticipantRole::Owner->canModerate())->toBeTrue()
        ->and(ParticipantRole::Moderator->canModerate())->toBeTrue()
        ->and(ParticipantRole::Member->canModerate())->toBeFalse();
});

it('restricts role transitions', function (): void {
    expect(ParticipantRole::Member->canTransitionTo(ParticipantRole::Moderator))->toBeTrue()
        ->and(ParticipantRole::Moderator->canTransitionTo(ParticipantRole::Member))->toBeTrue()
        ->and(ParticipantRole::Owner->canTransitionTo(ParticipantRole::Member))->toBeFalse();
});
