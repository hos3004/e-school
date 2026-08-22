<?php

declare(strict_types=1);

return [
    'navigation_group' => 'Staff',
    'common' => [
        'active' => 'Active',
    ],
    'profile' => [
        'model_label' => 'Staff profile',
        'plural_label' => 'Staff profiles',
        'fields' => [
            'bio' => 'Profile summary',
            'employment_type' => 'Employment type',
            'hired_at' => 'Hire date',
            'specializations' => 'Specializations',
            'staff_code' => 'Staff code',
            'terminated_at' => 'Termination date',
        ],
        'filters' => [
            'active' => 'Currently employed',
        ],
    ],
];
