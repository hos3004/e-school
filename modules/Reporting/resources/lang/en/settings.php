<?php

declare(strict_types=1);

return [
    'digest_recipient' => [
        'invalid_type' => 'Invalid recipient type.',
        'user_required' => 'A user must be selected when using "existing user".',
        'user_email_invalid' => "The selected user's email cannot receive mail (missing or placeholder).",
        'custom_email_invalid' => 'The custom email address cannot receive mail.',
        'concurrent_change' => 'This setting was changed elsewhere; reload the page and try again.',
        'fields' => [
            'type' => 'Recipient type',
            'user' => 'User',
            'email' => 'Custom email',
            'reason' => 'Reason for change',
        ],
    ],
];
