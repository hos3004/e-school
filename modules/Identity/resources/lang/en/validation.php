<?php

declare(strict_types=1);

/*
| Validation messages of the Identity module — identity::validation.*
*/

return [
    'organization_id_required' => 'The organization is required.',
    'name_required' => 'The name is required.',
    'name_too_long' => 'The name is too long (max 191 characters).',
    'email_required' => 'The email address is required.',
    'email_invalid' => 'The email address format is invalid.',
    'password_required' => 'The password is required.',
    'current_password_required' => 'Enter your current password to continue.',

    'locale_invalid' => 'The language is not supported.',
    'timezone_invalid' => 'The timezone is invalid.',

    'status_required' => 'The new status is required.',
    'status_invalid' => 'The status value is unknown.',
    'reason_too_short' => 'The reason is too short — write a clear reason (at least 5 characters).',

    'reset_token_required' => 'The reset token is required.',

    'device_name_too_long' => 'The device name is too long.',
    'push_token_too_long' => 'The push token is too long.',
];
