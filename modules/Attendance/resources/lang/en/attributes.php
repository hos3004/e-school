<?php

declare(strict_types=1);

/*
| Human attribute names used in validation messages and the Filament panel.
| Consumed via __('attendance::attributes.key').
*/

return [
    'session_participant_id' => 'Session participant',
    'attended_minutes' => 'Actual attended minutes',
    'session_minutes' => 'Session duration',
    'joined_after_minutes' => 'Minutes late at start',
    'left_before_minutes' => 'Minutes left before end',
    'status' => 'Attendance status',
    'derived_status' => 'Automatically derived status',
    'confirmed_at' => 'Confirmed at',
    'confirmed_by' => 'Confirmed by',
    'override_reason' => 'Override reason',
    'reason' => 'Reason',
];
