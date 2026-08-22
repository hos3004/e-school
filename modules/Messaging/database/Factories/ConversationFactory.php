<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Messaging\Domain\Enums\ConversationType;
use Modules\Messaging\Domain\Models\Conversation;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Conversation>
 */
final class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'subject' => $this->faker->sentence(4),
            'type' => ConversationType::Group,
            'is_moderated' => true,
            'related_type' => null,
            'related_id' => null,
            'created_by' => Fixtures::userId(),
            'last_message_at' => null,
        ];
    }

    public function ofType(ConversationType $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
