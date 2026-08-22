<?php

declare(strict_types=1);

/*
| Filament UI texts of the Academics module.
| Consumed via __('academics::filament.key').
*/

return [
    'group' => 'Academics',

    'fields' => [
        'created_at' => 'Created at',
    ],

    'currencies' => [
        'EGP' => 'Egyptian Pound',
        'SAR' => 'Saudi Riyal',
        'AED' => 'UAE Dirham',
        'USD' => 'US Dollar',
    ],

    'program' => [
        'label' => 'program',
        'plural' => 'Programs',

        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'duration_weeks' => 'Duration (weeks)',
            'default_session_minutes' => 'Default session minutes',
            'default_rate' => 'Default rate',
            'currency' => 'Currency',
            'is_active' => 'Active',
        ],

        'filters' => [
            'active' => 'Active programs only',
        ],
    ],

    'level' => [
        'label' => 'level',
        'plural' => 'Levels',

        'fields' => [
            'program' => 'Program',
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'sort_order' => 'Sort order',
        ],
    ],

    'course' => [
        'label' => 'course',
        'plural' => 'Courses',

        'fields' => [
            'level' => 'Level',
            'organization' => 'Organization',
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'total_sessions' => 'Total sessions',
            'completion_rules' => 'Completion rules',
            'is_active' => 'Active',
        ],

        'filters' => [
            'active' => 'Active courses only',
        ],
    ],
];
