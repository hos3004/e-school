<?php

declare(strict_types=1);

namespace Modules\Groups\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Groups\Database\Factories\Concerns\EnsuresReferencedRecords;
use Modules\Groups\Domain\Enums\MembershipStatus;
use Modules\Groups\Domain\Models\Group;
use Modules\Groups\Domain\Models\GroupMembership;

/**
 * @extends Factory<GroupMembership>
 */
final class GroupMembershipFactory extends Factory
{
    use EnsuresReferencedRecords;

    protected $model = GroupMembership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'student_profile_id' => self::ensureStudentProfile(),
            'joined_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 60)),
            'left_at' => null,
            'status' => MembershipStatus::Active,
        ];
    }

    /** انتساب مغادر — يسمح بإنشاء انتساب جديد لنفس الطالب. */
    public function left(): static
    {
        return $this->state(fn (): array => [
            'status' => MembershipStatus::Left,
            'left_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(1, 30)),
        ]);
    }
}
