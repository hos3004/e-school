<?php

declare(strict_types=1);

/*
| Known notification categories — display only; the category itself is a
| free string owned by the modules emitting the events.
*/

return [

    'scheduling' => 'Scheduling',
    'discipline' => 'Discipline',
    'billing' => 'Billing',
    'payroll' => 'Payroll',
    'system' => 'System',
    'registration_update' => 'Registration update',
    'teacher_workflow' => 'Teacher workflow',
    'assignment_update' => 'Assignment update',
    'session_changed' => 'Session change',
    'session_reminder' => 'Session reminder',
    'classroom_invitation' => 'Classroom invitation',
    'session_report' => 'Session report',
    'discipline_notice' => 'Discipline notice',
    'enrollment_frozen' => 'Enrollment frozen',
    'grade_published' => 'Grade published',
    'postponement_request' => 'Postponement request',
    'attendance_recorded' => 'Attendance recorded',
    'assignment_due' => 'Assignment due soon',
    'monthly_report' => 'Monthly report',
    'payroll_period' => 'Payroll period',
    'message_received' => 'Message received',
    'system_alert' => 'System alert',
];
