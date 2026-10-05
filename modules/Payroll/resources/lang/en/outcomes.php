<?php

declare(strict_types=1);

/*
| Payroll entry outcome reasons — the keys of `payroll.outcomes` in config.
| Shown in the admin "outcome" column. The stored key itself never changes.
*/

return [
    'completed' => 'Session held',
    'makeup_completed' => 'Makeup session held',
    'student_no_show' => 'Student did not attend',
    'no_show' => 'Student did not attend',
    'student_excused' => 'Student excused absence',
    'cancelled_accepted' => 'Cancellation accepted',
    'cancelled_by_student' => 'Cancelled by student',
    'cancelled_late_by_student' => 'Late cancellation by student',
    'cancelled_by_school' => 'Cancelled by the school',
    'teacher_absent' => 'Teacher did not attend',
    'postponed' => 'Session postponed',
];
