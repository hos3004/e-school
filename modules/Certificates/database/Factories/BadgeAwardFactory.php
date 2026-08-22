<?php

declare(strict_types=1);

namespace Modules\Certificates\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Certificates\Domain\Models\Badge;
use Modules\Certificates\Domain\Models\BadgeAward;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<BadgeAward>
 */
final class BadgeAwardFactory extends Factory
{
    protected $model = BadgeAward::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'badge_id' => Badge::factory(),
            'user_id' => Fixtures::userId(),
            'awarded_by' => null,
            'reason' => $this->faker->optional()->sentence(),
            'awarded_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 60)),
        ];
    }

    public function forBadge(Badge $badge): static
    {
        return $this->state(fn (): array => [
            'badge_id' => (string) $badge->getKey(),
            'organization_id' => $badge->organization_id,
        ]);
    }

    public function awardedBy(string $userId): static
    {
        return $this->state(fn (): array => ['awarded_by' => $userId]);
    }
}
