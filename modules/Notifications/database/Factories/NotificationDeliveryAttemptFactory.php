<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notifications\Domain\Models\NotificationDeliveryAttempt;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<NotificationDeliveryAttempt>
 */
final class NotificationDeliveryAttemptFactory extends Factory
{
    protected $model = NotificationDeliveryAttempt::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'outbox_id' => NotificationOutboxFactory::new()->create()->getKey(),
            'attempt_number' => $this->faker->numberBetween(1, 3),
            'attempted_at' => CarbonImmutable::now('UTC'),
            'provider_response' => null,
            'succeeded' => true,
            'retryable' => null,
            'error' => null,
        ];
    }

    public function forOutbox(string $outboxId, string $organizationId): static
    {
        return $this->state(fn (): array => [
            'outbox_id' => $outboxId,
            'organization_id' => $organizationId,
        ]);
    }

    public function failedWith(string $error): static
    {
        return $this->state(fn (): array => [
            'succeeded' => false,
            'retryable' => true,
            'error' => $error,
        ]);
    }
}
