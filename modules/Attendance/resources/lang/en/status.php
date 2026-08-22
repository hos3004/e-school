<?php

declare(strict_types=1);

/*
| Attendance statuses as in Modules\Attendance\Domain\Enums\AttendanceStatus.
| Consumed via AttendanceStatus::label() i.e. __('attendance::status.{value}').
*/

return [
    'present' => 'Present',
    'late' => 'Late',
    'partial' => 'Partial attendance',
    'left_early' => 'Left early',
    'excused' => 'Excused absence',
    'absent' => 'Absent',
    'no_show' => 'No-show (no notice)',
    'technical_issue' => 'Technical issue',
    'not_held' => 'Session not held',
];
