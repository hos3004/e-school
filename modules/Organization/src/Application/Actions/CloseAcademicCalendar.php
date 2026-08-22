<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Events\AcademicCalendarClosed;
use Modules\Organization\Domain\Models\AcademicCalendar;

/**
 * إغلاق تقويم أكاديمي — يخرج من حالة التنشيط.
 *
 * لا يحذف شيئًا: الحصص والتسجيلات المرتبطة تبقى كما هي؛ الجدولة
 * الجديدة فقط لن تجد تقويمًا نشطًا حتى يُنشَّط آخر.
 */
final readonly class CloseAcademicCalendar
{
    public function execute(AcademicCalendar $calendar): AcademicCalendar
    {
        if (! $calendar->is_active) {
            return $calendar;
        }

        DB::transaction(function () use ($calendar): void {
            $calendar->forceFill(['is_active' => false])->save();
        });

        Event::dispatch(new AcademicCalendarClosed(
            academicCalendarId: $calendar->id,
            organizationId: $calendar->organization_id,
        ));

        return $calendar;
    }
}
