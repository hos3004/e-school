<?php

declare(strict_types=1);

return [
    'navigation_group' => 'People',
    'common' => [
        'active' => 'Active',
    ],
    'profile' => [
        'model_label' => 'Staff profile',
        'plural_label' => 'Staff profiles',
        'fields' => [
            'bio' => 'Profile summary',
            'country' => 'Country',
            'date_of_birth' => 'Date of birth',
            'employment_type' => 'Employment type',
            'gender' => 'Gender',
            'hired_at' => 'Hire date',
            'name' => 'Name',
            'phone' => 'Phone number',
            'region' => 'Region',
            'specializations' => 'Specializations',
            'staff_code' => 'Staff code',
            'terminated_at' => 'Termination date',
            'reason' => 'Change reason',
            'reason_help' => 'A clear administrative reason recorded in the audit log; it is not stored on the profile.',
        ],
        'resources' => [
            'actions' => [
                'edit' => 'Edit profile',
            ],
        ],
        'filters' => [
            'active' => 'Currently employed',
            'country' => 'Country',
            'region' => 'Region',
        ],
        'gender_options' => [
            'female' => 'Female',
            'male' => 'Male',
        ],
    ],
    'teachers' => [
        'label' => 'Teachers',
        'title' => 'Teacher operations directory',
        'description' => 'A specialized view over teachers — metrics aggregated from real systems; operations live in the teacher operations hub.',
        'open_hub' => 'Open teacher hub',
        'edit' => 'Edit profile',
        'fields' => [
            'avatar' => 'Photo',
            'name' => 'Name',
            'account_status' => 'Account status',
            'qualified_courses' => 'Qualified courses',
            'active_groups' => 'Active groups',
            'upcoming_sessions' => 'Upcoming sessions',
            'completed_this_month' => 'Completed this month',
            'cancelled_this_month' => 'Cancelled this month',
            'availability' => 'Availability',
        ],
        'filters' => [
            'qualified_course' => 'Qualified course',
            'group' => 'Group',
        ],
    ],
];
