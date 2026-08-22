<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Policies;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Organization\Domain\Models\AcademicCalendar;

/**
 * سياسة التقويمات الأكاديمية — الصلاحيات عبر can() فقط.
 */
final class AcademicCalendarPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return $user->can('academic_calendars.view_any');
    }

    public function view(Authenticatable $user, AcademicCalendar $calendar): bool
    {
        return $calendar->is_active
            || $user->can('academic_calendars.view');
    }

    public function create(Authenticatable $user): bool
    {
        return $user->can('academic_calendars.create');
    }

    public function update(Authenticatable $user, AcademicCalendar $calendar): bool
    {
        return $user->can('academic_calendars.update');
    }

    public function delete(Authenticatable $user, AcademicCalendar $calendar): bool
    {
        return ! $calendar->is_active && $user->can('academic_calendars.delete');
    }

    /** تنشيط تقويم ليكون مرجع الجدولة. */
    public function activate(Authenticatable $user, AcademicCalendar $calendar): bool
    {
        return ! $calendar->is_active && $user->can('academic_calendars.activate');
    }

    /** إغلاق تقويم نشط. */
    public function close(Authenticatable $user, AcademicCalendar $calendar): bool
    {
        return $calendar->is_active && $user->can('academic_calendars.close');
    }
}
