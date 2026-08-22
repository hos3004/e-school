<?php

declare(strict_types=1);

namespace Modules\Academics\Database\Factories;

use Shared\Testing\Fixtures;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Academics\Domain\Models\Course;
use Modules\Academics\Domain\Models\Level;

/**
 * @extends Factory<Course>
 */
final class CourseFactory extends Factory
{
    protected $model = Course::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'level_id' => Level::factory(),
            'code' => strtoupper($this->faker->unique()->bothify('CRS-####')),
            'name' => [
                'ar' => 'كورس '.$this->faker->word(),
                'en' => $this->faker->words(2, true),
            ],
            'description' => [
                'ar' => $this->faker->sentence(),
                'en' => $this->faker->sentence(),
            ],
            'total_sessions' => $this->faker->numberBetween(8, 32),
            'completion_rules' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
