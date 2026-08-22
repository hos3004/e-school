<?php

declare(strict_types=1);

namespace Modules\Reporting\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Reporting\Domain\Models\ReportEventLog;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<ReportEventLog>
 */
final class ReportEventLogFactory extends Factory
{
    protected $model = ReportEventLog::class;

    public function definition(): array
    {
        return [
            'organization_id' => Fixtures::organizationId(),
            'event_id' => (string) Str::ulid(),
            'name' => $this->faker->randomElement([
                'sessions.completed',
                'attendance.confirmed',
                'discipline.violation_recorded',
                'payroll.entry_recorded',
            ]),
            'module' => $this->faker->randomElement(['sessions', 'attendance', 'discipline', 'payroll']),
            'actor_id' => null,
            'correlation_id' => (string) Str::ulid(),
            'occurred_at' => CarbonImmutable::now('UTC')->subMinutes($this->faker->numberBetween(1, 1440)),
            'payload' => [
                'organization_id' => Fixtures::organizationId(),
            ],
        ];
    }
}
