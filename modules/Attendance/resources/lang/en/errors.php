<?php

declare(strict_types=1);

/*
| Error messages of the Attendance module.
| Consumed via __('attendance::errors.key') — keys describe meaning, not wording.
*/

return [
    'participant_required' => 'The session participant identifier is required to record attendance.',
    'already_recorded' => 'Attendance for this participant was already recorded — each participant has exactly one attendance record.',
    'already_confirmed' => 'This attendance record is already confirmed and cannot be confirmed again.',
    'confirmer_required' => 'The confirmer identifier is required to confirm attendance.',
    'negative_minutes' => 'Minutes values must be non-negative integers.',
    'invalid_session_duration' => 'The session duration must be a positive number of minutes.',
    'override_reason_required' => 'Overriding the derived attendance status requires a written reason of at least :min_chars characters.',
    'override_no_change' => 'Cannot override with the same current status (:status) — pick a genuinely different status.',
];
