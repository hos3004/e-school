<?php

declare(strict_types=1);

namespace Modules\Organization\Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Organization\Domain\Enums\HolidaySource;
use Modules\Organization\Domain\Models\Holiday;

/**
 * @extends Factory<Holiday>
 */
final class HolidayFactory extends Factory
{
    protected $model = Holiday::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = CarbonImmutable::parse($this->faker->dateTimeBetween('+1 month', '+6 months'), 'UTC')->startOfDay();
        $endsOn = $startsOn->addDays($this->faker->numberBetween(0, 7));

        return [
            'organization_id' => OrganizationFactory::new()->create()->id,
            'academic_calendar_id' => null,
            'name' => ['ar' => 'عطلة '.$this->faker->word(), 'en' => 'Holiday'],
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'source' => HolidaySource::Manual,
            'blocks_scheduling' => true,
        ];
    }

    public function forCalendar(string $calendarId): static
    {
        return $this->state(fn (): array => ['academic_calendar_id' => $calendarId]);
    }
}
