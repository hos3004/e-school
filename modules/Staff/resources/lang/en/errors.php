<?php

declare(strict_types=1);

/*
| Error messages of the Staff module.
| Consumed via __('staff::errors.key') — keys describe meaning, not wording.
*/

return [
    'amount_negative' => 'The amount cannot be negative.',
    'availability_approved_not_removable' => 'Approved availability is already used for scheduling and can only be withdrawn by a supervisor.',
    'availability_invalid_approval_transition' => 'This availability cannot be approved from its current state.',
    'availability_time_invalid' => 'The availability start time must come before its end time.',
    'availability_timezone_invalid' => 'The selected time zone is not recognised.',
    'availability_weekday_invalid' => 'The weekday must fall between Sunday and Saturday.',
    'contract_base_not_allowed' => 'The selected calculation base does not apply to this contract type.',
    'contract_base_required' => 'A calculation base is required for this contract type.',
    'contract_overlaps' => 'An active contract already overlaps this period.',
    'contract_period_invalid' => 'The end date cannot fall before the start date.',
    'leave_overlaps_approved' => 'An approved leave already overlaps this period.',
    'leave_period_invalid' => 'The leave end date cannot fall before its start date.',
    'leave_transition_forbidden' => 'The leave cannot move to this state from its current one.',
    'profile_already_exists' => 'This account already has a staff profile.',
    'profile_already_terminated' => 'This staff member has already been terminated.',
    'rate_overlaps' => 'An active rate already overlaps this period for the same scope.',
    'rate_scope_course_required' => 'A course must be selected when the rate scope is a course.',
    'rate_scope_program_required' => 'A program must be selected when the rate scope is a program.',
];
