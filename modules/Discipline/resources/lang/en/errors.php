<?php

declare(strict_types=1);

/*
| Error messages of the Discipline module.
| Consumed via __('discipline::errors.key') — keys describe the meaning, not the wording.
*/

return [
    'unknown_violation_type' => 'The violation type ":type" is not recognized in the discipline settings.',

    'already_waived' => 'This violation has already been waived and cannot be waived twice.',

    'reactivation_open_exists' => 'An open reactivation request already exists for this enrollment; wait for its decision before applying again.',
    'reactivation_max_attempts' => 'The allowed reactivation attempts have been exhausted (:max_attempts attempts).',
    'reactivation_cooldown' => 'The cooldown period between reactivation attempts has not elapsed yet (:days days since the last decision).',
    'reactivation_invalid_decision' => 'Invalid decision; only approval or rejection is allowed.',
    'reactivation_invalid_transition' => 'Cannot move the request from ":from" to ":to".',
    'reactivation_assessment_required' => 'Approval requires linking a readiness assessment result before reinstatement.',
    'reactivation_cancellation_not_allowed' => 'The request cannot be cancelled in its current state ":status".',
];
