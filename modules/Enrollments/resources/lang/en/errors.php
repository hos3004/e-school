<?php

declare(strict_types=1);

/*
| Error messages of the Enrollments module.
| Consumed via __('enrollments::errors.key') — keys describe meaning, not wording.
*/

return [
    'placement_reason_required' => 'A reason is required to place the student.',
    'organization_mismatch' => 'The enrollment belongs to another organization.',
    'archived_enrollment' => 'An archived enrollment cannot be reused for placement.',
    'invalid_placement_transition' => 'The enrollment cannot move from ":from" to ":to" during placement.',
    'student_not_cleared' => 'The student has not been accepted for placement.',
    'academic_context_invalid' => 'The selected program or course is not active in this organization.',
    'eligibility_blocked' => 'The student does not meet the selected program eligibility rules.',
    'duplicate_active_enrollment' => 'This student already has an enrollment in the same program.',
    'invalid_freeze_type' => 'The freeze type is invalid. Allowed values: :types.',
    'pause_return_date_in_past' => 'The expected return date cannot be in the past.',
    'reactivation_permission_denied' => 'You do not have the reactivation permission (:permission).',
    'use_pause_action' => 'Use the pause action to record the expected return date.',
    'use_freeze_action' => 'Use the freeze action to record its type and reason.',
    'reactivation_requires_permission' => 'Returning from assessment requires :permission.',
    'transition_reason_required' => 'A clear written reason is required for this change.',
    'invalid_transition' => 'The enrollment cannot move from :from to :to.',
    'student_outside_organization' => 'The selected student does not belong to this organization.',
    'program_outside_organization' => 'The selected program is unavailable in this organization.',
    'level_outside_program' => 'The selected level does not belong to the enrollment program.',
    'level_unchanged' => 'The selected level is already the current level.',
    'activation_requires_placement' => 'An approved enrollment must be activated through course and group placement.',
];
