<?php

declare(strict_types=1);

/*
| Error messages of the VirtualClassroom module.
| Consumed via __('virtualclassroom::errors.key') — keys describe meaning, not wording.
*/

return [
    'provider_configuration' => 'The virtual classroom provider is not configured correctly.',
    'provider_unavailable' => 'The virtual classroom service is temporarily unavailable. Please try again shortly.',
    'provider_rejected' => 'The virtual classroom provider rejected the requested operation.',
    'unsupported_capability' => 'The selected virtual classroom provider does not support :capability.',
    'capability_runtime_recording_control' => 'starting or pausing a recording after the meeting begins',
    'invalid_webhook_signature' => 'The virtual classroom webhook signature is invalid.',
];
