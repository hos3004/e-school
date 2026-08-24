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
            'system' => 'System role',
            'permissions' => 'Granted permissions',
            'user_id' => 'User ID',
        ],
        'actions' => [
            'assign_user' => 'Assign to user',
            'revoke_user' => 'Revoke from user',
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
