<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Domain\Models\TeacherDashboard;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<TeacherDashboard>
 */
final class TeacherDashboardFactory extends Factory
{
    protected $model = TeacherDashboard::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'staff_profile_id' => Fixtures::staffProfileId(),
            'sessions_total' => $this->faker->numberBetween(10, 120),
            'sessions_completed' => $this->faker->numberBetween(8, 100),
            'cancellations_by_self' => $this->faker->numberBetween(0, 6),
            'postponements' => $this->faker->numberBetween(0, 8),
            // بالوحدات الصغرى — قروش. لا float في المال.
            'payout_minor' => $this->faker->numberBetween(50_000, 900_000),
            'currency' => 'EGP',
            'last_session_at' => CarbonImmutable::now('UTC')->subDays($this->faker->numberBetween(0, 7)),
        ];
    }
}
