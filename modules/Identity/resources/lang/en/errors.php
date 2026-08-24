<?php

declare(strict_types=1);

/*
| Error messages of the Identity module.
| Consumed via __('identity::errors.key') — keys describe meaning, not wording.
*/

return [
    'email_taken' => 'This email address is already registered.',
    'username_taken' => 'This username is already taken.',

    'status_reason_required' => 'Changing an account status requires a written reason.',
    'self_status_change' => 'You cannot change your own account status.',
    'invalid_status_transition' => 'Cannot transition from ":from" to ":to".',

    'current_password_wrong' => 'The current password is incorrect.',
    'password_unchanged' => 'The new password must differ from the current one.',

    'reset_token_invalid' => 'The reset link is invalid or has already been used.',
    'reset_token_expired' => 'The reset link has expired, please request a new one.',
    'phone_reset_invalid' => 'The verification code is invalid, expired, or has already been used.',
    'account_link_unverified' => 'The existing account could not be verified for this organization and contact.',

    'push_token_in_use' => 'The push token is already registered on another active device.',
    'device_already_revoked' => 'The device has already been revoked.',
];
