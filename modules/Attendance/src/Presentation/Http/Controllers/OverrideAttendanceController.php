<?php

declare(strict_types=1);

namespace Modules\Attendance\Presentation\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Attendance\Application\Actions\OverrideAttendanceAction;
use Modules\Attendance\Domain\Enums\AttendanceStatus;
use Modules\Attendance\Domain\Models\Attendance;
use Modules\Attendance\Presentation\Http\Requests\OverrideAttendanceRequest;
use Modules\Attendance\Presentation\Http\Resources\AttendanceResource;

/**
 * تجاوز حالة الحضور بسبب موثّق — PATCH /api/attendances/{attendance}.
 */
final class OverrideAttendanceController extends Controller
{
    public function __construct(
        private readonly OverrideAttendanceAction $action,
    ) {}

    public function __invoke(OverrideAttendanceRequest $request, Attendance $attendance): AttendanceResource
    {
        /** @var string $statusValue */
        $statusValue = $request->validated('status');

        return AttendanceResource::make(
            $this->action->execute(
                $attendance,
                AttendanceStatus::from($statusValue),
                (string) $request->validated('reason'),
            ),
        );
    }
}
