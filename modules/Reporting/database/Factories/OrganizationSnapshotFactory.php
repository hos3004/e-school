<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Reporting\Domain\Enums\SnapshotType;
use Modules\Reporting\Domain\Models\OrganizationSnapshot;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<OrganizationSnapshot>
 */
final class OrganizationSnapshotFactory extends Factory
{
    protected $model = OrganizationSnapshot::class;

    public function definition(): array
    {
        $sessionsHeld = $this->faker->numberBetween(10, 120);
        $sessionsCancelled = $this->faker->numberBetween(0, 15);

        return [
            'organization_id' => Fixtures::organizationId(),
            'snapshot_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'period_type' => SnapshotType::Daily,
            'students_active' => $this->faker->numberBetween(20, 400),
            'students_frozen' => $this->faker->numberBetween(0, 20),
            'teachers_active' => $this->faker->numberBetween(5, 60),
            'sessions_held' => $sessionsHeld,
            'sessions_cancelled' => $sessionsCancelled,
            'attendance_rate_bp' => $this->faker->numberBetween(5000, 9900),
        ];
    }
}
