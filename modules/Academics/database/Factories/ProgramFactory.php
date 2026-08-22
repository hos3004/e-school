<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Factories;

use Shared\Testing\Fixtures;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academics\Domain\Models\Program;

/**
 * @extends Factory<Program>
 */
final class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'code' => strtoupper($this->faker->unique()->bothify('PRG-###')),
            'name' => [
                'ar' => 'برنامج '.$this->faker->word(),
                'en' => $this->faker->words(2, true),
            ],
            'description' => [
                'ar' => $this->faker->sentence(),
                'en' => $this->faker->sentence(),
            ],
            'duration_weeks' => $this->faker->numberBetween(8, 48),
            'default_session_minutes' => 60,
            'default_rate' => $this->faker->numberBetween(5000, 30000),
            'currency' => 'EGP',
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
