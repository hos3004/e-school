<?php

declare(strict_types=1);

/*
| Filament panel texts of the Attendance module — no hardcoded strings in the resource.
*/

return [
    'navigation_group' => 'Attendance & Discipline',

    'attendance' => [
        'label' => 'Attendance record',
        'plural' => 'Attendance records',
    ],

    'pages' => [
        'list_title' => 'Attendance records',
        'view_title' => 'Attendance record details',
    ],

    'actions' => [
        'confirm' => 'Confirm',
        'confirm_description' => 'This record will be sealed with its automatically derived status and becomes final for reports and payroll. Continue?',
        'override' => 'Override status',
        'reason_helper' => 'The reason is written to the audit log along with your name and the change time.',
    ],

    'messages' => [
        'confirmed' => 'Attendance confirmed and written to the audit trail.',
        'overridden' => 'Attendance status overridden with the documented reason.',
    ],

    'hub' => [
        'title' => 'Attendance hub',
        'attendance_summary' => 'Attendance record summary',
        'participant' => 'Student and session',
        'audit' => 'Audit trail',
        'empty' => 'No records yet.',
    ],
];
