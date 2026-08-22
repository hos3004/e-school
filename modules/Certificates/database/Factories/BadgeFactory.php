<?php

declare(strict_types=1);

namespace Modules\Certificates\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Certificates\Domain\Enums\BadgeTier;
use Modules\Certificates\Domain\Models\Badge;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<Badge>
 */
final class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    public function definition(): array
    {
        $word = $this->faker->unique()->word();

        return [
            'organization_id' => Fixtures::organizationId(),
            'code' => 'badge_'.str($word)->slug('_'),
            'name' => [
                'ar' => 'شارة '.$word,
                'en' => 'Badge '.$word,
            ],
            'description' => [
                'ar' => 'وصف تجريبي للشارة: '.$this->faker->sentence(),
                'en' => 'Sample badge description: '.$this->faker->sentence(),
            ],
            'icon_path' => null,
            'tier' => $this->faker->randomElement(BadgeTier::cases()),
            'is_active' => true,
        ];
    }

    public function tier(BadgeTier $tier): static
    {
        return $this->state(fn (): array => ['tier' => $tier]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
