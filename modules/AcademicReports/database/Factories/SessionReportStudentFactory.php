<?php

declare(strict_types=1);

namespace Modules\AcademicReports\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\AcademicReports\Domain\Models\SessionReport;
use Modules\AcademicReports\Domain\Models\SessionReportStudent;
use Shared\Testing\Fixtures;

/**
 * @extends Factory<SessionReportStudent>
 */
final class SessionReportStudentFactory extends Factory
{
    protected $model = SessionReportStudent::class;

    public function definition(): array
    {
        return [
            'session_report_id' => SessionReport::factory(),
            'student_profile_id' => Fixtures::studentProfileId(),
            'participation' => $this->faker->numberBetween(1, 5),
            'performance' => $this->faker->numberBetween(1, 5),
            'commitment' => $this->faker->numberBetween(1, 5),
            'strengths' => $this->faker->optional()->sentence(),
            'weaknesses' => $this->faker->optional()->sentence(),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
