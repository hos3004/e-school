<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Messaging\Domain\Enums\ParticipantRole;
use Modules\Messaging\Domain\Models\ConversationParticipant;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ConversationParticipant>
 */
final class ConversationParticipantFactory extends Factory
{
    protected $model = ConversationParticipant::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'conversation_id' => null,
            'user_id' => Fixtures::userId(),
            'role' => ParticipantRole::Member,
            'joined_at' => CarbonImmutable::now('UTC'),
            'last_read_at' => null,
            'muted_until' => null,
        ];
    }

    public function inConversation(string $conversationId): static
    {
        return $this->state(fn (): array => ['conversation_id' => $conversationId]);
    }

    public function withRole(ParticipantRole $role): static
    {
        return $this->state(fn (): array => ['role' => $role]);
    }
}
