<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Organization\Domain\Enums\HolidaySource;
use Modules\Organization\Domain\Events\AcademicCalendarActivated;
use Modules\Organization\Domain\Models\AcademicCalendar;
use Modules\Organization\Domain\Models\Holiday;

/**
 * تنشيط تقويم أكاديمي — يصبح مرجع الجدولة للمؤسسة.
 *
 * قاعدة العمل: عدد التقاويم النشطة في نفس اللحظة محدود بـ
 * config('organization.rules.max_active_calendars')؛ لذلك التنشيط
 * يُغلق تلقائيًا أي تقويم نشط آخر للمؤسسة داخل نفس المعاملة.
 */
final readonly class ActivateAcademicCalendar
{
    public function execute(AcademicCalendar $calendar): AcademicCalendar
    {
        if ($calendar->is_active) {
            return $calendar;
        }

        DB::transaction(function () use ($calendar): void {
            AcademicCalendar::query()
                ->active()
                ->forOrganization($calendar->organization_id)
                ->update(['is_active' => false]);

            $calendar->forceFill(['is_active' => true])->save();

            // انسخ عطل المؤسسة العامة (غير المرتبطة بتقويم) إلى هذا التقويم
            // حتى ترثه الجدولة الجديدة الأيام المتوقفة.
            Holiday::query()
                ->forOrganization($calendar->organization_id)
                ->whereNull('academic_calendar_id')
                ->blockingScheduling()
                ->get()
                ->each(static function (Holiday $holiday) use ($calendar): void {
                    Holiday::query()->create([
                        'organization_id' => $calendar->organization_id,
                        'academic_calendar_id' => $calendar->id,
                        'name' => $holiday->name,
                        'starts_on' => $holiday->starts_on,
                        'ends_on' => $holiday->ends_on,
                        'source' => HolidaySource::Manual,
                        'blocks_scheduling' => true,
                    ]);
                });
        });

        Event::dispatch(new AcademicCalendarActivated(
            academicCalendarId: $calendar->id,
            organizationId: $calendar->organization_id,
        ));

        return $calendar->refresh();
    }
}
