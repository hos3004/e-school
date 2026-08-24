<?php

declare(strict_types=1);

/*
| Error messages of the Enrollments module.
| Consumed via __('enrollments::errors.key') — keys describe meaning, not wording.
*/

return [
    'organization_mismatch' => 'The enrollment belongs to another organization.',
    'archived_enrollment' => 'An archived enrollment cannot be reused for placement.',
    'invalid_placement_transition' => 'The enrollment cannot move from ":from" to ":to" during placement.',
    'student_not_cleared' => 'The student has not been accepted for placement.',
    'academic_context_invalid' => 'The selected program or course is not active in this organization.',
    'eligibility_blocked' => 'The student does not meet the selected program eligibility rules.',
];
