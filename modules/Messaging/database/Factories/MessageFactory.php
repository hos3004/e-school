<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Messaging\Domain\Models\Message;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Message>
 */
final class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'conversation_id' => null,
            'user_id' => Fixtures::userId(),
            'body' => $this->faker->paragraph(),
            'attachments' => [],
            'is_flagged' => false,
            'flagged_reason' => null,
            'moderated_by' => null,
            'moderated_at' => null,
            'edited_at' => null,
        ];
    }

    /**
     * يُستدعى بعد تعيين conversation_id صراحة لضبط created_at.
     */
    public function sentAt(CarbonImmutable $at): static
    {
        return $this->state(fn (): array => ['created_at' => $at]);
    }
}
