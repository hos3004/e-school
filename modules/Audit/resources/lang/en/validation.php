<?php

declare(strict_types=1);

/*
| Validation messages — Audit module — English.
*/

return [

    'action_required' => 'The action is required.',
    'action_max' => 'The action must not exceed 128 characters.',
    'auditable_type_required' => 'The auditable record type is required.',
    'values_must_be_object' => 'The values must be an object of keys and values.',
    'must_be_ulid' => 'The identifier must be a valid 26-character ULID.',
    'reason_too_long' => 'The reason must not exceed 2000 characters.',
    'per_page_too_large' => 'The page size must not exceed 200 entries.',
];
