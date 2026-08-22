<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Modules\Messaging\Application\Actions\CreateConversationAction;
use Modules\Messaging\Application\Actions\SendMessageAction;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Events\ConversationCreated;
use Modules\Messaging\Domain\Events\MessageSent;
use Modules\Messaging\Domain\Models\Conversation;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Shared\Support\BusinessRuleViolation;
use Shared\Testing\Fixtures;

function createConversationFor(string $userId): Conversation
{
    return app(CreateConversationAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        creatorUserId: $userId,
        type: ConversationType::Direct,
        subject: 'Direct test',
        participantUserIds: [$userId],
    );
}

it('creates a conversation with participants and dispatches the event', function (): void {
    Event::fake([ConversationCreated::class]);

    $creator = Fixtures::userId();
    $other = Fixtures::userId();

    $conversation = app(CreateConversationAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        creatorUserId: $creator,
        type: ConversationType::Group,
        subject: 'Test conversation',
        participantUserIds: [$other],
    );

    expect($conversation->exists)->toBeTrue()
        ->and($conversation->type)->toBe(ConversationType::Group->value)
        ->and($conversation->participants()->count())->toBe(2);

    Event::assertDispatched(ConversationCreated::class);
});

it('rejects direct conversations with more than two participants', function (): void {
    $creator = Fixtures::userId();
    $others = [
        Fixtures::userId(),
        Fixtures::userId(),
    ];

    app(CreateConversationAction::class)->execute(
        organizationId: Fixtures::organizationId(),
        creatorUserId: $creator,
        type: ConversationType::Direct,
        subject: 'Too many',
        participantUserIds: $others,
    );
})->throws(BusinessRuleViolation::class);

it('sends a message from a participant and updates last_message_at', function (): void {
    Event::fake([MessageSent::class]);

    $sender = Fixtures::userId();
    $conversation = createConversationFor($sender);
    $before = $conversation->last_message_at;

    $message = app(SendMessageAction::class)->execute(
        conversation: $conversation,
        senderUserId: $sender,
        body: 'Hello there',
    );

    expect($message->exists)->toBeTrue()
        ->and((string) $message->conversation_id)->toBe((string) $conversation->id)
        ->and($conversation->refresh()->last_message_at)->not->toBe($before);

    Event::assertDispatched(MessageSent::class);
});

it('rejects messages from non-participants', function (): void {
    $participant = Fixtures::userId();
    $outsider = Fixtures::userId();
    $conversation = createConversationFor($participant);

    app(SendMessageAction::class)->execute(
        conversation: $conversation,
        senderUserId: $outsider,
        body: 'I am not allowed',
    );
})->throws(BusinessRuleViolation::class);

it('keeps participants attached to the right conversation', function (): void {
    $user = Fixtures::userId();
    $conversation = createConversationFor($user);

    expect(ConversationParticipant::query()
        ->where('conversation_id', (string) $conversation->id)
        ->where('user_id', $user)
        ->exists())->toBeTrue();
});
