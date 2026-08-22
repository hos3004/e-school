<?php

declare(strict_types=1);

/*
| حالات الحضور كما في Modules\Attendance\Domain\Enums\AttendanceStatus.
| تُستهلك عبر AttendanceStatus::label() أي __('attendance::status.{value}').
*/

return [
    'present' => 'حاضر',
    'late' => 'حضر متأخرًا',
    'partial' => 'حضور جزئي',
    'left_early' => 'انصرف مبكرًا',
    'excused' => 'غائب بعذر مقبول',
    'absent' => 'غائب',
    'no_show' => 'لم يحضر ولم يُخطر',
    'technical_issue' => 'تعذّر الحضور لعطل تقني',
    'not_held' => 'الحصة لم تُعقد',
];
