<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Reporting\Domain\Models\StudentDashboard;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<StudentDashboard>
 */
final class StudentDashboardFactory extends Factory
{
    protected $model = StudentDashboard::class;

    public function definition(): array
    {
        $attended = $this->faker->numberBetween(4, 40);
        $missed = $this->faker->numberBetween(0, 8);
        $denominator = $attended + $missed;

        return [
            'organization_id' => Fixtures::organizationId(),
            'enrollment_id' => (string) Str::ulid(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'sessions_total' => $attended + $missed,
            'sessions_attended' => $attended,
            'sessions_missed' => $missed,
            'attendance_rate_bp' => (int) round(($attended * 10000) / max(1, $denominator)),
            'violations_count' => $this->faker->numberBetween(0, 3),
            'freezes_count' => $this->faker->numberBetween(0, 2),
            'last_session_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(0, 10)),
            'last_violation_at' => null,
        ];
    }

    public function withViolation(): static
    {
        return $this->state(fn (): array => [
            'violations_count' => $this->faker->numberBetween(1, 3),
            'last_violation_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(0, 5)),
        ]);
    }
}
