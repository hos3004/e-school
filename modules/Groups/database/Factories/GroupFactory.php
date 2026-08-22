<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Groups\Database\Factories\Concerns\EnsuresReferencedRecords;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;

/**
 * @extends Factory<Group>
 */
final class GroupFactory extends Factory
{
    use EnsuresReferencedRecords;

    protected $model = Group::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => self::ensureOrganization(),
            'code' => strtoupper($this->faker->unique()->bothify('GRP-####-####')),
            'name' => [
                'ar' => 'مجموعة '.$this->faker->numberBetween(1, 99),
                'en' => 'Group '.$this->faker->numberBetween(1, 99),
            ],
            'capacity' => $this->faker->numberBetween(5, 25),
            'timezone' => 'UTC',
            'status' => GroupStatus::Planning,
            'starts_on' => $this->faker->dateTimeBetween('-6 months', '+1 month')->format('Y-m-d'),
            'ends_on' => null,
        ];
    }

    /** مجموعة نشطة. */
    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupStatus::Active,
        ]);
    }

    /** مجموعة مُختمة. */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupStatus::Completed,
            'ends_on' => now()->subDay()->toDateString(),
        ]);
    }
}
