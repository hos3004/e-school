<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academics\Domain\Models\Level;
use Modules\Academics\Domain\Models\Program;

/**
 * @extends Factory<Level>
 */
final class LevelFactory extends Factory
{
    protected $model = Level::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('LVL-##')),
            'name' => [
                'ar' => 'مستوى '.$this->faker->numberBetween(1, 6),
                'en' => 'Level '.$this->faker->numberBetween(1, 6),
            ],
            'sort_order' => 0,
        ];
    }
}
