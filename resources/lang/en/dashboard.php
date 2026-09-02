<?php

declare(strict_types=1);

return [
    // The five sidebar sections. These values are the single reference for group
    // names, and every module lang file repeats the same literal string. The match
    // is guarded by tests/Feature/AdminNavigationStructureTest.php.
    'navigation' => [
        'daily' => 'Daily Operations',
        'people' => 'People',
        'learning' => 'Learning',
        'communication' => 'Communication',
        'insights' => 'Reports & Administration',
    ],

    'common' => [
        'dash' => '—',
    ],

    'stats' => [
        'students' => [
            'label' => 'Students',
            'description' => ':active active · :frozen frozen',
        ],
        'teachers' => [
            'label' => 'Teachers & staff',
            'description' => 'All teachers and supporting staff',
        ],
        'programs' => [
            'label' => 'Programs',
            'description' => 'Active programs and curricula',
        ],
        'sessions_today' => [
            'label' => "Today's sessions",
            'description' => ':done completed · :upcoming upcoming',
        ],
        'attendance_rate' => [
            'label' => 'Attendance rate this month',
            'description' => ':absent absences out of :total records',
            'empty_description' => 'No records yet',
        ],
        'payroll' => [
            'label' => 'Month payroll',
            'currency' => 'EGP',
            'deferred_description' => ':count deferred entries',
            'no_deferred_description' => 'No deferred entries',
        ],
    ],

    'needs_attention' => [
        'title' => 'Needs your attention',
        'subtitle' => 'Items waiting for an action from you now',
        'empty' => 'Nothing pending — every operational item is closed.',
        'items' => [
            'postponements_pending' => 'Postponement requests awaiting a response',
            'postponements_expired' => 'Expired postponement requests',
            'sessions_awaiting_review' => 'Sessions awaiting attendance approval',
            'registrations_submitted' => 'Registrations awaiting review',
            'enrollments_frozen' => 'Frozen enrollments',
            'reactivations_pending' => 'Pending reactivation requests',
            'availability_unapproved' => 'Unapproved teacher availability',
            'payroll_adjustments_pending' => 'Financial adjustments awaiting approval',
            'notifications_failed' => 'Failed notifications',
        ],
    ],

    'sessions_trend' => [
        'heading' => 'Sessions over the last four weeks',
        'dataset_held' => 'Held',
        'dataset_missed' => 'Not held',
    ],

    'upcoming_sessions' => [
        'title' => 'Upcoming sessions',
        'subtitle' => 'The next ten scheduled sessions that have not started yet.',
        'empty' => 'There are no scheduled sessions ahead.',
        'columns' => [
            'start_at' => 'Start time',
            'group' => 'Group',
            'teacher' => 'Teacher',
            'actions' => 'View',
        ],
        'view_session' => 'Open the session in the sessions panel',
    ],

    'quick_actions' => [
        'title' => 'Quick actions',
        'new_student' => 'New student',
        'new_program' => 'New program',
        'new_group' => 'New group',
        'sessions' => 'Sessions',
    ],
];
