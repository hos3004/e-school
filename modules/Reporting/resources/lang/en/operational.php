<?php

declare(strict_types=1);

return [
    'navigation' => 'Reports centre',
    'title' => 'Operational session reports',
    'description' => 'Session, student, teacher, and group summaries and details for the selected period and filters.',
    'periods' => [
        'today' => 'Today', 'yesterday' => 'Yesterday', 'this_week' => 'This week',
        'previous_week' => 'Previous week', 'this_month' => 'This month', 'custom' => 'Custom range',
    ],
    'filters' => [
        'period' => 'Period', 'preset' => 'Quick period', 'from' => 'From', 'until' => 'Until',
        'status' => 'Session status', 'attendance_status' => 'Attendance status', 'session_type' => 'Session type',
        'student' => 'Student', 'teacher' => 'Actual teacher', 'original_teacher' => 'Original teacher',
        'group' => 'Group', 'course' => 'Course', 'report_status' => 'Teacher report status',
        'search' => 'Search',
    ],
    'columns' => [
        'session' => 'Session', 'scheduled_at' => 'Scheduled at', 'duration' => 'Duration', 'course' => 'Course',
        'group' => 'Group', 'teacher' => 'Teacher', 'students' => 'Students', 'attendance' => 'Attendance',
        'status' => 'Session status', 'session_type' => 'Type', 'report_status' => 'Teacher report',
        'cancellation_reason' => 'Cancellation/postponement reason',
    ],
    'summary' => [
        'total' => 'Total sessions', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'postponed' => 'Postponed',
        'no_show' => 'No-show', 'excused' => 'Excused', 'scheduled' => 'Upcoming/in progress',
        'students' => 'Students', 'teachers' => 'Teachers', 'groups' => 'Groups',
        'present' => 'Present', 'absent' => 'Absent', 'attendance_rate' => 'Attendance rate',
        'scheduled_minutes' => 'Scheduled minutes', 'actual_minutes' => 'Actual minutes',
        'reports_submitted' => 'Reports submitted', 'reports_late' => 'Late reports', 'reports_missing' => 'Missing reports',
    ],
    'report_status' => ['submitted' => 'Submitted', 'late' => 'Submitted late', 'missing' => 'Missing', 'not_required' => 'Not required yet'],
    'attendance' => ['unrecorded' => 'Not recorded', 'present_count' => ':count present', 'absent_count' => ':count absent'],
    'actions' => ['run_report' => 'Run report', 'export_pdf' => 'Export PDF', 'reset_filters' => 'Reset filters'],
    'initial_title' => 'Choose the period and filters, then run the report',
    'initial_description' => 'Session data is loaded only after you run the report, so you can adjust filters first.',
    'empty' => 'No sessions match the selected period and filters.',
    'limit_exceeded' => 'The result exceeds the allowed limit. Narrow the period or add filters before exporting.',
    'period_label' => ':from to :until',
    'substitute' => 'Substitute for :teacher',
    'selected_value' => 'Selected value',
    'not_available' => 'Not available',
    'separators' => ['list' => ', '],
    'minutes' => ':count min',
    'unknown_session' => 'Untitled session', 'unknown_student' => 'Unknown student', 'unknown_teacher' => 'Unknown teacher',
    'unknown_group' => 'Unknown group', 'unknown_course' => 'Unknown course', 'no_students' => 'No students',
];
