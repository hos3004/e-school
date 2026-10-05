<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Groups\Database\Factories\Concerns\EnsuresReferencedRecords;
use Modules\Groups\Domain\Enums\GroupStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;

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

    /**
     * مسودة بالحد الأدنى — بلا سعة ولا تاريخ بداية.
     *
     * هذه هي الصورة التي تُنشأ بها المجموعة من التسكين الجماعي: البيانات
     * المؤجَّلة تبقى فارغة حتى الاستكمال، ولا تُملأ بقيم وهمية.
     */
    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => GroupStatus::Planning,
            'capacity' => null,
            'starts_on' => null,
        ]);
    }

    /** مجموعة قيد التخطيط استوفت شرط المعلم فصارت قابلة للتفعيل. */
    public function activatable(): static
    {
        return $this->afterCreating(static function (Group $group): void {
            GroupTeacher::factory()->for($group)->create();
        });
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
