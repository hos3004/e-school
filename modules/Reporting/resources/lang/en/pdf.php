<?php

declare(strict_types=1);

return [
    'document' => [
        'organization_fallback' => 'E-School',
        'generated_at' => 'Generated at',
        'period' => 'Period',
        'timezone' => 'Time zone',
        'filters' => 'Applied filters',
        'summary' => 'Report summary',
        'sessions' => 'Session details',
        'no_results' => 'No sessions match the selected period and filters.',
        'page' => 'Page',
    ],
    'columns' => [
        'session' => 'Session',
        'schedule' => 'Schedule',
        'group' => 'Group',
        'teacher' => 'Teacher',
        'students' => 'Students',
        'attendance' => 'Attendance',
        'status' => 'Status',
        'cancellation_reason' => 'Cancellation reason',
    ],
    'labels' => [
        'course' => 'Course',
        'type' => 'Type',
        'duration' => 'Duration',
        'minutes' => ':count min',
        'original_teacher' => 'Original teacher',
        'report_status' => 'Report status',
        'not_available' => '—',
    ],
    'errors' => [
        'invalid_configuration' => 'The report could not be exported because the PDF service is misconfigured.',
        'temporary_directory_unavailable' => 'The temporary workspace for report export is unavailable.',
        'rendering_failed' => 'The PDF could not be created. Try again or contact support.',
        'output_invalid' => 'The export service produced an invalid file.',
    ],
];
