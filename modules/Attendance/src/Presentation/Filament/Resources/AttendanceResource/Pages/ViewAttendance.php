<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Filament\Resources\AttendanceResource\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;

final class ViewAttendance extends ViewRecord
{
    protected static string $resource = AttendanceFilamentResource::class;

    public function getTitle(): string
    {
        return __('attendance::filament.pages.view_title');
    }
}
