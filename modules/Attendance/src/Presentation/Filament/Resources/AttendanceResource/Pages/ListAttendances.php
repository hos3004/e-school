<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Filament\Resources\AttendanceResource\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Attendance\Presentation\Filament\Resources\AttendanceFilamentResource;

final class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceFilamentResource::class;

    public function getTitle(): string
    {
        return __('attendance::filament.pages.list_title');
    }
}
