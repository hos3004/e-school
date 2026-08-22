<?php

declare(strict_types=1);

namespace Modules\Notifications\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notifications\Domain\Enums\Channel;
use Modules\Notifications\Domain\Models\NotificationPreference;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<NotificationPreference>
 */
final class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'user_id' => Fixtures::userId(),
            'category' => $this->faker->randomElement(['scheduling', 'discipline', 'billing', 'system']),
            'channel' => $this->faker->randomElement(Channel::cases()),
            'enabled' => $this->faker->boolean(80),
            'updated_at' => CarbonImmutable::now('UTC'),
        ];
    }

    /**
     * تفضيل فريد لمستخدم بعينه — يمنع تصادم قيد (user, category, channel).
     */
    public function forUserCategory(string $userId, string $organizationId, string $category, Channel $channel): static
    {
        return $this->state(fn (): array => [
            'user_id' => $userId,
            'organization_id' => $organizationId,
            'category' => $category,
            'channel' => $channel,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => ['enabled' => false]);
    }
}
