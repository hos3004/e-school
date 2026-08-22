<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Groups\Database\Factories\Concerns\EnsuresReferencedRecords;
use Modules\Groups\Domain\Enums\GroupTeacherRole;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupTeacher;

/**
 * @extends Factory<GroupTeacher>
 */
final class GroupTeacherFactory extends Factory
{
    use EnsuresReferencedRecords;

    protected $model = GroupTeacher::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'staff_profile_id' => self::ensureStaffProfile(),
            'course_id' => null,
            'role' => GroupTeacherRole::Lead,
            'assigned_from' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 60))->toDateString(),
            'assigned_to' => null,
        ];
    }

    /** إسناد مغلق بتاريخ نهاية. */
    public function closed(): static
    {
        return $this->state(fn (): array => [
            'assigned_to' => CarbonImmutable::now('UTC')->subDay()->toDateString(),
        ]);
    }

    /** معلم مساعد. */
    public function assistant(): static
    {
        return $this->state(fn (): array => [
            'role' => GroupTeacherRole::Assistant,
        ]);
    }
}
