<?php

declare(strict_types=1);

/*
| Filament UI texts of the Academics module.
| Consumed via __('academics::filament.key').
*/

return [
    'group' => 'Learning',

    'fields' => [
        'created_at' => 'Created at',
        'reason' => 'Change reason',
        'reason_help' => 'This reason is stored in the audit trail; describe the decision clearly.',
    ],

    'sections' => ['audit' => 'Reason and audit'],
    'filters' => ['trashed' => 'Archived'],
    'hub' => ['empty' => 'No data has been recorded yet.', 'unrestricted' => 'Unrestricted'],

    'currencies' => [
        'EGP' => 'Egyptian Pound',
        'SAR' => 'Saudi Riyal',
        'AED' => 'UAE Dirham',
        'USD' => 'US Dollar',
    ],

    'program' => [
        'label' => 'program',
        'plural' => 'Programs',

        'sections' => [
            'identity' => 'Program identity',
            'delivery' => 'Scope, duration, and objectives',
            'pricing' => 'Default session and pricing',
            'eligibility' => 'Admission and matching rules',
            'eligibility_help' => 'An empty list means admission is not restricted by that field.',
        ],
        'hub' => [
            'title' => 'Program hub', 'overview' => 'Program overview', 'levels' => 'Levels',
            'courses' => 'Courses', 'eligibility' => 'Eligibility and admission', 'categories' => 'Categories',
        ],

        'fields' => [
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'description_ar' => 'Description (Arabic)',
            'description_en' => 'Description (English)',
            'duration_weeks' => 'Duration (weeks)',
            'default_session_minutes' => 'Default session minutes',
            'default_rate' => 'Default rate',
            'currency' => 'Currency',
            'is_active' => 'Active',
            'sort_order' => 'Display order',
            'program_type' => 'Program type',
            'start_date' => 'Start date',
            'end_date' => 'End date',
            'target_gender' => 'Target audience',
            'age_from' => 'Age from',
            'age_to' => 'Age to',
            'age_range' => 'Age range',
            'objectives' => 'Program objectives',
            'objective_key' => 'Objective code',
            'objective_value' => 'Objective description',
            'language' => 'Delivery language',
            'rate_minor_units_help' => 'Enter the amount in the currency minor unit (for example cents).',
            'countries' => 'Allowed countries',
            'regions' => 'Allowed regions',
            'teacher_gender_rule' => 'Teacher gender matching rule',
            'manual_approval_required' => 'Manual approval required',
            'requires_individual_sessions' => 'Requires individual sessions',
            'levels_count' => 'Levels',
            'courses_count' => 'Courses',
            'active_courses_count' => 'Active courses',
        ],

        'filters' => [
            'active' => 'Active programs only',
        ],
    ],

    'level' => [
        'label' => 'level',
        'plural' => 'Levels',

        'sections' => ['identity' => 'Level identity'],
        'hub' => ['title' => 'Level hub', 'overview' => 'Level overview', 'courses' => 'Courses'],

        'fields' => [
            'program' => 'Program',
            'code' => 'Code',
            'name' => 'Name',
            'name_ar' => 'Name (Arabic)',
            'name_en' => 'Name (English)',
            'sort_order' => 'Sort order',
            'courses_count' => 'Courses',
        ],
    ],

    'course' => [
        'label' => 'Course',
        'plural' => 'Courses',

        'hub' => [
            'title' => 'Course hub', 'overview' => 'Course overview', 'description' => 'Description',
            'rules' => 'Completion rules and prerequisites', 'categories' => 'Categories',
        ],

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
            'categories' => 'Categories',
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

    'category' => [
        'label' => 'Category',
        'fields' => [
            'code' => 'Code', 'name' => 'Name', 'name_ar' => 'Name (Arabic)', 'name_en' => 'Name (English)',
            'parent' => 'Parent category', 'scope' => 'Scope', 'sort_order' => 'Display order', 'is_active' => 'Active',
        ],
    ],

    'actions' => [
        'create_level' => 'Add level', 'level_created' => 'The level was created.',
        'create_category' => 'Add category', 'update_category' => 'Edit category', 'archive_category' => 'Archive category',
        'category_created' => 'The category was created.', 'category_updated' => 'The category was updated.',
        'category_archived' => 'The category was archived.', 'activate' => 'Activate', 'deactivate' => 'Deactivate',
        'status_updated' => 'The status was updated.', 'archive' => 'Archive',
    ],

    'program_types' => ['fixed_duration' => 'Fixed duration', 'ongoing' => 'Ongoing'],
    'teacher_gender_rules' => ['any' => 'Any qualified teacher', 'same' => 'Same gender', 'opposite' => 'Opposite gender'],

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
