<?php

declare(strict_types=1);

/*
| Validation messages of the Attendance module.
| Consumed from FormRequests via __('attendance::validation.key').
*/

return [
    'participant_required' => 'The session participant identifier is required.',
    'participant_invalid' => 'The session participant identifier is invalid.',
    'attended_minutes_required' => 'Actual attended minutes are required.',
    'session_minutes_required' => 'The total session duration is required to derive the status.',
    'session_minutes_min' => 'The session duration must be at least one minute.',
    'minutes_integer' => 'Minutes must be an integer.',
    'minutes_min' => 'Minutes must be zero or more.',
    'minutes_max' => 'The minutes value exceeds the allowed limit.',
    'status_required' => 'A new status is required when overriding.',
    'status_invalid' => 'The selected status is unknown.',
    'reason_required' => 'An override reason is required by the audit rule.',
    'reason_min' => 'The override reason is too short — write a meaningful reason.',
    'reason_max' => 'The override reason exceeds the allowed length.',
];
