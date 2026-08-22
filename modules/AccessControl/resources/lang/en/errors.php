<?php

declare(strict_types=1);

/* Business rule violation messages — consumed via BusinessRuleViolation::make(). */

return [

    'role_name_taken' => 'The role name ":name" is already used in this scope.',
    'role_not_found' => 'The requested role does not exist.',
    'role_system_locked' => 'The role ":name" is a system role and cannot be modified or deleted.',
    'role_in_use' => 'The role ":name" cannot be deleted because it is assigned to at least one user.',
    'role_already_assigned' => 'This role is already assigned to the target model.',
    'role_not_assigned' => 'This role is not assigned to the target model.',

    'permission_name_taken' => 'The permission name ":name" already exists for this guard.',
    'permission_not_found' => 'One of the requested permissions does not exist.',
    'permission_in_use' => 'The permission ":name" cannot be deleted because it is attached to roles or models.',
    'permission_already_granted' => 'This permission is already granted to the target model.',
    'permission_not_granted' => 'This permission is not granted to the target model.',

    'guard_mismatch' => 'The permission ":name" does not belong to the role guard.',
];
