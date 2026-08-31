<?php

declare(strict_types=1);

/* Filament panel labels — consumed via __('accesscontrol::filament.*'). */

return [

    'group' => 'Access Control',

    'fields' => [
        'created_at' => 'Created at',
    ],

    'role' => [
        'label' => 'Role',
        'plural' => 'Roles',
        'fields' => [
            'name' => 'Role name',
            'guard' => 'Guard',
            'organization' => 'Organization',
            'scope' => 'Scope',
            'scope_global' => 'Global role',
            'scope_organization' => 'Organization role',
            'system' => 'System role',
            'permissions' => 'Granted permissions',
            'user_id' => 'User account',
            'reason' => 'Access change reason',
        ],
        'actions' => [
            'assign_user' => 'Assign to user',
            'revoke_user' => 'Revoke from user',
            'user_search_help' => 'Search by name, username, email, or phone within the current organization.',
            'assigned' => 'The role was assigned and the reason was audited.',
            'revoked' => 'The role was revoked and the reason was audited.',
        ],
        'filters' => [
            'system' => 'System roles only',
        ],
    ],

    'permission' => [
        'label' => 'Permission',
        'plural' => 'Permissions',
        'fields' => [
            'name' => 'Permission name',
            'guard' => 'Guard',
            'module' => 'Module',
        ],
    ],
];
