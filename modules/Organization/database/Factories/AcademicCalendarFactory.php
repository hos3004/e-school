<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Domain\Models\AcademicCalendar;

/**
 * @extends Factory<AcademicCalendar>
 */
final class AcademicCalendarFactory extends Factory
{
    protected $model = AcademicCalendar::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = (int) now('UTC')->format('Y');
        $startsOn = CarbonImmutable::create($year, 9, 1, 0, 0, 0, 'UTC');
        $endsOn = $startsOn->addMonths(9)->subDay();

        return [
            'organization_id' => OrganizationFactory::new()->create()->id,
            'name' => ['ar' => 'العام الدراسي '.$year, 'en' => 'School Year '.$year],
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => ['is_active' => true]);
    }
}
