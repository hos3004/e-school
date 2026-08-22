<?php

declare(strict_types=1);

/*
| Filament panel labels of the Guardians module.
*/

return [
    'navigation_group' => 'Families & Guardians',

    'common' => [
        'id' => 'ID',
        'created_at' => 'Created at',
        'archived_at' => 'Archived at',
        'not_archived' => 'Not archived',
    ],

    'profile' => [
        'model_label' => 'Guardian profile',
        'plural_label' => 'Guardian profiles',
        'fields' => [
            'user_id' => 'User account',
            'national_id_last4' => 'National ID (last 4)',
            'occupation' => 'Occupation',
            'preferred_contact_channel' => 'Preferred contact channel',
            'links_count' => 'Links count',
        ],
        'filters' => [
            'archived' => 'Archived?',
        ],
    ],

    'link' => [
        'model_label' => 'Guardianship link',
        'plural_label' => 'Guardianship links',
        'fields' => [
            'guardian' => 'Guardian',
            'student' => 'Student',
            'relationship' => 'Relationship',
            'is_primary' => 'Primary guardian',
            'can_act_for' => 'May act for student',
            'visible_sections' => 'Visible sections',
            'verified_at' => 'Verified at',
        ],
        'unverified' => 'Unverified',
        'filters' => [
            'verified' => 'Verified?',
        ],
    ],
];
