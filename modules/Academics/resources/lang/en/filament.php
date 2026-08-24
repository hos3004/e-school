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
        'label' => 'Course',
        'plural' => 'Courses',

        'sections' => [
            'identity' => 'Course identity',
            'delivery' => 'Classification and delivery',
            'rules' => 'Completion rules and prerequisites',
        ],

        'fields' => [
            'level' => 'Level',
            'program' => 'Program',
            'organization' => 'Organization',
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'description_ar' => 'Description (Arabic)',
            'description_en' => 'Description (English)',
            'total_sessions' => 'Total sessions',
            'completion_rules' => 'Completion rules',
            'prerequisites' => 'Prerequisites',
            'rule_key' => 'Rule',
            'rule_value' => 'Value',
            'is_active' => 'Active',
            'session_mode' => 'Session mode',
            'target_gender' => 'Target audience',
            'inherits_program' => 'Inherits from program',
            'age_from' => 'Age from',
            'age_to' => 'Age to',
            'age_range' => 'Age range',
            'any_age' => 'Any age',
            'age_from_only' => ':age and above',
            'age_to_only' => 'Up to :age',
            'default_duration_minutes' => 'Session duration (minutes)',
            'duration_help' => 'Used as the default when scheduling sessions for this course.',
            'sessions_per_week' => 'Sessions per week',
        ],

        'filters' => [
            'active' => 'Active courses only',
            'program' => 'Program',
            'trashed' => 'Archived',
        ],

        'errors' => [
            'no_organization' => 'Your account is not linked to an organization, so a course cannot be created.',
            'level_outside_organization' => 'The selected level does not belong to your organization.',
        ],
    ],

    'session_modes' => [
        'individual' => 'Individual',
        'group' => 'Group',
        'both' => 'Individual and group',
    ],

    'target_genders' => [
        'male' => 'Male',
        'female' => 'Female',
        'all' => 'Everyone',
    ],
];
