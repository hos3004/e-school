<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Messaging\Domain\Models\WhatsappInbound;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<WhatsappInbound>
 */
final class WhatsappInboundFactory extends Factory
{
    protected $model = WhatsappInbound::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'from_phone' => '+2010'.str_pad((string) $this->faker->numberBetween(0, 99999999), 8, '0'),
            'message_id' => 'wa-'.((string) Str::ulid()),
            'body' => $this->faker->sentence(10),
            'media' => null,
            'received_at' => CarbonImmutable::now('UTC')->subMinutes($this->faker->numberBetween(1, 120)),
            'matched_user_id' => null,
            'handled_by' => null,
            'handled_at' => null,
            'created_at' => CarbonImmutable::now('UTC'),
        ];
    }

    public function handled(): static
    {
        return $this->state(fn (): array => [
            'handled_by' => Fixtures::userId(),
            'handled_at' => CarbonImmutable::now('UTC'),
        ]);
    }
}
