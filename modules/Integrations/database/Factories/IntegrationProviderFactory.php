<?php

declare(strict_types=1);

namespace Modules\Integrations\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Integrations\Domain\Models\IntegrationProvider;

/**
 * @extends Factory<IntegrationProvider>
 */
final class IntegrationProviderFactory extends Factory
{
    protected $model = IntegrationProvider::class;

    public function definition(): array
    {
        $key = $this->faker->unique()->slug(2, false);

        return [
            'key' => $key,
            'name' => [
                'ar' => 'مزوّد '.$this->faker->word(),
                'en' => 'Provider '.$this->faker->word(),
            ],
            'category' => $this->faker->randomElement(['messaging', 'video', 'payment', 'storage']),
            'driver' => null,
            'is_active' => true,
            'default_settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
