<?php

declare(strict_types=1);

/* Input validation messages — consumed from FormRequests. */

return [

    'role_name_required' => 'The role name is required.',
    'permission_name_required' => 'The permission name is required.',
    'permission_name_format' => 'The permission name must start with a lowercase letter and contain only latin letters, digits, dots and dashes.',
    'permissions_required' => 'The permissions list is required as an array (an empty array removes all).',
    'role_required' => 'The role id is required.',
    'model_type_required' => 'The target model type is required.',
    'model_id_required' => 'The target model id is required.',
    'ulid_invalid' => 'Invalid identifier; it must be a 26-character ULID.',
    'guard_invalid' => 'Unknown guard; allowed values: web or api.',
    'organization_managed_by_server' => 'The organization is derived from the authenticated account and cannot be supplied.',
];
