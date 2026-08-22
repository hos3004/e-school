<?php

declare(strict_types=1);

namespace Modules\Integrations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integrations\Domain\Enums\DeliveryStatus;
use Modules\Integrations\Domain\Enums\WebhookDirection;
use Modules\Integrations\Domain\Models\IntegrationConnection;
use Modules\Integrations\Domain\Models\IntegrationWebhookDelivery;

/**
 * @extends Factory<IntegrationWebhookDelivery>
 */
final class IntegrationWebhookDeliveryFactory extends Factory
{
    protected $model = IntegrationWebhookDelivery::class;

    public function definition(): array
    {
        return [
            'connection_id' => IntegrationConnection::factory(),
            'direction' => WebhookDirection::Outbound,
            'event_type' => $this->faker->randomElement([
                'attendance.recorded',
                'payment.captured',
                'session.completed',
                'student.enrolled',
            ]),
            'status' => DeliveryStatus::Pending,
            'attempts' => 0,
            'payload' => ['sample' => true],
        ];
    }

    public function withStatus(DeliveryStatus $status): static
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
