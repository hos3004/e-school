<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Enums\HolidaySource;
use Modules\Organization\Domain\Events\HolidayAdded;
use Modules\Organization\Domain\Models\Holiday;
use Shared\Support\BusinessRuleViolation;

/**
 * إضافة عطلة لمؤسسة (وربما لتقويم أكاديمي محدد).
 *
 * قواعد العمل — كلها بأرقام من config لا من الكود:
 *  - مدة العطلة لا تتجاوز config('organization.rules.max_holiday_days').
 *  - لا تتقاطع مع عطلة قائمة لنفس المؤسسة على نفس النطاق.
 */
final readonly class AddHoliday
{
    public function execute(
        string $organizationId,
        array $name,
        string $startsOn,
        string $endsOn,
        ?string $academicCalendarId = null,
        bool $blocksScheduling = true,
    ): Holiday {
        $start = CarbonImmutable::parse($startsOn, 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($endsOn, 'UTC')->startOfDay();

        if ($start->gt($end)) {
            throw BusinessRuleViolation::make(
                'organization.holiday_range_invalid',
                'organization::errors.holiday_range_invalid',
            );
        }

        $maxDays = (int) config('organization.rules.max_holiday_days');

        if ($start->diffInDays($end) + 1 > $maxDays) {
            throw BusinessRuleViolation::make(
                'organization.holiday_too_long',
                'organization::errors.holiday_too_long',
                ['max_days' => $maxDays],
            );
        }

        $overlaps = Holiday::query()
            ->forOrganization($organizationId)
            ->when($academicCalendarId !== null, static function ($query) use ($academicCalendarId): void {
                $query->where(static function ($q) use ($academicCalendarId): void {
                    $q->where('academic_calendar_id', $academicCalendarId)
                        ->orWhereNull('academic_calendar_id');
                });
            })
            ->overlapping($start, $end)
            ->exists();

        if ($overlaps) {
            throw BusinessRuleViolation::make(
                'organization.holiday_overlaps',
                'organization::errors.holiday_overlaps',
                ['range' => $start->toDateString().' → '.$end->toDateString()],
            );
        }

        /** @var Holiday $holiday */
        $holiday = DB::transaction(function () use ($organizationId, $name, $start, $end, $academicCalendarId, $blocksScheduling): Holiday {
            return Holiday::query()->create([
                'organization_id' => $organizationId,
                'academic_calendar_id' => $academicCalendarId,
                'name' => $name,
                'starts_on' => $start,
                'ends_on' => $end,
                'source' => HolidaySource::Manual,
                'blocks_scheduling' => $blocksScheduling,
            ]);
        });

        Event::dispatch(new HolidayAdded(
            holidayId: $holiday->id,
            organizationId: $organizationId,
            academicCalendarId: $academicCalendarId,
            startsOn: $start->toDateString(),
            endsOn: $end->toDateString(),
            blocksScheduling: $blocksScheduling,
        ));

        return $holiday;
    }
}
