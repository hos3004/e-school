<?php

declare(strict_types=1);

/*
| Reporting module fields.
| Consumed via __('reporting::fields.key') in models, resources and requests.
*/

return [

    'id' => 'ID',
    'organization' => 'Organization',
    'enrollment' => 'Enrollment',
    'student' => 'Student',
    'staff' => 'Teacher',

    'sessions_total' => 'Total sessions',
    'sessions_attended' => 'Sessions attended',
    'sessions_missed' => 'Sessions missed',
    'sessions_completed' => 'Sessions completed',
    'attendance_rate' => 'Attendance rate',
    'violations_count' => 'Violations count',
    'freezes_count' => 'Freezes count',

    'cancellations_by_self' => 'Teacher cancellations',
    'postponements' => 'Postponements',
    'payout' => 'Net payout',
    'last_session_at' => 'Last session',
    'last_violation_at' => 'Last violation',

    'snapshot_date' => 'Snapshot date',
    'period_type' => 'Period type',
    'students_active' => 'Active students',
    'students_frozen' => 'Frozen students',
    'teachers_active' => 'Active teachers',
    'sessions_held' => 'Sessions held',
    'sessions_cancelled' => 'Sessions cancelled',

    'event_id' => 'Event ID',
    'event_name' => 'Event name',
    'event_module' => 'Source module',
    'occurred_at' => 'Occurred at',
    'ingested_at' => 'Ingested at',

    'column' => 'Corrected column',
    'value' => 'New value',
    'reason' => 'Correction reason',

    'capture_snapshot' => 'Capture snapshot now',
    'snapshot_captured' => 'Organization snapshot captured.',
];
