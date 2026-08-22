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
            'country' => 'Country',
            'date_of_birth' => 'Date of birth',
            'employment_type' => 'Employment type',
            'gender' => 'Gender',
            'hired_at' => 'Hire date',
            'phone' => 'Phone number',
            'region' => 'Region',
            'specializations' => 'Specializations',
            'staff_code' => 'Staff code',
            'terminated_at' => 'Termination date',
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
];
