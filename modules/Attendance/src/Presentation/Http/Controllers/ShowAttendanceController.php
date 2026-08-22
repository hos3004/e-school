<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Http\Resources\AttendanceResource;

/**
 * عرض قيد حضور — GET /api/attendances/{attendance}.
 */
final class ShowAttendanceController extends Controller
{
    public function __invoke(Attendance $attendance): AttendanceResource
    {
        abort_unless(auth()->user()?->can('view', $attendance) ?? false, 403);

        return AttendanceResource::make($attendance);
    }
}
