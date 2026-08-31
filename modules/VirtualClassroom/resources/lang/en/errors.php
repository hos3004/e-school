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
    'session_not_joinable' => 'This session cannot be joined in its current state.',
    'join_window_closed' => 'Joining this session is not available at this time.',
    'student_frozen_cannot_join' => 'A student with a frozen enrollment cannot join the classroom.',
    'session_not_found' => 'The session does not exist in this organization.',
    'not_provisioned' => 'The classroom has not been provisioned with the provider.',
    'classroom_not_ready' => 'The classroom is not ready to join. Check its connection or retry provisioning.',
    'reason_required' => 'A written reason is required.',
    'invalid_status' => 'The classroom cannot be provisioned from its current status: :status.',
];
