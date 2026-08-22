<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Events\AcademicCalendarCreated;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Organization;
use Shared\Support\BusinessRuleViolation;

/**
 * إنشاء تقويم أكاديمي لمؤسسة.
 *
 * قواعد العمل:
 *  - البداية قبل النهاية (تُفحص أيضًا في الـ FormRequest، لكن الحارس هنا يضمن العقد).
 *  - لا يتقاطع التقويم الجديد مع تقويم نشط قائم.
 */
final readonly class CreateAcademicCalendar
{
    /**
     * @param array<string, string> $name
     */
    public function execute(Organization $organization, array $name, string $startsOn, string $endsOn, bool $isActive = false): AcademicCalendar
    {
        $start = CarbonImmutable::parse($startsOn, 'UTC')->startOfDay();
        $end = CarbonImmutable::parse($endsOn, 'UTC')->startOfDay();

        if ($start->gte($end)) {
            throw BusinessRuleViolation::make(
                'organization.calendar_range_invalid',
                'organization::errors.calendar_range_invalid',
            );
        }

        $overlapsActive = AcademicCalendar::query()
            ->active()
            ->forOrganization($organization->id)
            ->whereDate('starts_on', '<=', $end->toDateString())
            ->whereDate('ends_on', '>=', $start->toDateString())
            ->exists();

        if ($overlapsActive) {
            throw BusinessRuleViolation::make(
                'organization.calendar_overlaps_active',
                'organization::errors.calendar_overlaps_active',
                ['range' => $start->toDateString().' → '.$end->toDateString()],
            );
        }

        /** @var AcademicCalendar $calendar */
        $calendar = DB::transaction(function () use ($organization, $name, $start, $end, $isActive): AcademicCalendar {
            return AcademicCalendar::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'starts_on' => $start,
                'ends_on' => $end,
                'is_active' => $isActive,
            ]);
        });

        Event::dispatch(new AcademicCalendarCreated(
            academicCalendarId: $calendar->id,
            organizationId: $organization->id,
            startsOn: $start->toDateString(),
            endsOn: $end->toDateString(),
        ));

        return $calendar;
    }
}
