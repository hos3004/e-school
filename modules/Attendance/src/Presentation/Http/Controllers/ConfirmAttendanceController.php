<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Attendance\Application\Actions\ConfirmAttendanceAction;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Http\Requests\ConfirmAttendanceRequest;
use Modules\Attendance\Presentation\Http\Resources\AttendanceResource;

/**
 * اعتماد حالة الحضور — POST /api/attendances/{attendance}/confirm.
 */
final class ConfirmAttendanceController extends Controller
{
    public function __construct(
        private readonly ConfirmAttendanceAction $action,
    ) {}

    public function __invoke(ConfirmAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        return AttendanceResource::make(
            $this->action->execute($attendance, (string) $request->user()?->getAuthIdentifier()),
        );
    }
}
