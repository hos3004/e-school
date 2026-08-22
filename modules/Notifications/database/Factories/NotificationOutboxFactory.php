<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Enums\OutboxStatus;
use Modules\Notifications\Domain\Models\NotificationOutbox;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<NotificationOutbox>
 */
final class NotificationOutboxFactory extends Factory
{
    protected $model = NotificationOutbox::class;

    public function definition(): array
    {
        $channel = $this->faker->randomElement(Channel::cases());
        $eventId = (string) Str::ulid();

        return [
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
            'category' => $this->faker->randomElement(['scheduling', 'discipline', 'billing', 'system']),
            'channel' => $channel,
            'locale' => $this->faker->randomElement(['ar', 'en']),
            'event_name' => 'notifications.queued',
            'event_id' => $eventId,
            'correlation_id' => (string) Str::ulid(),
            'subject' => [
                'ar' => 'إشعار تجريبي',
                'en' => 'Sample notification',
            ],
            'body' => [
                'ar' => 'هذا نص إشعار تجريبي بالعربية.',
                'en' => 'This is a sample notification body in English.',
            ],
            'payload' => [],
            'idempotency_key' => implode(':', [$eventId, 'scheduling', $channel->value]),
            'scheduled_for' => CarbonImmutable::now('UTC')->addMinutes($this->faker->numberBetween(1, 60)),
            'status' => OutboxStatus::Queued,
            'attempts' => 0,
            'last_error' => null,
            'last_error_retryable' => null,
        ];
    }

    public function withStatus(OutboxStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }

    public function withChannel(Channel $channel): static
    {
        return $this->state(function (array $attributes) use ($channel): array {
            $category = is_string($attributes['category']) ? $attributes['category'] : 'scheduling';

            return [
                'channel' => $channel,
                'idempotency_key' => implode(':', [$attributes['event_id'], $category, $channel->value]),
            ];
        });
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'status' => OutboxStatus::Sent,
            'attempts' => 1,
            'sent_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => OutboxStatus::Failed,
            'attempts' => max(1, (int) config('notifications.delivery.max_retries') + 1),
            'last_error' => 'provider_unreachable',
            'last_error_retryable' => true,
        ]);
    }
}
